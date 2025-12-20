<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\BrunoSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\FolderNode;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\FileType;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;

final class BrunoSerializerService implements BrunoSerializerInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * Serialize collection structure to file map.
     *
     * @return Collection<int, array{path: FilePath, content: FileContent}>
     */
    public function serialize(CollectionStructure $structure, string $basePath): Collection
    {
        $files = collect();

        // Add bruno.json
        $files->push([
            'path' => FilePath::create($basePath, 'bruno.json'),
            'content' => new FileContent(
                content: $this->serializeCollectionJson($structure),
                type: FileType::BRUNO_COLLECTION,
            ),
        ]);

        // Add environment files
        foreach ($structure->environments->environments as $environment) {
            $files->push([
                'path' => FilePath::create($basePath, "environments/{$environment->name}.bru"),
                'content' => new FileContent(
                    content: $this->serializeEnvironment($environment->name, $environment->variables),
                    type: FileType::BRUNO_ENVIRONMENT,
                ),
            ]);
        }

        // Add root requests
        foreach ($structure->rootRequests as $request) {
            $filename = $this->generateFilename($request);
            $files->push([
                'path' => FilePath::create($basePath, $filename),
                'content' => new FileContent(
                    content: $this->serializeBruRequest($request),
                    type: FileType::BRUNO_REQUEST,
                ),
            ]);
        }

        // Add folders and their requests
        foreach ($structure->folders as $folder) {
            $files = $files->merge($this->serializeFolder($folder, $basePath));
        }

        return $files;
    }

    /**
     * Serialize bruno.json file.
     */
    private function serializeCollectionJson(CollectionStructure $structure): string
    {
        return json_encode($structure->metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * Serialize folder and its contents.
     *
     * @return Collection<int, array{path: FilePath, content: FileContent}>
     */
    private function serializeFolder(FolderNode $folder, string $basePath, string $currentPath = ''): Collection
    {
        $files = collect();
        $folderPath = $currentPath !== '' ? $currentPath.'/'.$folder->name : $folder->name;

        // Serialize requests in this folder
        foreach ($folder->requests as $request) {
            $filename = $this->generateFilename($request);
            $files->push([
                'path' => FilePath::create($basePath, $folderPath.'/'.$filename),
                'content' => new FileContent(
                    content: $this->serializeBruRequest($request),
                    type: FileType::BRUNO_REQUEST,
                ),
            ]);
        }

        // Serialize subfolders recursively
        foreach ($folder->subfolders as $subfolder) {
            $files = $files->merge($this->serializeFolder($subfolder, $basePath, $folderPath));
        }

        return $files;
    }

    /**
     * Generate filename for request.
     */
    private function generateFilename(BrunoRequest $request): string
    {
        $strategy = $this->config['advanced']['file_naming'] ?? 'descriptive';

        if ($strategy === 'sequential') {
            return sprintf('%03d-%s.bru', $request->sequence, $this->sanitizeFilename($request->name));
        }

        // Descriptive strategy (default)
        $method = strtolower($request->method);
        $name = $this->sanitizeFilename($request->name);

        return "{$method}-{$name}.bru";
    }

    /**
     * Sanitize string for use as filename.
     */
    private function sanitizeFilename(string $name): string
    {
        // Convert to lowercase
        $sanitized = strtolower($name);

        // Replace spaces and special characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);

        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');

        // Limit length
        return Str::limit($sanitized, 100, '');
    }

    /**
     * Serialize .bru request file.
     */
    private function serializeBruRequest(BrunoRequest $request): string
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

        if ($body->type === BodyType::JSON && ! empty($body->content)) {
            $json = json_encode($body->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $indented = $this->indentContent($json, 1);

            return <<<BRU
body:json {
{$indented}
}
BRU;
        }

        if ($body->type === BodyType::FORM_URLENCODED && ! empty($body->content)) {
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
     * Serialize environment .bru file.
     *
     * @param  array<string, string>  $vars
     */
    private function serializeEnvironment(string $name, array $vars): string
    {
        $lines = ['vars {'];

        foreach ($vars as $key => $value) {
            $lines[] = "  {$key}: {$value}";
        }

        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }
}
