<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteDiscoveryService;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleController;

beforeEach(function () {
    // Clear all routes before each test
    $router = app('router');

    // Get the routes collection and clear it
    $routes = $router->getRoutes();

    // Clear routes using reflection to access protected property
    $reflection = new ReflectionClass($routes);
    $property = $reflection->getProperty('routes');
    $property->setAccessible(true);
    $property->setValue($routes, []);

    $property = $reflection->getProperty('allRoutes');
    $property->setAccessible(true);
    $property->setValue($routes, []);

    $property = $reflection->getProperty('nameList');
    $property->setAccessible(true);
    $property->setValue($routes, []);

    $property = $reflection->getProperty('actionList');
    $property->setAccessible(true);
    $property->setValue($routes, []);
});

describe('RouteDiscoveryService', function () {
    test('discovers basic routes', function () {
        Route::get('/api/users', [SampleController::class, 'index'])->name('users.index');

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->uri)->toBe('api/users');
        expect($routes->first()->methods)->toContain('GET');
        expect($routes->first()->name)->toBe('users.index');
    });

    test('discovers routes with parameters', function () {
        Route::get('/api/users/{id}', [SampleController::class, 'show'])->name('users.show');

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->uri)->toBe('api/users/{id}');
        expect($routes->first()->parameters)->toHaveKey('id');
    });

    test('discovers routes with multiple parameters', function () {
        Route::get('/api/posts/{post}/comments/{comment}', function () {})
            ->name('posts.comments.show');

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->parameters)->toHaveKeys(['post', 'comment']);
    });

    test('discovers routes with optional parameters', function () {
        Route::get('/api/users/{id?}', function () {})->name('users.optional');

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->parameters)->toHaveKey('id');
    });

    test('extracts controller and method information', function () {
        Route::get('/api/users', [SampleController::class, 'index']);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes->first()->controller)->toBe('SampleController');
        expect($routes->first()->controllerMethod)->toBe('index');
    });

    test('handles closure routes', function () {
        Route::get('/api/test', function () {
            return response()->json(['test' => true]);
        });

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->controller)->toBeNull();
        expect($routes->first()->controllerMethod)->toBeNull();
    });

    test('extracts middleware information', function () {
        Route::middleware(['api', 'auth:sanctum'])
            ->get('/api/users', [SampleController::class, 'index']);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes->first()->middleware)->toContain('api');
        expect($routes->first()->middleware)->toContain('auth:sanctum');
    });

    test('discovers routes with multiple HTTP methods', function () {
        Route::match(['GET', 'POST'], '/api/users', [SampleController::class, 'index']);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->methods)->toContain('GET');
        expect($routes->first()->methods)->toContain('POST');
    });

    test('handles domain-specific routes', function () {
        Route::domain('api.example.com')
            ->get('/users', [SampleController::class, 'index']);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes->first()->domain)->toBe('api.example.com');
    });

    test('identifies fallback routes', function () {
        Route::fallback(function () {
            return response()->json(['error' => 'Not found'], 404);
        });

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes->first()->isFallback)->toBeTrue();
    });

    test('discovers multiple routes', function () {
        Route::get('/api/users', [SampleController::class, 'index']);
        Route::post('/api/users', [SampleController::class, 'store']);
        Route::get('/api/users/{id}', [SampleController::class, 'show']);
        Route::put('/api/users/{id}', [SampleController::class, 'update']);
        Route::delete('/api/users/{id}', [SampleController::class, 'destroy']);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes)->toHaveCount(5);
    });

    test('handles resource routes', function () {
        Route::apiResource('users', SampleController::class);

        $service = app(RouteDiscoveryService::class);
        $routes = $service->discover();

        expect($routes->count())->toBeGreaterThan(0);
        expect($routes->pluck('name')->filter())->toContain('users.index');
    });
});
