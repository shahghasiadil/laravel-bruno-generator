<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Exceptions;

use RuntimeException;

class BrunoGeneratorException extends RuntimeException
{
    public static function noRoutesFound(): self
    {
        return new self('No routes found matching the specified criteria.');
    }

    public static function outputPathNotWritable(string $path): self
    {
        return new self("Output path is not writable: {$path}");
    }

    public static function insufficientDiskSpace(int $required, int $available): self
    {
        $requiredMb = round($required / 1024 / 1024, 2);
        $availableMb = round($available / 1024 / 1024, 2);

        return new self("Insufficient disk space. Required: {$requiredMb}MB, Available: {$availableMb}MB");
    }

    public static function invalidGroupStrategy(string $strategy): self
    {
        return new self("Invalid group strategy: {$strategy}. Valid options are: prefix, controller, tag, none");
    }

    public static function collectionAlreadyExists(string $path): self
    {
        return new self("Collection already exists at: {$path}. Use --force to overwrite.");
    }

    public static function invalidConfiguration(string $key, string $reason): self
    {
        return new self("Invalid configuration for '{$key}': {$reason}");
    }
}
