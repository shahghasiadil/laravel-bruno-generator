<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Collection Output Settings
    |--------------------------------------------------------------------------
    |
    | Configure where the Bruno collection will be generated and what it will
    | be named. The output path is relative to your Laravel project root.
    |
    */
    'output_path' => env('BRUNO_OUTPUT_PATH', 'bruno/collections'),
    'collection_name' => env('BRUNO_COLLECTION_NAME', 'Laravel API'),
    'bruno_version' => '1',

    /*
    |--------------------------------------------------------------------------
    | Route Discovery & Filtering
    |--------------------------------------------------------------------------
    |
    | Control which routes are included in the generated Bruno collection.
    | You can filter by middleware, route prefixes, route names, and domains.
    |
    */
    'route_discovery' => [
        // Auto-detect API routes by middleware
        'auto_detect_api' => true,

        // Include routes with these middleware
        'include_middleware' => [
            'api',
        ],

        // Exclude routes with these middleware
        'exclude_middleware' => [
            'web',
        ],

        // Include routes with these prefixes
        'include_prefixes' => [
            // 'api/v1',
        ],

        // Exclude routes with these prefixes
        'exclude_prefixes' => [
            'telescope',
            'horizon',
            '_ignition',
            'sanctum/csrf-cookie',
        ],

        // Include routes matching these name patterns (regex)
        'include_names' => [
            // '/^api\./',
        ],

        // Exclude routes matching these name patterns (regex)
        'exclude_names' => [
            '/^debugbar\./',
            '/^sanctum\./',
            '/^ignition\./',
        ],

        // Include specific domains (null = all domains)
        'include_domains' => null,

        // Exclude fallback routes
        'exclude_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Organization
    |--------------------------------------------------------------------------
    |
    | Define how routes are organized into folders and how they are sorted
    | within the Bruno collection structure.
    |
    */
    'organization' => [
        // Group routes by: 'prefix', 'controller', 'tag', 'none'
        'group_by' => env('BRUNO_GROUP_BY', 'prefix'),

        // Folder depth for prefix grouping (e.g., api/v1/users -> depth 3)
        'folder_depth' => 2,

        // Sort routes within groups by: 'uri', 'method', 'name', 'none'
        'sort_by' => 'uri',

        // Sort direction: 'asc', 'desc'
        'sort_direction' => 'asc',

        // Sequence number increment
        'sequence_increment' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Generation
    |--------------------------------------------------------------------------
    |
    | Configure how HTTP requests are generated from your Laravel routes,
    | including body inference from FormRequests and parameter handling.
    |
    */
    'request_generation' => [
        // Infer request body from FormRequest rules
        'infer_body_from_form_request' => env('BRUNO_INFER_BODY', true),

        // Generate example values for body fields
        'generate_example_values' => true,

        // Convert route parameters to Bruno variables ({id} -> {{id}})
        'parameterize_route_params' => true,

        // Generate query parameter examples
        'generate_query_params' => true,

        // Include common headers
        'include_default_headers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Headers that will be included in all generated requests. You can
    | override these in specific routes if needed.
    |
    */
    'default_headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication settings for the generated requests. The
    | package can detect auth middleware and add appropriate auth blocks.
    |
    */
    'auth' => [
        // Auth mode: 'none', 'bearer', 'basic', 'oauth2'
        'mode' => env('BRUNO_AUTH_MODE', 'bearer'),

        // Bearer token variable name
        'bearer_token_var' => 'authToken',

        // Include auth block in requests
        'include_auth' => true,

        // Routes requiring auth (middleware detection)
        'auth_middleware' => [
            'auth:sanctum',
            'auth:api',
            'auth',
            'jwt.auth',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Templates
    |--------------------------------------------------------------------------
    |
    | Define environment-specific variables that will be generated in
    | separate .bru files in the environments/ folder.
    |
    */
    'environments' => [
        'Local' => [
            'baseUrl' => env('APP_URL', 'http://localhost:8000'),
            'authToken' => '',
        ],
        'Staging' => [
            'baseUrl' => env('STAGING_URL', 'https://staging.example.com'),
            'authToken' => '',
        ],
        'Production' => [
            'baseUrl' => env('PRODUCTION_URL', 'https://api.example.com'),
            'authToken' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Global collection variables and custom variables to include in
    | the generated requests.
    |
    */
    'variables' => [
        // Global collection variables
        'base_url_var' => 'baseUrl',

        // Custom variables to include in collection
        'custom' => [
            // 'apiKey' => '{{apiKey}}',
            // 'tenantId' => '{{tenantId}}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Options
    |--------------------------------------------------------------------------
    |
    | Advanced configuration options for script generation, documentation
    | extraction, and file naming strategies.
    |
    */
    'advanced' => [
        // Generate pre-request scripts
        'generate_pre_request_scripts' => false,

        // Generate post-response scripts
        'generate_post_response_scripts' => false,

        // Generate tests
        'generate_tests' => false,

        // Include route documentation from PHPDoc
        'include_phpdoc_docs' => true,

        // Maximum description length
        'max_description_length' => 200,

        // File naming strategy: 'descriptive', 'sequential'
        'file_naming' => 'descriptive',

        // FormRequest parsing options
        'form_request' => [
            // Maximum nesting depth for nested rules
            'max_nesting_depth' => 5,

            // Include optional fields (nullable, sometimes)
            'include_optional_fields' => true,
        ],

        // Script templates
        'script_templates' => [
            'pre_request' => [
                // Add custom pre-request script templates
            ],
            'post_response' => [
                // Add custom post-response script templates
            ],
        ],

        // Test templates
        'test_templates' => [
            'status_check' => true,
            'response_time' => false,
            'schema_validation' => false,
        ],
    ],
];
