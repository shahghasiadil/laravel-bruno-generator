<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\GroupStrategy;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\CollectionOrganizerService;

beforeEach(function () {
    $this->config = [
        'collection_name' => 'Test API',
        'bruno_version' => '1',
        'organization' => [
            'group_by' => 'prefix',
            'folder_depth' => 2,
            'sort_by' => 'uri',
            'sort_direction' => 'asc',
            'sequence_increment' => 1,
        ],
        'environments' => [
            'Local' => ['baseUrl' => 'http://localhost'],
        ],
    ];

    $this->service = new CollectionOrganizerService($this->config);

    $this->sampleRequests = collect([
        new BrunoRequest(
            name: 'Get Users',
            description: 'Test',
            sequence: 1,
            method: 'GET',
            url: '{{baseUrl}}/api/users',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: 'api/users',
            controller: 'UserController',
            tags: ['users'],
        ),
        new BrunoRequest(
            name: 'Get Posts',
            description: 'Test',
            sequence: 2,
            method: 'GET',
            url: '{{baseUrl}}/api/posts',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: 'api/posts',
            controller: 'PostController',
            tags: ['posts'],
        ),
        new BrunoRequest(
            name: 'Get Health',
            description: 'Test',
            sequence: 3,
            method: 'GET',
            url: '{{baseUrl}}/health',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: null,
            controller: null,
            tags: [],
        ),
    ]);
});

describe('CollectionOrganizerService', function () {
    test('organizes requests by prefix', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::PREFIX);

        expect($structure->folders)->not->toBeEmpty();
        expect($structure->totalRequests())->toBe(3);
    });

    test('places ungrouped requests in root', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::PREFIX);

        expect($structure->rootRequests)->toHaveCount(1);
        expect($structure->rootRequests->first()->name)->toBe('Get Health');
    });

    test('creates folder hierarchy from prefix', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::PREFIX);

        // Should have api folder
        expect($structure->folders->count())->toBeGreaterThan(0);
    });

    test('organizes requests by controller', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::CONTROLLER);

        expect($structure->folders)->not->toBeEmpty();

        $controllerNames = $structure->folders->pluck('name')->all();
        expect($controllerNames)->toContain('UserController');
        expect($controllerNames)->toContain('PostController');
    });

    test('organizes requests by tag', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::TAG);

        expect($structure->folders)->not->toBeEmpty();

        $tagNames = $structure->folders->pluck('name')->all();
        expect($tagNames)->toContain('users');
        expect($tagNames)->toContain('posts');
    });

    test('handles none grouping strategy', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::NONE);

        expect($structure->folders)->toBeEmpty();
        expect($structure->rootRequests)->toHaveCount(3);
    });

    test('sorts requests by URI', function () {
        $config = array_merge($this->config, [
            'organization' => [
                'sort_by' => 'uri',
                'sort_direction' => 'asc',
            ],
        ]);

        $service = new CollectionOrganizerService($config);
        $structure = $service->organize($this->sampleRequests, GroupStrategy::NONE);

        $urls = $structure->rootRequests->pluck('url')->all();
        expect($urls[0])->toContain('/api/posts');
        expect($urls[1])->toContain('/api/users');
        expect($urls[2])->toContain('/health');
    });

    test('sorts requests in descending order', function () {
        $config = array_merge($this->config, [
            'organization' => [
                'sort_by' => 'uri',
                'sort_direction' => 'desc',
            ],
        ]);

        $service = new CollectionOrganizerService($config);
        $structure = $service->organize($this->sampleRequests, GroupStrategy::NONE);

        $urls = $structure->rootRequests->pluck('url')->all();
        expect($urls[0])->toContain('/health');
        expect($urls[1])->toContain('/api/users');
        expect($urls[2])->toContain('/api/posts');
    });

    test('reassigns sequence numbers', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::NONE);

        $sequences = $structure->rootRequests->pluck('sequence')->all();
        expect($sequences)->toBe([1, 2, 3]);
    });

    test('respects sequence increment', function () {
        $config = array_merge($this->config, [
            'organization' => [
                'sort_by' => 'uri',
                'sort_direction' => 'asc',
                'sequence_increment' => 10,
            ],
        ]);

        $service = new CollectionOrganizerService($config);
        $structure = $service->organize($this->sampleRequests, GroupStrategy::NONE);

        $sequences = $structure->rootRequests->pluck('sequence')->all();
        expect($sequences)->toBe([10, 20, 30]);
    });

    test('creates collection metadata', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::NONE);

        expect($structure->metadata->name)->toBe('Test API');
        expect($structure->metadata->version)->toBe('1');
        expect($structure->metadata->type)->toBe('collection');
    });

    test('creates environment collection', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::NONE);

        expect($structure->environments->hasEnvironments())->toBeTrue();
        expect($structure->environments->environments->first()->name)->toBe('Local');
    });

    test('counts total requests correctly', function () {
        $structure = $this->service->organize($this->sampleRequests, GroupStrategy::PREFIX);

        expect($structure->totalRequests())->toBe(3);
    });

    test('handles empty request collection', function () {
        $structure = $this->service->organize(collect(), GroupStrategy::NONE);

        expect($structure->rootRequests)->toBeEmpty();
        expect($structure->folders)->toBeEmpty();
        expect($structure->totalRequests())->toBe(0);
    });

    test('groups requests with similar prefixes together', function () {
        $requests = collect([
            new BrunoRequest(
                name: 'Get Users',
                description: 'Test',
                sequence: 1,
                method: 'GET',
                url: '{{baseUrl}}/api/v1/users',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: 'api/v1',
                controller: 'UserController',
                tags: [],
            ),
            new BrunoRequest(
                name: 'Get Posts',
                description: 'Test',
                sequence: 2,
                method: 'GET',
                url: '{{baseUrl}}/api/v1/posts',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: 'api/v1',
                controller: 'PostController',
                tags: [],
            ),
        ]);

        $structure = $this->service->organize($requests, GroupStrategy::PREFIX);

        // Both should be in the same folder group
        expect($structure->folders->count())->toBeGreaterThan(0);
    });

    test('sorts requests by method', function () {
        $requests = collect([
            new BrunoRequest(
                name: 'Create User',
                description: 'Test',
                sequence: 1,
                method: 'POST',
                url: '{{baseUrl}}/api/users',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: null,
                controller: null,
                tags: [],
            ),
            new BrunoRequest(
                name: 'Get Users',
                description: 'Test',
                sequence: 2,
                method: 'GET',
                url: '{{baseUrl}}/api/users',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: null,
                controller: null,
                tags: [],
            ),
            new BrunoRequest(
                name: 'Delete User',
                description: 'Test',
                sequence: 3,
                method: 'DELETE',
                url: '{{baseUrl}}/api/users',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: null,
                controller: null,
                tags: [],
            ),
        ]);

        $config = array_merge($this->config, [
            'organization' => [
                'sort_by' => 'method',
                'sort_direction' => 'asc',
            ],
        ]);

        $service = new CollectionOrganizerService($config);
        $structure = $service->organize($requests, GroupStrategy::NONE);

        $methods = $structure->rootRequests->pluck('method')->all();
        expect($methods)->toBe(['DELETE', 'GET', 'POST']);
    });

    test('sorts requests by name', function () {
        $requests = collect([
            new BrunoRequest(
                name: 'Zebra',
                description: 'Test',
                sequence: 1,
                method: 'GET',
                url: '{{baseUrl}}/api/zebra',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: null,
                controller: null,
                tags: [],
            ),
            new BrunoRequest(
                name: 'Apple',
                description: 'Test',
                sequence: 2,
                method: 'GET',
                url: '{{baseUrl}}/api/apple',
                headers: [],
                queryParams: [],
                pathVariables: [],
                body: null,
                auth: null,
                group: null,
                controller: null,
                tags: [],
            ),
        ]);

        $config = array_merge($this->config, [
            'organization' => [
                'sort_by' => 'name',
                'sort_direction' => 'asc',
            ],
        ]);

        $service = new CollectionOrganizerService($config);
        $structure = $service->organize($requests, GroupStrategy::NONE);

        $names = $structure->rootRequests->pluck('name')->all();
        expect($names)->toBe(['Apple', 'Zebra']);
    });
});
