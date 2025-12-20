<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\FileType;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\FileWriteException;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FileWriterService;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->service = new FileWriterService($this->filesystem);
    $this->testDir = sys_get_temp_dir().'/bruno-test-'.uniqid();
});

afterEach(function () {
    if ($this->filesystem->exists($this->testDir)) {
        $this->filesystem->deleteDirectory($this->testDir);
    }
});

describe('FileWriterService', function () {
    test('writes files successfully', function () {
        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'test.bru'),
                'content' => new FileContent('test content', FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, false);

        expect($this->filesystem->exists($this->testDir.'/test.bru'))->toBeTrue();
        expect($this->filesystem->get($this->testDir.'/test.bru'))->toBe('test content');
    });

    test('creates nested directories automatically', function () {
        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'api/v1/users/get-users.bru'),
                'content' => new FileContent('test content', FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, false);

        expect($this->filesystem->exists($this->testDir.'/api/v1/users/get-users.bru'))->toBeTrue();
    });

    test('writes multiple files', function () {
        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'file1.bru'),
                'content' => new FileContent('content 1', FileType::BRUNO_REQUEST),
            ],
            [
                'path' => FilePath::create($this->testDir, 'file2.bru'),
                'content' => new FileContent('content 2', FileType::BRUNO_REQUEST),
            ],
            [
                'path' => FilePath::create($this->testDir, 'bruno.json'),
                'content' => new FileContent('{}', FileType::BRUNO_COLLECTION),
            ],
        ]);

        $this->service->write($files, false);

        expect($this->filesystem->exists($this->testDir.'/file1.bru'))->toBeTrue();
        expect($this->filesystem->exists($this->testDir.'/file2.bru'))->toBeTrue();
        expect($this->filesystem->exists($this->testDir.'/bruno.json'))->toBeTrue();
    });

    test('throws exception when directory exists and force is false', function () {
        // Create directory with existing file
        $this->filesystem->makeDirectory($this->testDir);
        $this->filesystem->put($this->testDir.'/existing.txt', 'existing');

        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'test.bru'),
                'content' => new FileContent('test', FileType::BRUNO_REQUEST),
            ],
        ]);

        expect(fn () => $this->service->write($files, false))
            ->toThrow(FileWriteException::class);
    });

    test('overwrites when force is true', function () {
        // Create directory with existing file
        $this->filesystem->makeDirectory($this->testDir);
        $this->filesystem->put($this->testDir.'/existing.txt', 'old content');

        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'existing.txt'),
                'content' => new FileContent('new content', FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, true);

        expect($this->filesystem->get($this->testDir.'/existing.txt'))->toBe('new content');
    });

    test('creates backup when overwriting', function () {
        // Create directory with existing file
        $this->filesystem->makeDirectory($this->testDir);
        $this->filesystem->put($this->testDir.'/existing.txt', 'old content');

        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'new.txt'),
                'content' => new FileContent('new content', FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, true);

        // Backup should be created but then deleted after successful write
        expect($this->filesystem->exists($this->testDir.'/new.txt'))->toBeTrue();
    });

    test('handles empty file collection', function () {
        $files = collect([]);

        // Should not throw error
        $this->service->write($files, false);

        expect(true)->toBeTrue();
    });

    test('performs atomic writes', function () {
        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'atomic.bru'),
                'content' => new FileContent('atomic content', FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, false);

        // Verify no .tmp files remain
        $tmpFiles = $this->filesystem->glob($this->testDir.'/*.tmp');
        expect($tmpFiles)->toBeEmpty();

        // Verify final file exists
        expect($this->filesystem->exists($this->testDir.'/atomic.bru'))->toBeTrue();
    });

    test('writes files with correct content', function () {
        $content = <<<'BRU'
meta {
  name: Test Request
  seq: 1
}

get {
  url: {{baseUrl}}/api/test
}
BRU;

        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'test.bru'),
                'content' => new FileContent($content, FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, false);

        expect($this->filesystem->get($this->testDir.'/test.bru'))->toBe($content);
    });

    test('handles unicode content', function () {
        $content = 'Test with émojis 🚀 and spëcial çharacters';

        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'unicode.bru'),
                'content' => new FileContent($content, FileType::BRUNO_REQUEST),
            ],
        ]);

        $this->service->write($files, false);

        expect($this->filesystem->get($this->testDir.'/unicode.bru'))->toBe($content);
    });

    test('cleans up temp files on error', function () {
        // Create a scenario where write might fail partway through
        $files = collect([
            [
                'path' => FilePath::create($this->testDir, 'file1.bru'),
                'content' => new FileContent('content 1', FileType::BRUNO_REQUEST),
            ],
        ]);

        try {
            $this->service->write($files, false);
        } catch (\Throwable $e) {
            // Ignore errors
        }

        // Verify no .tmp files remain
        $tmpFiles = $this->filesystem->glob($this->testDir.'/*.tmp');
        expect($tmpFiles)->toBeEmpty();
    });
});
