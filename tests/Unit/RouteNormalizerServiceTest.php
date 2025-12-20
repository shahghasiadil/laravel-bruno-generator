<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteNormalizerService;

beforeEach(function () {
    $this->formRequestParser = new FormRequestParserService();

    $this->config = [
        'variables' => [
            'base_url_var' => 'baseUrl',
        ],
        'request_generation' => [
            'infer_body_from_form_request' => false,
            'parameterize_route_params' => true,
            'include_default_headers' => true,
        ],
        'default_headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'auth' => [
            'mode' => 'bearer',
            'include_auth' => true,
            'bearer_token_var' => 'authToken',
            'auth_middleware' => ['auth:sanctum', 'auth:api'],
        ],
        'organization' => [
            'group_by' => 'prefix',
            'folder_depth' => 2,
        ],
        'advanced' => [
            'include_phpdoc_docs' => false,
            'generate_tests' => false,
            'generate_pre_request_scripts' => false,
            'generate_post_response_scripts' => false,
            'max_description_length' => 200,
        ],
    ];

    $this->service = new RouteNormalizerService($this->formRequestParser, $this->config);
});

describe('RouteNormalizerService', function () {
    test('normalizes basic route', function () {
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
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests)->toHaveCount(1);
        expect($requests->first()->name)->toBeString();
        expect($requests->first()->method)->toBe('GET');
        expect($requests->first()->url)->toContain('{{baseUrl}}/api/users');
    });

    test('converts route parameters to Bruno variables', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users/{id}',
                methods: ['GET'],
                name: 'users.show',
                action: 'UserController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['id' => 'id'],
                controller: 'UserController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->url)->toContain('{{baseUrl}}/api/users/{{id}}');
        expect($requests->first()->pathVariables)->toHaveKey('id');
    });

    test('generates request per HTTP method', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET', 'POST'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests)->toHaveCount(2);
        expect($requests->pluck('method')->all())->toBe(['GET', 'POST']);
    });

    test('filters out HEAD method', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET', 'HEAD', 'POST'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests)->toHaveCount(2);
        expect($requests->pluck('method')->all())->toBe(['GET', 'POST']);
    });

    test('includes default headers for GET requests', function () {
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
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->headers)->toHaveKey('Accept');
        expect($requests->first()->headers)->not->toHaveKey('Content-Type');
    });

    test('includes Content-Type header for POST requests', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['POST'],
                name: 'users.store',
                action: 'UserController@store',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'store',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->headers)->toHaveKey('Content-Type');
    });

    test('generates auth block for protected routes', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api', 'auth:sanctum'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->auth)->not->toBeNull();
        expect($requests->first()->auth->type)->toBe(AuthType::BEARER);
    });

    test('does not generate auth for public routes', function () {
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
        ]);

        $config = array_merge($this->config, [
            'auth' => [
                'mode' => 'none',
                'include_auth' => false,
            ],
        ]);

        $service = new RouteNormalizerService($this->formRequestParser, $config);
        $requests = $service->normalize($routes);

        expect($requests->first()->auth)->toBeNull();
    });

    test('generates name from route name', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'api.users.index',
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->name)->toContain('Api');
    });

    test('generates name from controller method when no route name', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: null,
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->name)->toContain('Index');
    });

    test('generates description with middleware info', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'UserController@index',
                middleware: ['api', 'auth:sanctum', 'throttle:60'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->description)->toContain('Middleware:');
    });

    test('determines group from prefix', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/v1/users',
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
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->group)->toBe('api/v1');
    });

    test('assigns sequential numbers', function () {
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
                uri: 'api/posts',
                methods: ['GET'],
                name: 'posts.index',
                action: 'PostController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'PostController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->pluck('sequence')->all())->toBe([1, 2]);
    });

    test('generates example value for route parameters', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users/{userId}',
                methods: ['GET'],
                name: 'users.show',
                action: 'UserController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['userId' => 'userId'],
                controller: 'UserController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->pathVariables)->toHaveKey('userId');
        expect($requests->first()->pathVariables['userId'])->toBe('1'); // id-like parameter
    });

    test('extracts tags from route name', function () {
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
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->tags)->toContain('users');
    });

    test('extracts tags from controller', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/test',
                methods: ['GET'],
                name: null,
                action: 'UserController@index',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'index',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->tags)->toContain('User');
    });

    test('creates empty body for POST requests without FormRequest', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['POST'],
                name: 'users.store',
                action: 'UserController@store',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: 'UserController',
                controllerMethod: 'store',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->body)->not->toBeNull();
        expect($requests->first()->body->content)->toBeArray();
    });

    test('does not create body for GET requests', function () {
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
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->body)->toBeNull();
    });
});
