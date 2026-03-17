<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\BrunoSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\FolderNode;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\FileType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\OutputFormat;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers\FormatSerializerFactory;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;

final class BrunoSerializerService implements BrunoSerializerInterface
{
    private readonly FormatSerializerInterface $formatSerializer;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        ?FormatSerializerFactory $factory = null,
    ) {
        // Create factory if not provided
        $factory = $factory ?? new FormatSerializerFactory($config);

        // Determine output format from config
        $outputFormat = OutputFormat::fromString(
            $this->config['output_format'] ?? 'bru'
        );

        // Create appropriate serializer
        $this->formatSerializer = $factory->make($outputFormat);
    }

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
            $extension = $this->formatSerializer->getFileExtension();
            $files->push([
                'path' => FilePath::create($basePath, "environments/{$environment->name}{$extension}"),
                'content' => new FileContent(
                    content: $this->formatSerializer->serializeEnvironment($environment->name, $environment->variables),
                    type: FileType::BRUNO_ENVIRONMENT,
                ),
            ]);
        }

        // Add root requests
        foreach ($structure->rootRequests as $request) {
            $filename = $this->generateFilename($request);
            $fileType = $this->formatSerializer->getFileExtension() === '.yaml'
                ? FileType::BRUNO_YAML_REQUEST
                : FileType::BRUNO_REQUEST;

            $files->push([
                'path' => FilePath::create($basePath, $filename),
                'content' => new FileContent(
                    content: $this->formatSerializer->serializeRequest($request),
                    type: $fileType,
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
            $fileType = $this->formatSerializer->getFileExtension() === '.yaml'
                ? FileType::BRUNO_YAML_REQUEST
                : FileType::BRUNO_REQUEST;

            $files->push([
                'path' => FilePath::create($basePath, $folderPath.'/'.$filename),
                'content' => new FileContent(
                    content: $this->formatSerializer->serializeRequest($request),
                    type: $fileType,
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
        $extension = $this->formatSerializer->getFileExtension();

        if ($strategy === 'sequential') {
            return sprintf('%03d-%s%s', $request->sequence, $this->sanitizeFilename($request->name), $extension);
        }

        // Descriptive strategy (default)
        $method = strtolower($request->method);
        $name = $this->sanitizeFilename($request->name);

        return "{$method}-{$name}{$extension}";
    }

    /**
     * Sanitize string for use as filename.
     */
    private function sanitizeFilename(string $name): string
    {
        // Convert to lowercase
        $sanitized = strtolower($name);

        // Replace spaces and special characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized) ?? $sanitized;

        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');

        // Limit length
        return Str::limit($sanitized, 100, '');
    }
}
