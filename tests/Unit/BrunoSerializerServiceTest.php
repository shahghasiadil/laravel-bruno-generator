<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionMetadata;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentCollection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentConfig;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\FolderNode;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\FileType;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\BrunoSerializerService;

beforeEach(function () {
    $this->service = new BrunoSerializerService([
        'advanced' => [
            'file_naming' => 'descriptive',
        ],
    ]);

    $this->basePath = sys_get_temp_dir().'/test-bruno';
});

describe('BrunoSerializerService', function () {
    test('serializes collection structure', function () {
        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect(), $environments);

        $files = $this->service->serialize($structure, $this->basePath);

        expect($files)->not->toBeEmpty();
        expect($files->first())->toHaveKeys(['path', 'content']);
    });

    test('generates bruno.json file', function () {
        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect(), $environments);

        $files = $this->service->serialize($structure, $this->basePath);

        $brunoJsonFile = $files->firstWhere(fn ($file) => str_ends_with($file['path']->relativePath, 'bruno.json'));

        expect($brunoJsonFile)->not->toBeNull();
        expect($brunoJsonFile['content']->type)->toBe(FileType::BRUNO_COLLECTION);
        expect($brunoJsonFile['content']->content)->toContain('"name": "Test API"');
        expect($brunoJsonFile['content']->content)->toContain('"version": "1"');
    });

    test('generates environment files', function () {
        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect([
            'Local' => new EnvironmentConfig('Local', ['baseUrl' => 'http://localhost']),
            'Production' => new EnvironmentConfig('Production', ['baseUrl' => 'https://api.example.com']),
        ]));
        $structure = new CollectionStructure($metadata, collect(), collect(), $environments);

        $files = $this->service->serialize($structure, $this->basePath);

        $envFiles = $files->filter(fn ($file) => str_contains($file['path']->relativePath, 'environments/'));

        expect($envFiles)->toHaveCount(2);
        expect($envFiles->pluck('path')->map->basename()->all())->toContain('Local.bru', 'Production.bru');
    });

    test('serializes basic .bru request file', function () {
        $request = new BrunoRequest(
            name: 'Get Users',
            description: 'Retrieve all users',
            sequence: 1,
            method: 'GET',
            url: '{{baseUrl}}/api/users',
            headers: ['Accept' => 'application/json'],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: null,
            controller: 'UserController',
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);

        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile)->not->toBeNull();
        expect($bruFile['content']->content)->toContain('meta {');
        expect($bruFile['content']->content)->toContain('name: Get Users');
        expect($bruFile['content']->content)->toContain('seq: 1');
        expect($bruFile['content']->content)->toContain('get {');
        expect($bruFile['content']->content)->toContain('url: {{baseUrl}}/api/users');
    });

    test('includes headers block when present', function () {
        $request = new BrunoRequest(
            name: 'Get Users',
            description: 'Test',
            sequence: 1,
            method: 'GET',
            url: '{{baseUrl}}/api/users',
            headers: ['Accept' => 'application/json', 'Authorization' => 'Bearer {{token}}'],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: null,
            controller: null,
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('headers {');
        expect($bruFile['content']->content)->toContain('Accept: application/json');
        expect($bruFile['content']->content)->toContain('Authorization: Bearer {{token}}');
    });

    test('includes JSON body block when present', function () {
        $body = new RequestBody(
            type: BodyType::JSON,
            content: ['name' => 'John Doe', 'email' => 'john@example.com'],
            raw: null,
        );

        $request = new BrunoRequest(
            name: 'Create User',
            description: 'Test',
            sequence: 1,
            method: 'POST',
            url: '{{baseUrl}}/api/users',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: $body,
            auth: null,
            group: null,
            controller: null,
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('body:json {');
        expect($bruFile['content']->content)->toContain('"name": "John Doe"');
        expect($bruFile['content']->content)->toContain('"email": "john@example.com"');
    });

    test('includes auth block when present', function () {
        $auth = new AuthBlock(AuthType::BEARER, ['token' => '{{authToken}}']);

        $request = new BrunoRequest(
            name: 'Get Users',
            description: 'Test',
            sequence: 1,
            method: 'GET',
            url: '{{baseUrl}}/api/users',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: $auth,
            group: null,
            controller: null,
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('auth:bearer {');
        expect($bruFile['content']->content)->toContain('token: {{authToken}}');
    });

    test('includes query params block when present', function () {
        $request = new BrunoRequest(
            name: 'Get Users',
            description: 'Test',
            sequence: 1,
            method: 'GET',
            url: '{{baseUrl}}/api/users',
            headers: [],
            queryParams: ['page' => '1', 'limit' => '10'],
            pathVariables: [],
            body: null,
            auth: null,
            group: null,
            controller: null,
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('params:query {');
        expect($bruFile['content']->content)->toContain('page: 1');
        expect($bruFile['content']->content)->toContain('limit: 10');
    });

    test('generates descriptive filenames by default', function () {
        $request = new BrunoRequest(
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
            group: null,
            controller: null,
            tags: [],
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['path']->basename())->toBe('get-get-users.bru');
    });

    test('generates sequential filenames when configured', function () {
        $service = new BrunoSerializerService([
            'advanced' => [
                'file_naming' => 'sequential',
            ],
        ]);

        $request = new BrunoRequest(
            name: 'Get Users',
            description: 'Test',
            sequence: 5,
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
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['path']->basename())->toBe('005-get-users.bru');
    });

    test('organizes requests into folders', function () {
        $request = new BrunoRequest(
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
            group: 'api/v1',
            controller: null,
            tags: [],
        );

        $folder = new FolderNode('api', 'api', collect([$request]), collect());
        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect([$folder]), collect(), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['path']->relativePath)->toContain('api/');
    });

    test('includes tests block when present', function () {
        $request = new BrunoRequest(
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
            group: null,
            controller: null,
            tags: [],
            tests: 'test("should return 200", function() { expect(res.status).to.equal(200); });',
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('tests {');
        expect($bruFile['content']->content)->toContain('should return 200');
    });

    test('includes docs block when present', function () {
        $request = new BrunoRequest(
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
            group: null,
            controller: null,
            tags: [],
            docs: 'This endpoint retrieves all users.',
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        expect($bruFile['content']->content)->toContain('docs {');
        expect($bruFile['content']->content)->toContain('This endpoint retrieves all users.');
    });

    test('sanitizes special characters in filenames', function () {
        $request = new BrunoRequest(
            name: 'Get User@#$ Profile!',
            description: 'Test',
            sequence: 1,
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
        );

        $metadata = new CollectionMetadata('Test API', '1');
        $environments = new EnvironmentCollection(collect());
        $structure = new CollectionStructure($metadata, collect(), collect([$request]), $environments);

        $files = $this->service->serialize($structure, $this->basePath);
        $bruFile = $files->first(fn ($file) => $file['content']->type === FileType::BRUNO_REQUEST);

        // Should contain only safe characters
        expect($bruFile['path']->basename())->toMatch('/^[a-z0-9-]+\.bru$/');
    });
});
