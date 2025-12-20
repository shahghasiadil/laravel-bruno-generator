<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleController;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleFormRequest;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->testOutputPath = base_path('test-bruno-' . uniqid());

    // Configure output path
    Config::set('bruno-generator.output_path', 'test-bruno-' . uniqid());
    Config::set('bruno-generator.collection_name', 'Test API');

    // Setup test routes
    Route::middleware(['api'])
        ->prefix('api')
        ->group(function () {
            Route::get('/users', [SampleController::class, 'index'])->name('api.users.index');
            Route::post('/users', [SampleController::class, 'store'])->name('api.users.store');
            Route::get('/users/{id}', [SampleController::class, 'show'])->name('api.users.show');
            Route::put('/users/{id}', [SampleController::class, 'update'])->name('api.users.update');
            Route::delete('/users/{id}', [SampleController::class, 'destroy'])->name('api.users.destroy');
        });
});

afterEach(function () {
    // Clean up test output
    if ($this->filesystem->exists($this->testOutputPath)) {
        $this->filesystem->deleteDirectory($this->testOutputPath);
    }

    // Clean up any generated collections
    $generatedDirs = $this->filesystem->glob(base_path('test-bruno-*'));
    foreach ($generatedDirs as $dir) {
        if ($this->filesystem->isDirectory($dir)) {
            $this->filesystem->deleteDirectory($dir);
        }
    }
});

describe('BrunoGenerateCommand', function () {
    test('generates bruno collection successfully', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        // Verify bruno.json was created
        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        expect($this->filesystem->exists($collectionPath . '/bruno.json'))->toBeTrue();
    });

    test('generates .bru files for each route', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');

        // Should have .bru files
        $bruFiles = $this->filesystem->glob($collectionPath . '/**/*.bru');
        expect(count($bruFiles))->toBeGreaterThan(0);
    });

    test('generates environment files', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');

        expect($this->filesystem->exists($collectionPath . '/environments/Local.bru'))->toBeTrue();
    });

    test('respects --api-only flag', function () {
        // Add web route
        Route::middleware(['web'])
            ->get('/web/dashboard', function () {})
            ->name('web.dashboard');

        $this->artisan('bruno:generate', ['--api-only' => true, '--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $bruFiles = $this->filesystem->glob($collectionPath . '/**/*.bru');

        // Should not include web routes
        $content = '';
        foreach ($bruFiles as $file) {
            $content .= $this->filesystem->get($file);
        }

        expect($content)->not->toContain('/web/dashboard');
    });

    test('respects --prefix filter', function () {
        $this->artisan('bruno:generate', [
            '--prefix' => 'api/users',
            '--force' => true,
        ])->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $bruFiles = $this->filesystem->glob($collectionPath . '/**/*.bru');

        // All routes should be under api/users prefix
        foreach ($bruFiles as $file) {
            $content = $this->filesystem->get($file);
            if (str_contains($content, 'url:')) {
                expect($content)->toContain('/api/users');
            }
        }
    });

    test('respects --group-by option', function () {
        $this->artisan('bruno:generate', [
            '--group-by' => 'controller',
            '--force' => true,
        ])->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');

        // Should have controller-based folders
        expect($this->filesystem->exists($collectionPath . '/SampleController'))->toBeTrue();
    });

    test('handles --dry-run flag', function () {
        $this->artisan('bruno:generate', ['--dry-run' => true])
            ->expectsOutput('🔍 Dry-run mode - No files will be written')
            ->assertSuccessful();

        // Should not create files
        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        expect($this->filesystem->exists($collectionPath))->toBeFalse();
    });

    test('displays progress information', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->expectsOutput('🚀 Generating Bruno API collection...')
            ->assertSuccessful();
    });

    test('displays success message with statistics', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->expectsOutputToContain('✅ Successfully generated Bruno collection!')
            ->assertSuccessful();
    });

    test('fails when no routes match filters', function () {
        // Use a filter that won't match any routes
        $this->artisan('bruno:generate', [
            '--prefix' => 'nonexistent-prefix-that-will-never-match',
        ])->assertFailed();
    });

    test('generates valid bruno.json format', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $brunoJson = json_decode($this->filesystem->get($collectionPath . '/bruno.json'), true);

        expect($brunoJson)->toHaveKeys(['version', 'name', 'type']);
        expect($brunoJson['version'])->toBe('1');
        expect($brunoJson['name'])->toBe('Test API');
        expect($brunoJson['type'])->toBe('collection');
    });

    test('generates valid .bru file format', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $allFiles = $this->filesystem->allFiles($collectionPath);

        // Filter for .bru files that are not in environments folder
        $requestFiles = array_filter($allFiles, function ($file) {
            $normalized = str_replace('\\', '/', $file->getPathname());
            return $file->getExtension() === 'bru' && !str_contains($normalized, '/environments/');
        });

        expect($requestFiles)->not->toBeEmpty();

        $firstFileObj = reset($requestFiles);
        $firstFile = $this->filesystem->get($firstFileObj->getPathname());

        // Should contain required blocks
        expect($firstFile)->toContain('meta {');
        expect($firstFile)->toMatch('/(get|post|put|delete|patch) \{/');
    });

    test('handles route parameters correctly', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $allFiles = $this->filesystem->allFiles($collectionPath);

        // Filter for .bru files that are not in environments folder
        $requestFiles = array_filter($allFiles, function ($file) {
            $normalized = str_replace('\\', '/', $file->getPathname());
            return $file->getExtension() === 'bru' && !str_contains($normalized, '/environments/');
        });

        expect($requestFiles)->not->toBeEmpty();

        $foundParameterizedRoute = false;
        foreach ($requestFiles as $fileObj) {
            $content = $this->filesystem->get($fileObj->getPathname());
            // Check for parameter in URL - could be in path params or directly in URL
            if (str_contains($content, '{{id}}') || str_contains($content, 'id:')) {
                $foundParameterizedRoute = true;
                break;
            }
        }

        expect($foundParameterizedRoute)->toBeTrue();
    });

    test('generates environment with correct variables', function () {
        $this->artisan('bruno:generate', ['--force' => true])
            ->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $envContent = $this->filesystem->get($collectionPath . '/environments/Local.bru');

        expect($envContent)->toContain('vars {');
        expect($envContent)->toContain('baseUrl:');
    });

    test('respects --name option', function () {
        $this->artisan('bruno:generate', [
            '--name' => 'Custom API',
            '--force' => true,
        ])->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Custom-API');
        expect($this->filesystem->exists($collectionPath))->toBeTrue();

        $brunoJson = json_decode($this->filesystem->get($collectionPath . '/bruno.json'), true);
        expect($brunoJson['name'])->toBe('Custom API');
    });

    test('handles --exclude-prefix option', function () {
        // Add route that should be excluded
        Route::middleware(['api'])
            ->get('/telescope/requests', function () {})
            ->name('telescope.requests');

        $this->artisan('bruno:generate', [
            '--exclude-prefix' => 'telescope',
            '--force' => true,
        ])->assertSuccessful();

        $collectionPath = base_path(Config::get('bruno-generator.output_path') . '/Test-API');
        $bruFiles = $this->filesystem->glob($collectionPath . '/**/*.bru');

        $content = '';
        foreach ($bruFiles as $file) {
            $content .= $this->filesystem->get($file);
        }

        expect($content)->not->toContain('/telescope/requests');
    });

    test('generates with custom output path', function () {
        $customPath = 'custom-output-' . uniqid();

        $this->artisan('bruno:generate', [
            '--output' => $customPath,
            '--force' => true,
        ])->assertSuccessful();

        $collectionPath = base_path($customPath . '/Test-API');
        expect($this->filesystem->exists($collectionPath))->toBeTrue();

        // Cleanup
        if ($this->filesystem->exists(base_path($customPath))) {
            $this->filesystem->deleteDirectory(base_path($customPath));
        }
    });
});
