<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Exceptions;

class FileWriteException extends BrunoGeneratorException
{
    public static function failedToWrite(string $path, string $reason): self
    {
        return new self("Failed to write file {$path}: {$reason}");
    }

    public static function failedToCreateDirectory(string $path): self
    {
        return new self("Failed to create directory: {$path}");
    }

    public static function backupFailed(string $path): self
    {
        return new self("Failed to create backup of: {$path}");
    }

    public static function atomicWriteFailed(string $path): self
    {
        return new self("Atomic write operation failed for: {$path}");
    }

    public static function permissionDenied(string $path): self
    {
        return new self("Permission denied while writing to: {$path}");
    }
}
