<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentVariable;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers\YamlFormatSerializer;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->serializer = new YamlFormatSerializer;
});

describe('YamlFormatSerializer', function () {
    test('uses the .yml extension', function () {
        expect($this->serializer->getFileExtension())->toBe('.yml');
    });

    test('serializes info, http, params, headers, body, auth and tags per the OpenCollection spec', function () {
        $request = new BrunoRequest(
            name: 'Create User',
            description: 'Test',
            sequence: 3,
            method: 'POST',
            url: '{{baseUrl}}/api/users/:id',
            headers: ['Accept' => 'application/json'],
            queryParams: ['filter' => 'active'],
            pathVariables: ['id' => '1'],
            body: new RequestBody(type: BodyType::JSON, content: ['name' => 'John'], raw: null),
            auth: new AuthBlock(AuthType::BEARER, ['token' => '{{authToken}}']),
            group: null,
            controller: null,
            tags: ['users', 'smoke'],
        );

        $yaml = Yaml::parse($this->serializer->serializeRequest($request));

        expect($yaml['info'])->toBe([
            'name' => 'Create User',
            'type' => 'http',
            'seq' => 3,
            'tags' => ['users', 'smoke'],
        ]);

        expect($yaml['http']['method'])->toBe('POST');
        expect($yaml['http']['url'])->toBe('{{baseUrl}}/api/users/:id');

        expect($yaml['http']['params'])->toContain(['name' => 'filter', 'value' => 'active', 'type' => 'query']);
        expect($yaml['http']['params'])->toContain(['name' => 'id', 'value' => '1', 'type' => 'path']);

        expect($yaml['http']['headers'])->toBe([
            ['name' => 'Accept', 'value' => 'application/json'],
        ]);

        expect($yaml['http']['body']['type'])->toBe('json');
        expect($yaml['http']['body']['data'])->toBeString();
        expect(json_decode((string) $yaml['http']['body']['data'], true))->toBe(['name' => 'John']);

        expect($yaml['http']['auth'])->toBe([
            'type' => 'bearer',
            'token' => '{{authToken}}',
        ]);
    });

    test('serializes form-urlencoded body as an array of name/value pairs', function () {
        $request = new BrunoRequest(
            name: 'Submit Form',
            description: 'Test',
            sequence: 1,
            method: 'POST',
            url: '{{baseUrl}}/api/submit',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: new RequestBody(type: BodyType::FORM_URLENCODED, content: ['username' => 'john'], raw: null),
            auth: null,
            group: null,
            controller: null,
            tags: [],
        );

        $yaml = Yaml::parse($this->serializer->serializeRequest($request));

        expect($yaml['http']['body'])->toBe([
            'type' => 'form-urlencoded',
            'data' => [
                ['name' => 'username', 'value' => 'john'],
            ],
        ]);
    });

    test('serializes scripts and tests into runtime.scripts', function () {
        $request = new BrunoRequest(
            name: 'Login',
            description: 'Test',
            sequence: 1,
            method: 'POST',
            url: '{{baseUrl}}/api/login',
            headers: [],
            queryParams: [],
            pathVariables: [],
            body: null,
            auth: null,
            group: null,
            controller: null,
            tags: [],
            preRequestScript: 'bru.setVar("x", 1);',
            postResponseScript: 'bru.setVar("token", res.body.token);',
            tests: 'test("ok", function() {});',
        );

        $yaml = Yaml::parse($this->serializer->serializeRequest($request));

        expect($yaml['runtime']['scripts'])->toBe([
            ['type' => 'before-request', 'code' => 'bru.setVar("x", 1);'],
            ['type' => 'after-response', 'code' => 'bru.setVar("token", res.body.token);'],
            ['type' => 'tests', 'code' => 'test("ok", function() {});'],
        ]);
    });

    test('serializes inherit auth as the bare scalar', function () {
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
            auth: new AuthBlock(AuthType::INHERIT, []),
            group: null,
            controller: null,
            tags: [],
        );

        $yaml = Yaml::parse($this->serializer->serializeRequest($request));

        expect($yaml['http']['auth'])->toBe('inherit');
    });

    test('does not support a collection-level auth file yet', function () {
        expect($this->serializer->serializeCollectionAuth(new AuthBlock(AuthType::BEARER, ['token' => '{{authToken}}'])))->toBeNull();
    });

    test('serializes environments as an array of variable objects', function () {
        $yaml = Yaml::parse($this->serializer->serializeEnvironment('Local', [
            new EnvironmentVariable(name: 'baseUrl', value: 'http://localhost'),
        ]));

        expect($yaml)->toBe([
            'name' => 'Local',
            'variables' => [
                [
                    'name' => 'baseUrl',
                    'value' => 'http://localhost',
                    'enabled' => true,
                    'secret' => false,
                ],
            ],
        ]);
    });

    test('blanks the value and sets secret: true for secret environment variables', function () {
        $yaml = Yaml::parse($this->serializer->serializeEnvironment('Local', [
            new EnvironmentVariable(name: 'authToken', value: 'super-secret', secret: true),
        ]));

        expect($yaml['variables'][0])->toBe([
            'name' => 'authToken',
            'value' => '',
            'enabled' => true,
            'secret' => true,
        ]);
    });

    test('includes a description for environment variables when present', function () {
        $yaml = Yaml::parse($this->serializer->serializeEnvironment('Local', [
            new EnvironmentVariable(name: 'baseUrl', value: 'http://localhost', description: 'Local dev server'),
        ]));

        expect($yaml['variables'][0]['description'])->toBe('Local dev server');
    });
});
