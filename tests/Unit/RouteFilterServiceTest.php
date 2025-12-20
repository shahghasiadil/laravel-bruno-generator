<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteFilterService;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilterCriteria;

beforeEach(function () {
    $this->service = new RouteFilterService();

    $this->sampleRoutes = collect([
        new RouteInfo(
            uri: 'api/users',
            methods: ['GET'],
            name: 'api.users.index',
            action: 'UserController@index',
            middleware: ['api', 'auth:sanctum'],
            domain: null,
            parameters: [],
            controller: 'UserController',
            controllerMethod: 'index',
            isFallback: false,
        ),
        new RouteInfo(
            uri: 'web/dashboard',
            methods: ['GET'],
            name: 'dashboard',
            action: 'DashboardController@index',
            middleware: ['web'],
            domain: null,
            parameters: [],
            controller: 'DashboardController',
            controllerMethod: 'index',
            isFallback: false,
        ),
        new RouteInfo(
            uri: 'telescope/requests',
            methods: ['GET'],
            name: 'telescope.requests',
            action: 'TelescopeController@index',
            middleware: ['web'],
            domain: null,
            parameters: [],
            controller: 'TelescopeController',
            controllerMethod: 'index',
            isFallback: false,
        ),
        new RouteInfo(
            uri: 'api/v1/posts',
            methods: ['GET'],
            name: 'api.posts.index',
            action: 'PostController@index',
            middleware: ['api'],
            domain: null,
            parameters: [],
            controller: 'PostController',
            controllerMethod: 'index',
            isFallback: false,
        ),
    ]);
});

describe('RouteFilterService', function () {
    test('filters routes by include middleware', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: ['api'],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(2);
        expect($filtered->pluck('uri')->all())->toBe(['api/users', 'api/v1/posts']);
    });

    test('filters routes by exclude middleware', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: ['web'],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(2);
        expect($filtered->pluck('uri')->all())->toBe(['api/users', 'api/v1/posts']);
    });

    test('filters routes by include prefix', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: ['api/v1'],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(1);
        expect($filtered->first()->uri)->toBe('api/v1/posts');
    });

    test('filters routes by exclude prefix', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: ['telescope'],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(3);
        expect($filtered->pluck('uri')->contains('telescope/requests'))->toBeFalse();
    });

    test('filters routes by include name pattern', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: ['/^api\./'],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(2);
        expect($filtered->pluck('name')->all())->toBe(['api.users.index', 'api.posts.index']);
    });

    test('filters routes by exclude name pattern', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: ['/^telescope\./'],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(3);
        expect($filtered->pluck('name')->contains('telescope.requests'))->toBeFalse();
    });

    test('auto-detects API routes', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: true,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(2);
        expect($filtered->every(fn ($route) => in_array('api', $route->middleware)))->toBeTrue();
    });

    test('excludes fallback routes when configured', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
            new RouteInfo(
                uri: '{fallbackPlaceholder}',
                methods: ['GET'],
                name: null,
                action: 'Closure',
                middleware: [],
                domain: null,
                parameters: [],
                controller: null,
                controllerMethod: null,
                isFallback: true,
            ),
        ]);

        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: true,
        );

        $filtered = $this->service->filter($routes, $criteria);

        expect($filtered)->toHaveCount(1);
        expect($filtered->first()->isFallback)->toBeFalse();
    });

    test('combines multiple filter criteria', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: ['api'],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: ['telescope'],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toHaveCount(2);
        expect($filtered->pluck('uri')->all())->toBe(['api/users', 'api/v1/posts']);
    });

    test('returns empty collection when no routes match', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: ['nonexistent'],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        expect($filtered)->toBeEmpty();
    });

    test('filters by domain', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api'],
                domain: 'api.example.com',
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
            new RouteInfo(
                uri: 'admin/users',
                methods: ['GET'],
                name: 'admin.users.index',
                action: 'AdminController@index',
                middleware: ['web'],
                domain: 'admin.example.com',
                parameters: [],
                controller: 'AdminController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: [],
            excludeNames: [],
            includeDomains: ['api.example.com'],
            excludeFallback: false,
        );

        $filtered = $this->service->filter($routes, $criteria);

        expect($filtered)->toHaveCount(1);
        expect($filtered->first()->domain)->toBe('api.example.com');
    });

    test('handles invalid regex patterns gracefully', function () {
        $criteria = new FilterCriteria(
            autoDetectApi: false,
            includeMiddleware: [],
            excludeMiddleware: [],
            includePrefixes: [],
            excludePrefixes: [],
            includeNames: ['/invalid[regex/'], // Invalid regex
            excludeNames: [],
            includeDomains: null,
            excludeFallback: false,
        );

        $filtered = $this->service->filter($this->sampleRoutes, $criteria);

        // Should not throw exception, just skip invalid pattern
        expect($filtered)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});
