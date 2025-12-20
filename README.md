# Laravel Bruno Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-bruno-generator.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-bruno-generator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/shahghasiadil/laravel-bruno-generator/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/shahghasiadil/laravel-bruno-generator/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-bruno-generator.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-bruno-generator)

**Automatically generate [Bruno](https://www.usebruno.com/) API collections from your Laravel routes.**

This package analyzes your Laravel application's routes and generates a ready-to-use Bruno collection, complete with request bodies inferred from FormRequests, authentication configuration, environment files, and more.

## Features

- 🚀 **One-Command Generation** - Generate complete Bruno collections instantly
- 📝 **FormRequest Integration** - Automatically infer request bodies from Laravel FormRequests
- 🗂️ **Smart Organization** - Group requests by prefix, controller, or tags
- 🔐 **Auth Support** - Bearer, Basic, and OAuth2 authentication
- 🌍 **Multi-Environment** - Generate Local, Staging, and Production environments
- 📚 **PHPDoc Extraction** - Include controller method documentation
- ✅ **Test Generation** - Optional test block generation
- 🎯 **Powerful Filtering** - Filter by middleware, prefix, route name patterns
- 💾 **Git-Friendly** - Deterministic output perfect for version control
- 🛡️ **Type-Safe** - Full PHP 8.1+ strict types throughout

## Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 10, 11, or 12

## Installation

Install the package via Composer:

```bash
composer require shahghasiadil/laravel-bruno-generator --dev
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=bruno-generator-config
```

## Quick Start

Generate a Bruno collection from your API routes:

```bash
php artisan bruno:generate
```

The collection will be generated in `bruno/collections/Laravel-API/` by default.

### Open in Bruno

1. Open Bruno application
2. Click "Open Collection"
3. Navigate to the generated folder
4. Start testing your API!

## Usage

### Basic Usage

Generate with default settings:

```bash
php artisan bruno:generate
```

### Command Options

```bash
php artisan bruno:generate
    --output=path/to/output      # Custom output directory
    --name="My API"              # Collection name
    --api-only                   # Include only API routes
    --prefix=api/v1              # Filter by route prefix
    --middleware=auth:sanctum    # Filter by middleware
    --group-by=controller        # Group by: prefix|controller|tag|none
    --with-body-inference        # Enable FormRequest parsing
    --with-tests                 # Generate test blocks
    --with-docs                  # Include PHPDoc documentation
    --force                      # Overwrite existing collection
    --dry-run                    # Preview without writing files
```

### Examples

**Generate only authenticated API routes:**

```bash
php artisan bruno:generate --api-only --middleware=auth:sanctum
```

**Generate with FormRequest body inference:**

```bash
php artisan bruno:generate --with-body-inference
```

**Group by controller with tests:**

```bash
php artisan bruno:generate --group-by=controller --with-tests
```

**Dry run to preview:**

```bash
php artisan bruno:generate --dry-run
```

**Clear generated collection:**

```bash
php artisan bruno:clear
```

## Configuration

The configuration file provides extensive customization options:

### Route Discovery & Filtering

```php
'route_discovery' => [
    'auto_detect_api' => true,

    'include_middleware' => ['api'],
    'exclude_middleware' => ['web'],

    'include_prefixes' => [],
    'exclude_prefixes' => ['telescope', 'horizon'],

    'include_names' => [],
    'exclude_names' => ['/^debugbar\./'],

    'exclude_fallback' => true,
],
```

### Collection Organization

```php
'organization' => [
    'group_by' => 'prefix', // prefix|controller|tag|none
    'folder_depth' => 2,
    'sort_by' => 'uri',     // uri|method|name|none
    'sort_direction' => 'asc',
],
```

### Request Generation

```php
'request_generation' => [
    'infer_body_from_form_request' => true,
    'generate_example_values' => true,
    'parameterize_route_params' => true,
    'generate_query_params' => true,
],
```

### Authentication

```php
'auth' => [
    'mode' => 'bearer',  // none|bearer|basic|oauth2
    'bearer_token_var' => 'authToken',
    'include_auth' => true,
    'auth_middleware' => ['auth:sanctum', 'auth:api'],
],
```

### Environments

```php
'environments' => [
    'Local' => [
        'baseUrl' => 'http://localhost:8000',
        'authToken' => '',
    ],
    'Production' => [
        'baseUrl' => 'https://api.example.com',
        'authToken' => '',
    ],
],
```

## FormRequest Body Inference

The package can automatically generate request bodies from your Laravel FormRequests:

```php
// app/Http/Requests/UpdateUserRequest.php
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'integer|min:18',
            'is_active' => 'boolean',
            'tags' => 'array',
            'tags.*' => 'string',
        ];
    }
}
```

**Generated Bruno request body:**

```json
{
  "name": "Name",
  "email": "user@example.com",
  "age": 18,
  "is_active": true,
  "tags": [""]
}
```

### Supported Rules

- **String types**: `string`, `email`, `url`, `uuid`
- **Numeric types**: `integer`, `numeric`, `decimal`
- **Boolean**: `boolean`, `bool`
- **Arrays**: `array`, nested arrays with `.*`
- **Dates**: `date`, `datetime`, `date_format`
- **Files**: `file`, `image`

### Nested Fields

```php
'user.name' => 'required|string',
'user.email' => 'required|email',
```

Generates:

```json
{
  "user": {
    "name": "Name",
    "email": "user@example.com"
  }
}
```

## Output Structure

```
bruno/collections/Laravel-API/
├── bruno.json
├── environments/
│   ├── Local.bru
│   ├── Staging.bru
│   └── Production.bru
├── api/
│   └── v1/
│       ├── users/
│       │   ├── get-users.bru
│       │   ├── post-users.bru
│       │   └── get-users-id.bru
│       └── posts/
│           ├── get-posts.bru
│           └── post-posts.bru
└── get-health.bru
```

## Bruno File Format

Each `.bru` file contains all the request details:

```
meta {
  name: Get Users
  type: http
  seq: 1
}

get {
  url: {{baseUrl}}/api/users
  body: none
  auth: none
}

headers {
  Accept: application/json
}

auth:bearer {
  token: {{authToken}}
}
```

## Advanced Features

### PHPDoc Documentation

Extract documentation from controller methods:

```php
/**
 * Retrieve all users with pagination.
 *
 * Returns a paginated list of users with their profiles.
 */
public function index()
{
    //...
}
```

### Test Generation

Generate basic test assertions:

```javascript
tests {
  test("should return successful response", function() {
    expect(res.getStatus()).to.equal(200);
  });
}
```

### Custom Scripts

Add pre-request or post-response scripts via configuration:

```php
'script_templates' => [
    'pre_request' => [
        'bru.setVar("timestamp", Date.now());',
    ],
],
```

## Best Practices

1. **Use --dry-run first** - Preview the output before generating
2. **Filter routes carefully** - Only include routes you need to test
3. **Enable body inference** - Save time with automatic request bodies
4. **Use environments** - Configure multiple environments from the start
5. **Version control** - Commit the generated collection to track API changes
6. **Regenerate regularly** - Keep the collection in sync with route changes

## Testing

Run the package tests:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on recent changes.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email adil.shahghasi@gmail.com instead of using the issue tracker.

## Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

- **Issues**: [GitHub Issues](https://github.com/shahghasiadil/laravel-bruno-generator/issues)
- **Documentation**: [Full Documentation](https://github.com/shahghasiadil/laravel-bruno-generator)
- **Discussions**: [GitHub Discussions](https://github.com/shahghasiadil/laravel-bruno-generator/discussions)

---

**Built with ❤️ for the Laravel and Bruno communities**
