<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FileWriterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\FileWriteException;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;

final class FileWriterService implements FileWriterInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Write files atomically.
     *
     * @param  Collection<int, array{path: FilePath, content: FileContent}>  $files
     */
    public function write(Collection $files, bool $force): void
    {
        if ($files->isEmpty()) {
            return;
        }

        // Get the base output directory from the first file
        $firstFile = $files->first();
        $outputDir = dirname($firstFile['path']->absolutePath);

        // Validate output directory
        $this->validateOutputDirectory($outputDir, $force);

        // Check disk space
        $requiredSpace = $files->sum(fn ($file) => $file['content']->size());
        $this->ensureSufficientDiskSpace($outputDir, $requiredSpace);

        // Create backup if overwriting
        $backupPath = null;
        if ($force && $this->filesystem->exists($outputDir)) {
            $backupPath = $this->createBackup($outputDir);
        }

        try {
            // Write all files
            foreach ($files as $file) {
                $this->writeFile($file['path'], $file['content']);
            }

            // Remove backup if successful
            if ($backupPath !== null && $this->filesystem->exists($backupPath)) {
                $this->filesystem->deleteDirectory($backupPath);
            }
        } catch (\Throwable $e) {
            // Restore from backup on failure
            if ($backupPath !== null && $this->filesystem->exists($backupPath)) {
                $this->restoreBackup($backupPath, $outputDir);
            }

            throw $e;
        }
    }

    /**
     * Validate output directory.
     */
    private function validateOutputDirectory(string $path, bool $force): void
    {
        // Check if directory exists and has files
        if ($this->filesystem->exists($path)) {
            $files = $this->filesystem->files($path);
            $directories = $this->filesystem->directories($path);

            if ((! empty($files) || ! empty($directories)) && ! $force) {
                throw FileWriteException::failedToWrite(
                    $path,
                    'Directory is not empty. Use --force to overwrite.',
                );
            }
        }

        // Check if parent directory is writable
        $parentDir = dirname($path);
        if ($this->filesystem->exists($parentDir) && ! $this->filesystem->isWritable($parentDir)) {
            throw FileWriteException::permissionDenied($parentDir);
        }
    }

    /**
     * Ensure sufficient disk space is available.
     */
    private function ensureSufficientDiskSpace(string $path, int $required): void
    {
        // Add 10% buffer
        $requiredWithBuffer = (int) ($required * 1.1);

        $available = @disk_free_space(dirname($path));

        if ($available === false) {
            // Cannot determine disk space, proceed with caution
            return;
        }

        if ($available < $requiredWithBuffer) {
            throw FileWriteException::failedToWrite(
                $path,
                sprintf(
                    'Insufficient disk space. Required: %s MB, Available: %s MB',
                    round($requiredWithBuffer / 1024 / 1024, 2),
                    round($available / 1024 / 1024, 2),
                ),
            );
        }
    }

    /**
     * Create backup of existing directory.
     */
    private function createBackup(string $path): string
    {
        if (! $this->filesystem->exists($path)) {
            return '';
        }

        $backupPath = $path.'.backup.'.time();

        try {
            $this->filesystem->copyDirectory($path, $backupPath);

            return $backupPath;
        } catch (\Throwable $e) {
            throw FileWriteException::backupFailed($path);
        }
    }

    /**
     * Restore from backup.
     */
    private function restoreBackup(string $backupPath, string $originalPath): void
    {
        try {
            // Remove failed write
            if ($this->filesystem->exists($originalPath)) {
                $this->filesystem->deleteDirectory($originalPath);
            }

            // Restore backup
            $this->filesystem->moveDirectory($backupPath, $originalPath);
        } catch (\Throwable $e) {
            // Log error but don't throw - backup still exists
        }
    }

    /**
     * Write a single file atomically.
     */
    private function writeFile(FilePath $path, FileContent $content): void
    {
        // Ensure directory exists
        $directory = $path->dirname();
        if (! $this->filesystem->exists($directory)) {
            if (! $this->filesystem->makeDirectory($directory, 0755, true)) {
                throw FileWriteException::failedToCreateDirectory($directory);
            }
        }

        // Write to temporary file first
        $tempPath = $path->absolutePath.'.tmp';

        try {
            if ($this->filesystem->put($tempPath, $content->content) === false) {
                throw FileWriteException::atomicWriteFailed($path->absolutePath);
            }

            // Atomic rename
            if (! rename($tempPath, $path->absolutePath)) {
                throw FileWriteException::atomicWriteFailed($path->absolutePath);
            }
        } catch (\Throwable $e) {
            // Clean up temp file
            if ($this->filesystem->exists($tempPath)) {
                @$this->filesystem->delete($tempPath);
            }

            throw FileWriteException::failedToWrite($path->absolutePath, $e->getMessage());
        }
    }
}
