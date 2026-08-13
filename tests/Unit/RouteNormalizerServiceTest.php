<?php

declare(strict_types=1);

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteNormalizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleController;

beforeEach(function () {
    $this->formRequestParser = new FormRequestParserService;

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

    test('converts route parameters to Bruno path params by default', function () {
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

        expect($requests->first()->url)->toContain('{{baseUrl}}/api/users/:id');
        expect($requests->first()->pathVariables)->toHaveKey('id');
    });

    test('supports the legacy double_brace path param style', function () {
        $config = $this->config;
        $config['request_generation']['path_param_style'] = 'double_brace';
        $service = new RouteNormalizerService($this->formRequestParser, $config);

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

        $requests = $service->normalize($routes);

        expect($requests->first()->url)->toContain('{{baseUrl}}/api/users/{{id}}');
        expect($requests->first()->pathVariables)->toBe([]);
    });

    test('generates query params from FormRequest rules on GET routes', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: SampleController::class.'@store',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: SampleController::class,
                controllerMethod: 'store',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->queryParams)->toHaveKey('name');
        expect($requests->first()->queryParams)->not->toHaveKey('tags');
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

    test('generates an inherit auth block for protected routes by default', function () {
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
        expect($requests->first()->auth->type)->toBe(AuthType::INHERIT);
    });

    test('generates full inline auth for yaml format, since there is no collection-root file to inherit from yet', function () {
        $config = $this->config;
        $config['output_format'] = 'yaml';
        $service = new RouteNormalizerService($this->formRequestParser, $config);

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

        $requests = $service->normalize($routes);

        expect($requests->first()->auth->type)->toBe(AuthType::BEARER);
        expect($requests->first()->auth->config)->toBe(['token' => '{{authToken}}']);
    });

    test('generates full inline auth when inherit_from_collection is disabled', function () {
        $config = $this->config;
        $config['auth']['inherit_from_collection'] = false;
        $service = new RouteNormalizerService($this->formRequestParser, $config);

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

        $requests = $service->normalize($routes);

        expect($requests->first()->auth->type)->toBe(AuthType::BEARER);
        expect($requests->first()->auth->config)->toBe(['token' => '{{authToken}}']);
    });

    test('does not generate auth for routes without auth middleware, even when a mode is configured', function () {
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

        expect($requests->first()->auth)->toBeNull();
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

    test('omits Content-Type for inferred multipart-form requests', function () {
        $config = $this->config;
        $config['request_generation']['infer_body_from_form_request'] = true;
        $service = new RouteNormalizerService($this->formRequestParser, $config);

        $routes = collect([
            new RouteInfo(
                uri: 'api/uploads',
                methods: ['POST'],
                name: 'uploads.store',
                action: SampleController::class.'@upload',
                middleware: ['api'],
                domain: null,
                parameters: [],
                controller: SampleController::class,
                controllerMethod: 'upload',
                isFallback: false,
            ),
        ]);

        $requests = $service->normalize($routes);
        $request = $requests->first();

        expect($request->body->type)->toBe(BodyType::MULTIPART_FORM);
        expect($request->headers)->not->toHaveKey('Content-Type');
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

    test('uses a where() numeric constraint over the name heuristic', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/posts/{slug}',
                methods: ['GET'],
                name: 'posts.show',
                action: 'PostController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['slug' => '[0-9]+'],
                controller: 'PostController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        // "slug" would normally produce 'example-slug' by name, but the
        // numeric where() constraint should win.
        expect($requests->first()->pathVariables['slug'])->toBe('1');
    });

    test('uses a where() alternation constraint to pick the first option', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/reports/{period}',
                methods: ['GET'],
                name: 'reports.show',
                action: 'ReportController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['period' => '(daily|weekly|monthly)'],
                controller: 'ReportController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->pathVariables['period'])->toBe('daily');
    });

    test('uses a hyphen-free example for an alphabetic-only where() constraint', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/countries/{code}',
                methods: ['GET'],
                name: 'countries.show',
                action: 'CountryController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['code' => '[a-zA-Z]+'],
                controller: 'CountryController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->pathVariables['code'])->toBe('examplevalue');
    });

    test('uses a slug example for an alphabetic where() constraint that explicitly allows a hyphen', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/countries/{code}',
                methods: ['GET'],
                name: 'countries.show',
                action: 'CountryController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['code' => '[a-z-]+'],
                controller: 'CountryController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->pathVariables['code'])->toBe('example-slug');
    });

    test('falls back to the name heuristic when the where() pattern is unrecognized', function () {
        $routes = collect([
            new RouteInfo(
                uri: 'api/users/{id}',
                methods: ['GET'],
                name: 'users.show',
                action: 'UserController@show',
                middleware: ['api'],
                domain: null,
                parameters: ['id' => '.*'],
                controller: 'UserController',
                controllerMethod: 'show',
                isFallback: false,
            ),
        ]);

        $requests = $this->service->normalize($routes);

        expect($requests->first()->pathVariables['id'])->toBe('1');
    });
});
