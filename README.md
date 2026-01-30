# Laravel Bruno Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-bruno-generator.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-bruno-generator)
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

## Environment Variables

The package supports several environment variables for quick configuration without modifying the config file:

| Variable | Description | Default |
|----------|-------------|---------|
| `BRUNO_OUTPUT_PATH` | Output directory for Bruno collections | `bruno/collections` |
| `BRUNO_COLLECTION_NAME` | Name of the generated collection | `Laravel API` |
| `BRUNO_GROUP_BY` | Grouping strategy (prefix/controller/tag/none) | `prefix` |
| `BRUNO_INFER_BODY` | Enable FormRequest body inference | `true` |
| `BRUNO_AUTH_MODE` | Authentication mode (none/bearer/basic/oauth2) | `bearer` |
| `APP_URL` | Local environment base URL | `http://localhost:8000` |
| `STAGING_URL` | Staging environment base URL | `https://staging.example.com` |
| `PRODUCTION_URL` | Production environment base URL | `https://api.example.com` |

### Using Environment Variables

Add these to your `.env` file to customize the generation behavior:

```env
# Bruno Generator Settings
BRUNO_OUTPUT_PATH=bruno/collections
BRUNO_COLLECTION_NAME="My Project API"
BRUNO_GROUP_BY=controller
BRUNO_INFER_BODY=true
BRUNO_AUTH_MODE=bearer

# Environment URLs
APP_URL=http://localhost:8000
STAGING_URL=https://staging.myapp.com
PRODUCTION_URL=https://api.myapp.com
```

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

Configure multiple environments with different base URLs and authentication tokens:

```php
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
```

Each environment generates a separate `.bru` file in the `environments/` folder, making it easy to switch between different environments in Bruno.

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

## Working with Multiple Environments

The package generates separate environment files for Local, Staging, and Production by default. This allows you to seamlessly switch between different API endpoints while testing.

### Environment Configuration

#### 1. Configure Environment URLs

Set your environment URLs in `.env`:

```env
APP_URL=http://localhost:8000
STAGING_URL=https://staging.myapp.com
PRODUCTION_URL=https://api.myapp.com
```

#### 2. Generate the Collection

```bash
php artisan bruno:generate
```

This creates environment files in `bruno/collections/Laravel-API/environments/`:

```
environments/
├── Local.bru
├── Staging.bru
└── Production.bru
```

#### 3. Switch Environments in Bruno

1. Open your collection in Bruno
2. Click the environment dropdown in the top-right
3. Select your desired environment (Local, Staging, or Production)
4. All requests will now use that environment's `baseUrl`

### Environment Variables

Each environment file contains variables that can be referenced in your requests using `{{variableName}}` syntax:

**Local.bru:**
```
vars {
  baseUrl: http://localhost:8000
  authToken:
}
```

**Staging.bru:**
```
vars {
  baseUrl: https://staging.myapp.com
  authToken: your-staging-token-here
}
```

**Production.bru:**
```
vars {
  baseUrl: https://api.myapp.com
  authToken: your-production-token-here
}
```

### Adding Custom Environment Variables

You can add custom variables to each environment in the config file:

```php
'environments' => [
    'Local' => [
        'baseUrl' => env('APP_URL', 'http://localhost:8000'),
        'authToken' => '',
        'apiKey' => env('LOCAL_API_KEY', ''),
        'tenantId' => 'local-tenant',
    ],
    'Staging' => [
        'baseUrl' => env('STAGING_URL', 'https://staging.example.com'),
        'authToken' => '',
        'apiKey' => env('STAGING_API_KEY', ''),
        'tenantId' => 'staging-tenant',
    ],
    'Production' => [
        'baseUrl' => env('PRODUCTION_URL', 'https://api.example.com'),
        'authToken' => '',
        'apiKey' => env('PRODUCTION_API_KEY', ''),
        'tenantId' => 'prod-tenant',
    ],
],
```

Then use these variables in your requests:

```
headers {
  X-API-Key: {{apiKey}}
  X-Tenant-ID: {{tenantId}}
}
```

### Adding New Environments

You can add custom environments like Development, QA, or UAT:

```php
'environments' => [
    'Local' => [
        'baseUrl' => env('APP_URL', 'http://localhost:8000'),
        'authToken' => '',
    ],
    'Development' => [
        'baseUrl' => env('DEV_URL', 'https://dev.example.com'),
        'authToken' => '',
    ],
    'QA' => [
        'baseUrl' => env('QA_URL', 'https://qa.example.com'),
        'authToken' => '',
    ],
    'UAT' => [
        'baseUrl' => env('UAT_URL', 'https://uat.example.com'),
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
```

### Environment-Specific Authentication

Each environment can have its own authentication token. After generating the collection:

1. Open the environment file (e.g., `environments/Staging.bru`)
2. Add your authentication token:

```
vars {
  baseUrl: https://staging.myapp.com
  authToken: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
}
```

3. Requests using `{{authToken}}` will automatically use the environment-specific token

### Best Practices for Multi-Environment Setup

1. **Never commit tokens** - Add `*.bru` files with tokens to `.gitignore` or use environment variables
2. **Use descriptive names** - Name environments based on your deployment pipeline
3. **Keep URLs in .env** - Store all environment URLs in your `.env` file for easy management
4. **Document required variables** - Add comments in config to explain custom variables
5. **Test each environment** - Verify requests work correctly in each environment
6. **Use environment-specific data** - Configure test data IDs per environment if needed

### Example: Complete Multi-Environment Setup

**.env file:**
```env
# Local Development
APP_URL=http://localhost:8000

# Remote Environments
DEV_URL=https://dev-api.myapp.com
STAGING_URL=https://staging-api.myapp.com
PRODUCTION_URL=https://api.myapp.com

# API Keys (optional)
DEV_API_KEY=dev_key_123
STAGING_API_KEY=staging_key_456
PRODUCTION_API_KEY=prod_key_789
```

**config/bruno-generator.php:**
```php
'environments' => [
    'Local' => [
        'baseUrl' => env('APP_URL'),
        'authToken' => '',
        'apiKey' => '',
        'debug' => true,
    ],
    'Development' => [
        'baseUrl' => env('DEV_URL'),
        'authToken' => '',
        'apiKey' => env('DEV_API_KEY'),
        'debug' => true,
    ],
    'Staging' => [
        'baseUrl' => env('STAGING_URL'),
        'authToken' => '',
        'apiKey' => env('STAGING_API_KEY'),
        'debug' => false,
    ],
    'Production' => [
        'baseUrl' => env('PRODUCTION_URL'),
        'authToken' => '',
        'apiKey' => env('PRODUCTION_API_KEY'),
        'debug' => false,
    ],
],
```

After generation, manually add your auth tokens to each environment file in Bruno.

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
