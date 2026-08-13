<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleController;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $outputPath = 'test-bruno-check-'.uniqid();

    Config::set('bruno-generator.output_path', $outputPath);
    Config::set('bruno-generator.collection_name', 'Test API');
    Config::set('bruno-generator.output_format', 'bru');

    $this->collectionPath = base_path($outputPath.'/Test-API');

    Route::middleware(['api'])
        ->prefix('api')
        ->group(function () {
            Route::get('/users', [SampleController::class, 'index'])->name('api.users.index');
            Route::post('/users', [SampleController::class, 'store'])->name('api.users.store');
            Route::get('/users/{id}', [SampleController::class, 'show'])->name('api.users.show');
        });
});

afterEach(function () {
    $generatedDirs = $this->filesystem->glob(base_path('test-bruno-check-*'));
    foreach ($generatedDirs as $dir) {
        if ($this->filesystem->isDirectory($dir)) {
            $this->filesystem->deleteDirectory($dir);
        }
    }
});

describe('BrunoCheckCommand', function () {
    test('fails when the collection has never been generated', function () {
        $this->artisan('bruno:check')
            ->assertFailed();
    });

    test('succeeds when the collection is up to date', function () {
        $this->artisan('bruno:generate', ['--force' => true])->assertSuccessful();

        $this->artisan('bruno:check')
            ->expectsOutputToContain('No drift detected')
            ->assertSuccessful();
    });

    test('fails when a generated file has been hand-edited', function () {
        $this->artisan('bruno:generate', ['--force' => true])->assertSuccessful();

        $envFile = $this->collectionPath.'/environments/Local.bru';
        $this->filesystem->put($envFile, $this->filesystem->get($envFile)."\n# hand edit\n");

        $this->artisan('bruno:check')->assertFailed();
    });

    test('fails when a route is added after generation', function () {
        $this->artisan('bruno:generate', ['--force' => true])->assertSuccessful();

        Route::middleware(['api'])->prefix('api')->group(function () {
            Route::get('/posts', [SampleController::class, 'index'])->name('api.posts.index');
        });

        $this->artisan('bruno:check')->assertFailed();
    });

    test('fails when an orphaned file is left on disk', function () {
        $this->artisan('bruno:generate', ['--force' => true])->assertSuccessful();

        $this->filesystem->put($this->collectionPath.'/stale-request.bru', 'meta { name: Stale }');

        $this->artisan('bruno:check')->assertFailed();
    });

    test('lists drifted files in verbose mode', function () {
        $this->artisan('bruno:generate', ['--force' => true])->assertSuccessful();

        $this->filesystem->put($this->collectionPath.'/stale-request.bru', 'meta { name: Stale }');

        $this->artisan('bruno:check', ['--verbose' => true])
            ->expectsOutputToContain('stale-request.bru')
            ->assertFailed();
    });
});
