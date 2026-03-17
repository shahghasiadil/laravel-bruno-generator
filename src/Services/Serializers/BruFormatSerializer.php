<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers;

use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
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
        $blocks[] = $this->formatMetaBlock($request->name, $request->description, $request->sequence);

        // HTTP method block (required)
        $blocks[] = $this->formatMethodBlock($request->method, $request->url, $request->body, $request->auth);

        // Query params block
        if ($request->hasQueryParams()) {
            $blocks[] = $this->formatParamsBlock($request->queryParams);
        }

        // Headers block
        if ($request->hasHeaders()) {
            $blocks[] = $this->formatHeadersBlock($request->headers);
        }

        // Auth block
        if ($request->hasAuth()) {
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
     * Serialize environment .bru file.
     *
     * @param  array<string, string>  $vars
     */
    public function serializeEnvironment(string $name, array $vars): string
    {
        $lines = ['vars {'];

        foreach ($vars as $key => $value) {
            $lines[] = "  {$key}: {$value}";
        }

        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    public function getFileExtension(): string
    {
        return '.bru';
    }

    /**
     * Format meta block.
     */
    private function formatMetaBlock(string $name, string $desc, int $seq): string
    {
        return <<<BRU
meta {
  name: {$name}
  type: http
  seq: {$seq}
}
BRU;
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
     * Format query params block.
     *
     * @param  array<string, string>  $params
     */
    private function formatParamsBlock(array $params): string
    {
        $lines = ['params:query {'];

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

        if ($body->type === BodyType::FORM_URLENCODED && $body->content !== []) {
            $lines = ['body:form-urlencoded {'];
            foreach ($body->content as $key => $value) {
                $lines[] = "  {$key}: {$value}";
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
}
