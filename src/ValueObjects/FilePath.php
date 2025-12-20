<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects;

final readonly class FilePath
{
    public function __construct(
        public string $absolutePath,
        public string $relativePath,
    ) {}

    public static function create(string $basePath, string $relativePath): self
    {
        $normalizedRelative = str_replace('\\', '/', $relativePath);
        $normalizedBase = str_replace('\\', '/', $basePath);

        return new self(
            absolutePath: rtrim($normalizedBase, '/') . '/' . ltrim($normalizedRelative, '/'),
            relativePath: $normalizedRelative,
        );
    }

    public function dirname(): string
    {
        return dirname($this->absolutePath);
    }

    public function basename(): string
    {
        return basename($this->absolutePath);
    }
}
