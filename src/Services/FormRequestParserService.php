<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\FormRequestParseException;

final class FormRequestParserService
{
    private int $currentNestingDepth = 0;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * Parse FormRequest from route.
     */
    public function parseFromRoute(RouteInfo $route): ?RequestBody
    {
        if ($route->controller === null || $route->controllerMethod === null) {
            return null;
        }

        try {
            $formRequestClass = $this->findFormRequestClass($route);

            if ($formRequestClass === null) {
                return null;
            }

            return $this->parse($formRequestClass);
        } catch (\Throwable $e) {
            // Silently fail - FormRequest parsing is optional
            return null;
        }
    }

    /**
     * Find FormRequest class used by controller method.
     */
    private function findFormRequestClass(RouteInfo $route): ?string
    {
        try {
            // Build full controller class name from action
            $controllerClass = $this->resolveControllerClass($route->action);

            if ($controllerClass === null || !class_exists($controllerClass)) {
                return null;
            }

            $reflection = new ReflectionClass($controllerClass);
            $method = $reflection->getMethod($route->controllerMethod);

            // Check method parameters for FormRequest
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                if (is_subclass_of($typeName, FormRequest::class)) {
                    return $typeName;
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve full controller class name from action.
     */
    private function resolveControllerClass(string $action): ?string
    {
        if (str_contains($action, '@')) {
            [$controller] = explode('@', $action, 2);

            return $controller;
        }

        if (class_exists($action)) {
            return $action;
        }

        return null;
    }

    /**
     * Parse FormRequest class to infer request body.
     */
    public function parse(string $formRequestClass): ?RequestBody
    {
        if (!class_exists($formRequestClass)) {
            throw FormRequestParseException::classNotFound($formRequestClass);
        }

        if (!is_subclass_of($formRequestClass, FormRequest::class)) {
            throw FormRequestParseException::invalidFormRequest($formRequestClass);
        }

        try {
            // Instantiate FormRequest (without running authorization)
            $reflection = new ReflectionClass($formRequestClass);
            $instance = $reflection->newInstanceWithoutConstructor();

            // Get rules
            $rulesMethod = $reflection->getMethod('rules');
            $rulesMethod->setAccessible(true);
            $rules = $rulesMethod->invoke($instance);

            if (!is_array($rules) || empty($rules)) {
                return null;
            }

            // Convert rules to example values
            $this->currentNestingDepth = 0;
            $content = $this->rulesToExampleValues($rules);

            return new RequestBody(
                type: BodyType::JSON,
                content: $content,
                raw: null,
            );
        } catch (\Throwable $e) {
            throw FormRequestParseException::rulesParseFailed($formRequestClass, $e->getMessage());
        }
    }

    /**
     * Convert validation rules to example values.
     *
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private function rulesToExampleValues(array $rules): array
    {
        $maxNestingDepth = $this->config['advanced']['form_request']['max_nesting_depth'] ?? 5;

        if ($this->currentNestingDepth >= $maxNestingDepth) {
            throw FormRequestParseException::maxNestingDepthExceeded($maxNestingDepth);
        }

        $result = [];

        foreach ($rules as $field => $fieldRules) {
            // Skip if field should be excluded
            if (!$this->shouldIncludeField($fieldRules)) {
                continue;
            }

            // Handle nested fields (e.g., 'user.name' or 'tags.*')
            if (str_contains($field, '.')) {
                $this->processNestedField($result, $field, $fieldRules);
            } else {
                $result[$field] = $this->generateExampleValue($field, $fieldRules);
            }
        }

        return $result;
    }

    /**
     * Determine if field should be included based on rules.
     *
     * @param mixed $rules
     */
    private function shouldIncludeField(mixed $rules): bool
    {
        $includeOptional = $this->config['advanced']['form_request']['include_optional_fields'] ?? true;

        if (!$includeOptional) {
            $ruleString = is_array($rules) ? implode('|', $rules) : (string) $rules;

            // Exclude nullable and sometimes fields if configured
            if (str_contains($ruleString, 'nullable') || str_contains($ruleString, 'sometimes')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process nested field notation.
     *
     * @param array<string, mixed> $result
     * @param mixed $fieldRules
     */
    private function processNestedField(array &$result, string $field, mixed $fieldRules): void
    {
        $parts = explode('.', $field);
        $current = &$result;

        $this->currentNestingDepth++;

        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;

            // Handle array notation (tags.*)
            if ($part === '*') {
                // Initialize parent as array if not set
                if (!is_array($current)) {
                    $current = [];
                }

                // Add example array element
                if ($isLast) {
                    $current[] = $this->generateExampleValue($field, $fieldRules);
                } else {
                    // Initialize nested structure
                    if (empty($current)) {
                        $current[] = [];
                    }
                    $current = &$current[0];
                }
            } else {
                // Regular nested field
                if (!isset($current[$part])) {
                    $current[$part] = $isLast ? null : [];
                }

                if ($isLast) {
                    $current[$part] = $this->generateExampleValue($field, $fieldRules);
                } else {
                    $current = &$current[$part];
                }
            }
        }

        $this->currentNestingDepth--;
    }

    /**
     * Generate example value for field based on rules.
     *
     * @param mixed $rules
     */
    private function generateExampleValue(string $field, mixed $rules): mixed
    {
        $ruleArray = $this->normalizeRules($rules);
        $fieldType = $this->inferFieldType($field, $ruleArray);

        return match ($fieldType) {
            'email' => 'user@example.com',
            'url' => 'https://example.com',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'date' => '2024-01-01',
            'datetime' => '2024-01-01T00:00:00Z',
            'time' => '12:00:00',
            'boolean' => true,
            'integer' => $this->generateIntegerValue($ruleArray),
            'numeric' => $this->generateNumericValue($ruleArray),
            'array' => [],
            'file' => 'file.pdf',
            'image' => 'image.jpg',
            'string' => $this->generateStringValue($field, $ruleArray),
            default => '',
        };
    }

    /**
     * Normalize rules to array format.
     *
     * @param mixed $rules
     * @return array<int, string>
     */
    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        if (is_array($rules)) {
            $normalized = [];
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    $normalized[] = $rule;
                } elseif (is_object($rule)) {
                    $normalized[] = get_class($rule);
                }
            }

            return $normalized;
        }

        return [];
    }

    /**
     * Infer field type from name and rules.
     *
     * @param array<int, string> $rules
     */
    private function inferFieldType(string $field, array $rules): string
    {
        // Check explicit type rules first
        foreach ($rules as $rule) {
            $ruleLower = strtolower($rule);

            if (str_starts_with($ruleLower, 'email')) {
                return 'email';
            }
            if (str_starts_with($ruleLower, 'url')) {
                return 'url';
            }
            if (str_starts_with($ruleLower, 'uuid')) {
                return 'uuid';
            }
            if (str_starts_with($ruleLower, 'date')) {
                return 'date';
            }
            if (str_starts_with($ruleLower, 'boolean') || $ruleLower === 'bool') {
                return 'boolean';
            }
            if (str_starts_with($ruleLower, 'integer') || $ruleLower === 'int') {
                return 'integer';
            }
            if (str_starts_with($ruleLower, 'numeric')) {
                return 'numeric';
            }
            if (str_starts_with($ruleLower, 'array')) {
                return 'array';
            }
            if (str_starts_with($ruleLower, 'file')) {
                return 'file';
            }
            if (str_starts_with($ruleLower, 'image')) {
                return 'image';
            }
        }

        // Infer from field name
        $fieldLower = strtolower($field);

        return match (true) {
            str_contains($fieldLower, 'email') => 'email',
            str_contains($fieldLower, 'url') || str_contains($fieldLower, 'link') => 'url',
            str_contains($fieldLower, 'uuid') => 'uuid',
            str_contains($fieldLower, 'date') && !str_contains($fieldLower, 'update') => 'date',
            str_contains($fieldLower, 'time') => 'time',
            str_contains($fieldLower, 'is_') || str_contains($fieldLower, 'has_') => 'boolean',
            str_contains($fieldLower, 'count') || str_contains($fieldLower, 'age') || str_contains($fieldLower, '_id') => 'integer',
            str_contains($fieldLower, 'price') || str_contains($fieldLower, 'amount') => 'numeric',
            default => 'string',
        };
    }

    /**
     * Generate integer value from rules.
     *
     * @param array<int, string> $rules
     */
    private function generateIntegerValue(array $rules): int
    {
        foreach ($rules as $rule) {
            if (preg_match('/min:(\d+)/', $rule, $matches)) {
                return (int) $matches[1];
            }
        }

        return 1;
    }

    /**
     * Generate numeric value from rules.
     *
     * @param array<int, string> $rules
     */
    private function generateNumericValue(array $rules): float|int
    {
        foreach ($rules as $rule) {
            if (preg_match('/min:([\d.]+)/', $rule, $matches)) {
                return str_contains($matches[1], '.') ? (float) $matches[1] : (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * Generate string value from field name and rules.
     *
     * @param array<int, string> $rules
     */
    private function generateStringValue(string $field, array $rules): string
    {
        // Get max length from rules
        $maxLength = 255;
        foreach ($rules as $rule) {
            if (preg_match('/max:(\d+)/', $rule, $matches)) {
                $maxLength = (int) $matches[1];
                break;
            }
        }

        // Generate meaningful example based on field name
        $example = Str::title(str_replace(['_', '-'], ' ', $field));

        // Truncate if needed
        return Str::limit($example, $maxLength, '');
    }
}
