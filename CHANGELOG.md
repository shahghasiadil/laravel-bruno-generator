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
- Real Bruno path parameters: `:id` URLs plus a `params:path` block, controlled
  by a new `request_generation.path_param_style` config key (`colon` default,
  `double_brace` to keep the previous `{{id}}` behavior)
- Query parameter generation from FormRequest rules on GET/HEAD routes
- Request tags are now written to `meta.tags` (.bru) / `info.tags` (YAML)
- Collection-level auth block (`collection.bru`) that protected requests
  point at via `auth: inherit`, instead of repeating credentials in every
  file. Controlled by `auth.inherit_from_collection` (default `true`)
- `AuthType` gained `inherit`, `apikey`, `oauth1`, `ntlm`, `wsse`, and
  `akamai-edgegrid` cases to match Bruno's supported auth modes

### Fixed
- `settings` block was silently dropped from every request when
  `CollectionOrganizerService` reassigned sequence numbers after sorting
- YAML output did not conform to the OpenCollection spec: headers/params
  were maps instead of arrays, bodies used `{mode, json}` instead of
  `{type, data}`, auth was nested instead of flat, and scripts used a
  non-existent `runtime.script` shape instead of `runtime.scripts[]`
- Auth was applied to every request regardless of middleware; routes without
  an auth middleware now correctly generate no auth block
- Query parameter generation was a stubbed no-op despite being documented

### Changed
- YAML file extension changed from `.yaml` to `.yml` to match Bruno's
  OpenCollection convention
- Documentation length limit now only applies to .bru format
- Refactored `BrunoSerializerService` to use factory pattern
- Enhanced `RouteNormalizerService` with format-aware documentation extraction
- Updated `BrunoGenerateCommand` with format option
- File extensions now determined dynamically based on output format
- Renamed `AuthType::AWS_SIG_V4` value from `aws-sig-v4` to `awsv4` to match
  Bruno's auth type identifier

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
