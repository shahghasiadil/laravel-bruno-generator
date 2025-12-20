<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects;

use ShahGhasiAdil\LaravelBrunoGenerator\Enums\FileType;

final readonly class FileContent
{
    public function __construct(
        public string $content,
        public FileType $type,
    ) {}

    public function size(): int
    {
        return strlen($this->content);
    }

    public function isEmpty(): bool
    {
        return empty(trim($this->content));
    }
}
