<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use Illuminate\Support\Collection;

final readonly class FolderNode
{
    /**
     * @param Collection<int, BrunoRequest> $requests
     * @param Collection<int, FolderNode> $subfolders
     */
    public function __construct(
        public string $name,
        public string $path,
        public Collection $requests,
        public Collection $subfolders,
    ) {}

    public static function create(string $name, string $path): self
    {
        return new self(
            name: $name,
            path: $path,
            requests: collect(),
            subfolders: collect(),
        );
    }

    public function hasRequests(): bool
    {
        return $this->requests->isNotEmpty();
    }

    public function hasSubfolders(): bool
    {
        return $this->subfolders->isNotEmpty();
    }

    public function isEmpty(): bool
    {
        return $this->requests->isEmpty() && $this->subfolders->isEmpty();
    }
}
