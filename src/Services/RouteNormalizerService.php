<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteNormalizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestSettings;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;

final class RouteNormalizerService implements RouteNormalizerInterface
{
    private int $sequence = 0;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly FormRequestParserService $formRequestParser,
        private readonly array $config,
    ) {}

    /**
     * Normalize routes to Bruno request format.
     *
     * @param  Collection<int, RouteInfo>  $routes
     * @return Collection<int, BrunoRequest>
     */
    public function normalize(Collection $routes): Collection
    {
        $this->sequence = 0;

        return $routes
            ->flatMap(fn (RouteInfo $route) => $this->normalizeRoute($route))
            ->values();
    }

    /**
     * Normalize a single route (may produce multiple requests for routes with multiple methods).
     *
     * @return Collection<int, BrunoRequest>
     */
    private function normalizeRoute(RouteInfo $route): Collection
    {
        $requests = collect();

        // Filter out HEAD method as it's typically not needed in API testing
        $methods = array_filter($route->methods, fn ($method) => $method !== 'HEAD');

        foreach ($methods as $method) {
            $requests->push($this->createBrunoRequest($route, $method));
        }

        return $requests;
    }

    /**
     * Create a BrunoRequest from RouteInfo for a specific HTTP method.
     */
    private function createBrunoRequest(RouteInfo $route, string $method): BrunoRequest
    {
        $this->sequence++;

        $name = $this->generateName($route, $method);
        $description = $this->generateDescription($route);
        $url = $this->buildUrl($route);
        $headers = $this->buildHeaders($route, $method);
        $queryParams = $this->extractQueryParams($route);
        $pathVariables = $this->extractPathVariables($route);
        $body = $this->parseRequestBody($route, $method);
        $auth = $this->determineAuth($route);
        $group = $this->determineGroup($route);
        $docs = $this->extractPhpDocDocs($route);
        $tests = $this->generateTests($route, $method);
        $scripts = $this->generateScripts($route, $method);
        $settings = $this->generateSettings();

        return new BrunoRequest(
            name: $name,
            description: $description,
            sequence: $this->sequence,
            method: strtoupper($method),
            url: $url,
            headers: $headers,
            queryParams: $queryParams,
            pathVariables: $pathVariables,
            body: $body,
            auth: $auth,
            group: $group,
            controller: $route->controller,
            tags: $this->extractTags($route),
            settings: $settings,
            preRequestScript: $scripts['pre'] ?? null,
            postResponseScript: $scripts['post'] ?? null,
            tests: $tests,
            docs: $docs,
        );
    }

    /**
     * Generate request name from route.
     */
    private function generateName(RouteInfo $route, string $method): string
    {
        // Use route name if available
        if ($route->name !== null) {
            return Str::title(str_replace(['.', '_', '-'], ' ', $route->name));
        }

        // Use controller method if available
        if ($route->controllerMethod !== null && $route->controllerMethod !== '__invoke') {
            return Str::title(Str::headline($route->controllerMethod));
        }

        // Generate from URI and method
        $uriParts = array_filter(explode('/', $route->uri), fn ($part) => ! str_contains($part, '{'));
        $resourceName = ! empty($uriParts) ? end($uriParts) : 'Request';

        return Str::title($method.' '.Str::singular(Str::headline($resourceName)));
    }

    /**
     * Generate description from route.
     */
    private function generateDescription(RouteInfo $route): string
    {
        // Format-aware description length
        $format = $this->config['output_format'] ?? 'bru';
        $maxLength = $format === 'yaml'
            ? PHP_INT_MAX // No limit for YAML
            : ($this->config['advanced']['max_description_length'] ?? 200);

        // Use route name as base description
        if ($route->name !== null) {
            $description = 'Endpoint: '.$route->name;
        } elseif ($route->controller !== null && $route->controllerMethod !== null) {
            $description = "Controller: {$route->controller}@{$route->controllerMethod}";
        } else {
            $description = 'API endpoint for '.$route->uri;
        }

        // Add middleware info if present
        if (! empty($route->middleware)) {
            $middlewareList = implode(', ', array_slice($route->middleware, 0, 3));
            if (count($route->middleware) > 3) {
                $middlewareList .= '...';
            }
            $description .= " | Middleware: {$middlewareList}";
        }

        return Str::limit($description, $maxLength);
    }

    /**
     * Build request URL with variable substitution.
     */
    private function buildUrl(RouteInfo $route): string
    {
        $baseUrlVar = $this->config['variables']['base_url_var'] ?? 'baseUrl';
        $url = '{{'.$baseUrlVar.'}}/'.ltrim($route->uri, '/');

        // Convert route parameters to Bruno variables
        // Use negative lookbehind/lookahead to avoid matching {{baseUrl}}
        if ($this->config['request_generation']['parameterize_route_params'] ?? true) {
            $url = preg_replace('/(?<!\{)\{([^}?]+)\??\}(?!\})/', '{{$1}}', $url);
        }

        return $url;
    }

    /**
     * Build default headers for request.
     *
     * @return array<string, string>
     */
    private function buildHeaders(RouteInfo $route, string $method): array
    {
        if (! ($this->config['request_generation']['include_default_headers'] ?? true)) {
            return [];
        }

        $headers = $this->config['default_headers'] ?? [];

        // Remove Content-Type for GET/DELETE methods
        if (in_array(strtoupper($method), ['GET', 'DELETE', 'HEAD'])) {
            unset($headers['Content-Type']);
        }

        return $headers;
    }

    /**
     * Extract query parameters from route.
     *
     * @return array<string, string>
     */
    private function extractQueryParams(RouteInfo $route): array
    {
        // This will be enhanced when FormRequest parsing is added
        return [];
    }

    /**
     * Extract path variables from route parameters.
     *
     * @return array<string, string>
     */
    private function extractPathVariables(RouteInfo $route): array
    {
        $variables = [];

        foreach ($route->parameters as $paramName => $paramPattern) {
            $variables[$paramName] = $this->generateExampleValue($paramName);
        }

        return $variables;
    }

    /**
     * Generate example value for parameter based on name.
     */
    private function generateExampleValue(string $paramName): string
    {
        $lowerName = strtolower($paramName);

        return match (true) {
            str_contains($lowerName, 'id') => '1',
            str_contains($lowerName, 'uuid') => '123e4567-e89b-12d3-a456-426614174000',
            str_contains($lowerName, 'slug') => 'example-slug',
            str_contains($lowerName, 'code') => 'ABC123',
            str_contains($lowerName, 'token') => 'sample-token',
            default => 'value',
        };
    }

    /**
     * Parse request body from route.
     */
    private function parseRequestBody(RouteInfo $route, string $method): ?RequestBody
    {
        // Only include body for methods that typically have one
        if (! in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        // Try to infer from FormRequest if enabled
        if ($this->config['request_generation']['infer_body_from_form_request'] ?? true) {
            $formRequestBody = $this->formRequestParser->parseFromRoute($route);
            if ($formRequestBody !== null) {
                return $formRequestBody;
            }
        }

        // Return empty JSON body as default
        return new RequestBody(
            type: BodyType::JSON,
            content: [],
            raw: null,
        );
    }

    /**
     * Determine authentication configuration for request.
     */
    private function determineAuth(RouteInfo $route): ?AuthBlock
    {
        if (! ($this->config['auth']['include_auth'] ?? true)) {
            return null;
        }

        $authMode = $this->config['auth']['mode'] ?? 'bearer';
        $authMiddleware = $this->config['auth']['auth_middleware'] ?? [];

        // Check if route has auth middleware
        $hasAuth = ! empty(array_intersect($route->middleware, $authMiddleware));

        if (! $hasAuth && $authMode === 'none') {
            return null;
        }

        // If route has auth middleware or mode is not 'none', include auth block
        if ($hasAuth || $authMode !== 'none') {
            return $this->createAuthBlock($authMode);
        }

        return null;
    }

    /**
     * Create auth block based on mode.
     */
    private function createAuthBlock(string $mode): AuthBlock
    {
        $authType = match ($mode) {
            'bearer' => AuthType::BEARER,
            'basic' => AuthType::BASIC,
            'oauth2' => AuthType::OAUTH2,
            default => AuthType::NONE,
        };

        $config = match ($authType) {
            AuthType::BEARER => [
                'token' => '{{'.($this->config['auth']['bearer_token_var'] ?? 'authToken').'}}',
            ],
            AuthType::BASIC => [
                'username' => '{{username}}',
                'password' => '{{password}}',
            ],
            AuthType::OAUTH2 => [
                'accessToken' => '{{accessToken}}',
            ],
            default => [],
        };

        return new AuthBlock(type: $authType, config: $config);
    }

    /**
     * Determine group for request based on organization strategy.
     */
    private function determineGroup(RouteInfo $route): ?string
    {
        $groupBy = $this->config['organization']['group_by'] ?? 'prefix';

        return match ($groupBy) {
            'prefix' => $this->extractGroupFromPrefix($route),
            'controller' => $route->controller,
            'tag' => $this->extractGroupFromTags($route),
            default => null,
        };
    }

    /**
     * Extract group name from route prefix.
     */
    private function extractGroupFromPrefix(RouteInfo $route): ?string
    {
        $depth = $this->config['organization']['folder_depth'] ?? 2;
        $parts = array_filter(explode('/', $route->uri), fn ($part) => ! str_contains($part, '{'));

        if (empty($parts)) {
            return null;
        }

        // Take only the specified depth
        $groupParts = array_slice($parts, 0, $depth);

        return implode('/', $groupParts);
    }

    /**
     * Extract group from tags.
     */
    private function extractGroupFromTags(RouteInfo $route): ?string
    {
        $tags = $this->extractTags($route);

        return ! empty($tags) ? $tags[0] : null;
    }

    /**
     * Extract tags from route.
     *
     * @return array<int, string>
     */
    private function extractTags(RouteInfo $route): array
    {
        $tags = [];

        // Extract from route name
        if ($route->name !== null) {
            $parts = explode('.', $route->name);
            if (count($parts) > 1) {
                $tags[] = $parts[0];
            }
        }

        // Extract from controller
        if ($route->controller !== null) {
            $controllerTag = str_replace('Controller', '', $route->controller);
            if (! in_array($controllerTag, $tags, true)) {
                $tags[] = $controllerTag;
            }
        }

        return array_unique($tags);
    }

    /**
     * Extract PHPDoc documentation from controller method.
     */
    private function extractPhpDocDocs(RouteInfo $route): ?string
    {
        if (! ($this->config['advanced']['include_phpdoc_docs'] ?? true)) {
            return null;
        }

        if ($route->controller === null || $route->controllerMethod === null) {
            return null;
        }

        try {
            $controllerClass = $this->resolveControllerClass($route->action);

            if ($controllerClass === null || ! class_exists($controllerClass)) {
                return null;
            }

            $reflection = new \ReflectionClass($controllerClass);
            $method = $reflection->getMethod($route->controllerMethod);
            $docComment = $method->getDocComment();

            if ($docComment === false || empty($docComment)) {
                return null;
            }

            // Parse and clean PHPDoc
            $docs = $this->parsePhpDoc($docComment);

            // Format-aware truncation
            $format = $this->config['output_format'] ?? 'bru';
            if ($format === 'yaml') {
                return $docs; // Full Markdown for YAML
            }

            return Str::limit($docs, $this->config['advanced']['max_description_length'] ?? 200);
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
     * Parse PHPDoc comment into markdown.
     */
    private function parsePhpDoc(string $docComment): string
    {
        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            // Remove leading /** */ and *
            $line = trim($line);
            $line = ltrim($line, '/*');
            $line = rtrim($line, '*/');
            $line = ltrim($line, '*');
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Skip @tags
            if (str_starts_with($line, '@')) {
                continue;
            }

            $description[] = $line;
        }

        return implode("\n", $description);
    }

    /**
     * Generate tests for request.
     */
    private function generateTests(RouteInfo $route, string $method): ?string
    {
        if (! ($this->config['advanced']['generate_tests'] ?? false)) {
            return null;
        }

        $tests = [];
        $templates = $this->config['advanced']['test_templates'] ?? [];

        // Status check test
        if ($templates['status_check'] ?? true) {
            $tests[] = <<<'JS'
test("should return successful response", function() {
  expect(res.getStatus()).to.equal(200);
});
JS;
        }

        // Response time test
        if ($templates['response_time'] ?? false) {
            $tests[] = <<<'JS'
test("should respond within acceptable time", function() {
  expect(res.getResponseTime()).to.be.below(1000);
});
JS;
        }

        // Schema validation test
        if ($templates['schema_validation'] ?? false) {
            $tests[] = <<<'JS'
test("should return valid JSON", function() {
  expect(res.getBody()).to.be.an('object');
});
JS;
        }

        return ! empty($tests) ? implode("\n\n", $tests) : null;
    }

    /**
     * Generate request settings.
     */
    private function generateSettings(): RequestSettings
    {
        $settingsConfig = $this->config['advanced']['request_settings'] ?? [];

        if ($settingsConfig === []) {
            return RequestSettings::default();
        }

        return RequestSettings::fromConfig($settingsConfig);
    }

    /**
     * Generate scripts for request.
     *
     * @return array<string, string|null>
     */
    private function generateScripts(RouteInfo $route, string $method): array
    {
        $scripts = ['pre' => null, 'post' => null];

        // Pre-request script
        if ($this->config['advanced']['generate_pre_request_scripts'] ?? false) {
            $preTemplates = $this->config['advanced']['script_templates']['pre_request'] ?? [];

            if (! empty($preTemplates)) {
                $scripts['pre'] = implode("\n", $preTemplates);
            }
        }

        // Post-response script
        if ($this->config['advanced']['generate_post_response_scripts'] ?? false) {
            $postTemplates = $this->config['advanced']['script_templates']['post_response'] ?? [];

            if (! empty($postTemplates)) {
                $scripts['post'] = implode("\n", $postTemplates);
            }
        }

        return $scripts;
    }
}
