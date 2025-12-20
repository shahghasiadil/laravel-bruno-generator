<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;

interface FileWriterInterface
{
    /**
     * Write files atomically.
     *
     * @param Collection<int, array{path: FilePath, content: FileContent}> $files
     */
    public function write(Collection $files, bool $force): void;
}
