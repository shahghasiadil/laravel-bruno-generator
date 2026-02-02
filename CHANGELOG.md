# Changelog

All notable changes to `laravel-bruno-generator` will be documented in this file.

## [Unreleased]

### Added
- Support for OpenCollection YAML format
- `--format` option to choose output format (bru or yaml)
- Environment variable: `BRUNO_OUTPUT_FORMAT`
- Full Markdown documentation support for YAML format (no character limits)
- New enum: `OutputFormat`
- Format-specific serializers via strategy pattern
- `FormatSerializerInterface` contract for extensible format support
- `BruFormatSerializer` for .bru format generation
- `YamlFormatSerializer` for OpenCollection YAML format generation
- `FormatSerializerFactory` for creating format-specific serializers

### Changed
- Documentation length limit now only applies to .bru format
- Refactored `BrunoSerializerService` to use factory pattern
- Enhanced `RouteNormalizerService` with format-aware documentation extraction
- Updated `BrunoGenerateCommand` with format option
- File extensions now determined dynamically based on output format

### Dependencies
- Added `symfony/yaml` ^6.0|^7.0

## 1.0.0 - 2024-12-19

### Added
- Initial release
- Generate Bruno API collections from Laravel routes
- Support for Laravel 10, 11, and 12
- Route filtering by middleware, prefix, and name patterns
- FormRequest body inference with nested and array field support
- Multiple grouping strategies (prefix, controller, tag)
- Environment file generation (Local, Staging, Production)
- Authentication configuration (Bearer, Basic, OAuth2)
- PHPDoc documentation extraction
- Test and script generation support
- Atomic file writing with backup and rollback
- Dry-run mode for preview
- Comprehensive configuration system
- Full type safety with strict types
- PSR-12 code style
- PEST test suite
- PHPStan level 8 static analysis

### Features
- `php artisan bruno:generate` - Generate Bruno collection
- `php artisan bruno:clear` - Clear generated collection
- Deterministic output with stable sorting
- Git-friendly .bru file format
- Descriptive file naming (method-resource.bru)
- Support for route parameters ({id} → {{id}})
- Query parameter generation
- Default headers configuration
- Multiple authentication modes
- Configurable grouping and sorting
