<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers;

use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentVariable;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestSettings;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;

final class BruFormatSerializer implements FormatSerializerInterface
{
    public function __construct() {}

    /**
     * Serialize .bru request file.
     */
    public function serializeRequest(BrunoRequest $request): string
    {
        $blocks = [];

        // Meta block (required)
        $blocks[] = $this->formatMetaBlock($request->name, $request->sequence, $request->tags);

        // HTTP method block (required)
        $blocks[] = $this->formatMethodBlock($request->method, $request->url, $request->body, $request->auth);

        // Path params block
        if ($request->hasPathVariables()) {
            $blocks[] = $this->formatParamsBlock('path', $request->pathVariables);
        }

        // Query params block
        if ($request->hasQueryParams()) {
            $blocks[] = $this->formatParamsBlock('query', $request->queryParams);
        }

        // Headers block
        if ($request->hasHeaders()) {
            $blocks[] = $this->formatHeadersBlock($request->headers);
        }

        // Auth block (skipped for `inherit`, which has no config of its own)
        if ($request->hasAuth() && $request->auth !== null && ! $request->auth->isInherit()) {
            $blocks[] = $this->formatAuthBlock($request->auth);
        }

        // Body block
        if ($request->hasBody()) {
            $blocks[] = $this->formatBodyBlock($request->body);
        }

        // Settings block
        if ($request->settings !== null) {
            $blocks[] = $this->formatSettingsBlock($request->settings);
        }

        // Pre-request script
        if ($request->preRequestScript !== null) {
            $blocks[] = $this->formatScriptBlock('pre-request', $request->preRequestScript);
        }

        // Post-response script
        if ($request->postResponseScript !== null) {
            $blocks[] = $this->formatScriptBlock('post-response', $request->postResponseScript);
        }

        // Tests block
        if ($request->tests !== null) {
            $blocks[] = $this->formatTestsBlock($request->tests);
        }

        // Docs block
        if ($request->docs !== null) {
            $blocks[] = $this->formatDocsBlock($request->docs);
        }

        return implode("\n", $blocks)."\n";
    }

    /**
     * Serialize environment .bru file. Secret variables are written as
     * name-only entries in a vars:secret block; their values are never
     * written to disk.
     *
     * @param  array<int, EnvironmentVariable>  $vars
     */
    public function serializeEnvironment(string $name, array $vars): string
    {
        $lines = ['vars {'];
        $secretNames = [];

        foreach ($vars as $var) {
            if ($var->secret) {
                $secretNames[] = $var->name;

                continue;
            }

            if ($var->description !== null) {
                $lines[] = "  @description('''{$var->description}''')";
            }

            $lines[] = "  {$var->name}: {$var->value}";
        }

        $lines[] = '}';

        if ($secretNames !== []) {
            $lines[] = '';
            $lines[] = 'vars:secret [';
            $lastIndex = count($secretNames) - 1;
            foreach ($secretNames as $index => $secretName) {
                $comma = $index < $lastIndex ? ',' : '';
                $lines[] = "  {$secretName}{$comma}";
            }
            $lines[] = ']';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Serialize the collection.bru auth block.
     */
    public function serializeCollectionAuth(AuthBlock $auth): ?string
    {
        if ($auth->isNone() || $auth->isInherit()) {
            return null;
        }

        return $this->formatAuthBlock($auth)."\n";
    }

    public function getFileExtension(): string
    {
        return '.bru';
    }

    /**
     * Format meta block.
     *
     * @param  array<int, string>  $tags
     */
    private function formatMetaBlock(string $name, int $seq, array $tags = []): string
    {
        $lines = ['meta {', "  name: {$name}", '  type: http', "  seq: {$seq}"];

        if ($tags !== []) {
            $lines[] = '  tags: [';
            foreach ($tags as $tag) {
                $lines[] = "    {$tag}";
            }
            $lines[] = '  ]';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Format HTTP method block.
     */
    private function formatMethodBlock(string $method, string $url, ?RequestBody $body, ?AuthBlock $auth): string
    {
        $methodLower = strtolower($method);

        // Determine body type
        $bodyType = 'none';
        if ($body !== null && $body->hasContent()) {
            $bodyType = $body->type->value;
        }

        // Determine auth type
        $authType = 'none';
        if ($auth !== null && ! $auth->isNone()) {
            $authType = $auth->type->value;
        }

        return <<<BRU
{$methodLower} {
  url: {$url}
  body: {$bodyType}
  auth: {$authType}
}
BRU;
    }

    /**
     * Format a params block (query or path).
     *
     * @param  array<string, string>  $params
     */
    private function formatParamsBlock(string $type, array $params): string
    {
        $lines = ["params:{$type} {"];

        foreach ($params as $key => $value) {
            $lines[] = "  {$key}: {$value}";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Format headers block.
     *
     * @param  array<string, string>  $headers
     */
    private function formatHeadersBlock(array $headers): string
    {
        $lines = ['headers {'];

        foreach ($headers as $key => $value) {
            $lines[] = "  {$key}: {$value}";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Format auth block.
     */
    private function formatAuthBlock(?AuthBlock $auth): string
    {
        if ($auth === null || $auth->isNone()) {
            return '';
        }

        $authType = $auth->type->value;
        $lines = ["auth:{$authType} {"];

        foreach ($auth->config as $key => $value) {
            $lines[] = "  {$key}: {$value}";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Format body block.
     */
    private function formatBodyBlock(?RequestBody $body): string
    {
        if ($body === null || ! $body->hasContent()) {
            return '';
        }

        $bodyType = $body->type->value;

        if ($body->raw !== null) {
            return <<<BRU
body:{$bodyType} {
{$body->raw}
}
BRU;
        }

        if ($body->type === BodyType::JSON && $body->content !== []) {
            $json = json_encode($body->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return '';
            }
            $indented = $this->indentContent($json, 1);

            return <<<BRU
body:json {
{$indented}
}
BRU;
        }

        if (in_array($body->type, [BodyType::FORM_URLENCODED, BodyType::MULTIPART_FORM], true) && $body->content !== []) {
            $lines = ["body:{$bodyType} {"];
            foreach ($body->content as $key => $value) {
                $lines[] = "  {$key}: {$this->stringifyFieldValue($value)}";
            }
            $lines[] = '}';

            return implode("\n", $lines);
        }

        return '';
    }

    /**
     * Format script block.
     */
    private function formatScriptBlock(string $type, string $script): string
    {
        $indented = $this->indentContent($script, 1);

        return <<<BRU
script:{$type} {
{$indented}
}
BRU;
    }

    /**
     * Format tests block.
     */
    private function formatTestsBlock(string $tests): string
    {
        $indented = $this->indentContent($tests, 1);

        return <<<BRU
tests {
{$indented}
}
BRU;
    }

    /**
     * Format settings block.
     */
    private function formatSettingsBlock(RequestSettings $settings): string
    {
        $lines = ['settings {'];

        $settingsArray = $settings->toArray();
        foreach ($settingsArray as $key => $value) {
            $formattedValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            $lines[] = "  {$key}: {$formattedValue}";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Format docs block.
     */
    private function formatDocsBlock(string $docs): string
    {
        $indented = $this->indentContent($docs, 1);

        return <<<BRU
docs {
{$indented}
}
BRU;
    }

    /**
     * Indent content by specified number of spaces.
     */
    private function indentContent(string $content, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $lines = explode("\n", $content);

        return implode("\n", array_map(fn ($line) => $indent.$line, $lines));
    }

    /**
     * Stringify a form/multipart field value. Arrays and objects are
     * JSON-encoded rather than interpolated, which would otherwise produce
     * the literal string "Array" and a PHP warning for nested/array rules
     * (e.g. `tags.*`, `user.name`) combined with a file/image field.
     */
    private function stringifyFieldValue(mixed $value): string
    {
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES);

            return $json === false ? '' : $json;
        }

        return (string) $value;
    }
}
