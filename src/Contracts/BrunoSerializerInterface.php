<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;

interface BrunoSerializerInterface
{
    /**
     * Serialize collection structure to file map.
     *
     * @return Collection<int, array{path: FilePath, content: FileContent}>
     */
    public function serialize(CollectionStructure $structure, string $basePath): Collection;
}
