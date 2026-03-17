# Laravel Bruno Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-bruno-generator.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-bruno-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-bruno-generator.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-bruno-generator)

Generate [Bruno](https://www.usebruno.com/) API collections from Laravel routes.

This package analyzes your Laravel application's routes and generates a ready-to-use Bruno collection, complete with request bodies inferred from FormRequests, authentication configuration, environment files, and more.

## Installation

You can install the package via composer:

```bash
composer require shahghasiadil/laravel-bruno-generator --dev
```

Publish the config file:

```bash
php artisan vendor:publish --tag=bruno-generator-config
```

## Quick Start

Generate a collection:

```bash
php artisan bruno:generate
```

Default output:

```text
bruno/collections/Laravel-API/
```

Open that folder in Bruno using `Open Collection`.

## Features

- One-command generation from Laravel routes
- FormRequest body inference
- Route filtering (middleware, prefix, include/exclude)
- Multiple organization strategies (`prefix`, `controller`, `tag`, `none`)
- Auth support (`none`, `bearer`, `basic`, `oauth2`)
- Multi-environment generation (`Local`, `Staging`, `Production` by default)
- Optional docs, tests, and scripts generation
- `.bru` and YAML output support
- Deterministic, git-friendly generated files

## Usage

### Common commands

Generate with default settings:

```bash
php artisan bruno:generate
```

Generate YAML output:

```bash
php artisan bruno:generate --format=yaml
```

Generate only API routes protected by Sanctum:

```bash
php artisan bruno:generate --api-only --middleware=auth:sanctum
```

Generate with docs and tests:

```bash
php artisan bruno:generate --with-docs --with-tests
```

Preview without writing files:

```bash
php artisan bruno:generate --dry-run
```

Overwrite existing output:

```bash
php artisan bruno:generate --force
```

Clear generated collection:

```bash
php artisan bruno:clear
```

Clear a custom path:

```bash
php artisan bruno:clear path/to/collection --force
```

### All generate options

```bash
php artisan bruno:generate \
  --format=bru|yaml \
  --output=path/to/output \
  --name="My API" \
  --api-only \
  --prefix=api/v1 \
  --exclude-prefix=admin \
  --middleware=auth:sanctum \
  --exclude-middleware=web \
  --group-by=prefix|controller|tag|none \
  --with-body-inference \
  --with-tests \
  --with-scripts \
  --with-docs \
  --force \
  --dry-run
```

## Configuration

You can control most behavior through `.env`:

```env
BRUNO_OUTPUT_PATH=bruno/collections
BRUNO_COLLECTION_NAME="Laravel API"
BRUNO_OUTPUT_FORMAT=bru
BRUNO_GROUP_BY=prefix
BRUNO_INFER_BODY=true
BRUNO_AUTH_MODE=bearer

APP_URL=http://localhost:8000
STAGING_URL=https://staging.example.com
PRODUCTION_URL=https://api.example.com
```

After publishing, full options are available in `config/bruno-generator.php`.

### Key config sections

- `output_path`, `collection_name`, `output_format`
- `route_discovery` for include/exclude rules
- `organization` for grouping and sorting
- `request_generation` for body/query/header behavior
- `auth` for mode and auth middleware detection
- `environments` for generated Bruno environments
- `advanced` for docs length, tests, scripts, YAML options, request settings

## FormRequest Body Inference

When enabled, request bodies are generated from your FormRequest rules.

Example FormRequest rules:

```php
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
```

Example generated body:

```json
{
  "name": "Name",
  "email": "user@example.com",
  "age": 18,
  "is_active": true,
  "tags": [""]
}
```

Nested rules like `user.name` and `user.email` are also supported.

## Output Formats

Both output formats are supported:

- `bru` (default)
- `yaml`

Use command flag:

```bash
php artisan bruno:generate --format=yaml
```

Or environment variable:

```env
BRUNO_OUTPUT_FORMAT=yaml
```

## Environments

By default, the package generates:

- `Local`
- `Staging`
- `Production`

Each environment contains values like `baseUrl` and `authToken` and can be switched in Bruno.

You can add custom environments (for example `Development`, `QA`, `UAT`) in `config/bruno-generator.php`.

## Generated Structure

Typical output:

```text
bruno/collections/Laravel-API/
  bruno.json
  environments/
    Local.bru
    Staging.bru
    Production.bru
  api/
    ...requests and folders...
```

## Best Practices

- Use `--dry-run` before first full generation
- Keep route filters explicit in larger projects
- Commit generated collections to track API changes
- Keep environment URLs in `.env`
- Do not commit real auth tokens

## Testing

Run tests:

```bash
composer test
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

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover any security-related issues, please email adil.shahghasi@gmail.com.

## Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
