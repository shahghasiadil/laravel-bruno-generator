# Changelog

All notable changes to `laravel-bruno-generator` will be documented in this file.

## [Unreleased]

### Added
- Route `where()` constraints now inform path parameter examples: numeric
  constraints (`[0-9]+`, `\d+`) produce `1`, alternation constraints
  (`(daily|weekly|monthly)`) produce the first option, UUID-shaped
  constraints produce a sample UUID, and alphabetic constraints produce a
  slug-like example — falling back to the existing name-based heuristic when
  the constraint isn't recognized
- FormRequest `in:a,b,c` rules now produce the first allowed value as the
  example, instead of a generic string
- FormRequest `between:min,max` rules on integer/numeric fields now use
  `min` as the example value, same as `min:`
- FormRequest bodies with a `file` or `image` rule on any field now
  correctly generate a `multipart-form` body instead of JSON
- Secret environment variables: names listed in `secrets.variable_names`
  (default `['authToken']`), or marked `'secret' => true` per-variable, are
  never written to disk. `.bru` output lists them by name only in a
  `vars:secret` block; YAML output sets `secret: true` with an empty value
- `@description` support on environment variables (`.bru` `@description('''...''')`
  annotations / YAML `description:` field), sourced from the new array shape
  for environment variable config entries (`['value' => ..., 'description' => ...]`)
- `bruno_compatibility` config (`v3` | `v4`, default `v4`) gates the
  `@description` annotation, which Bruno 3.x's `.bru` parser doesn't
  recognize; set to `v3` to suppress it for collections that need to open in
  older Bruno
- New `EnvironmentVariable` DTO carrying name/value/secret/description
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
- `.bru` serializer had no case for `multipart-form` bodies and silently
  produced an empty body block for any file/image upload endpoint
- Route parameters had no way to carry a `where()` constraint at all — the
  captured value was always just the parameter's own name
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
- Default `output_format` changed from `bru` to `yaml`, matching Bruno's own
  default since v3.1
- `authToken` is now treated as a secret environment variable by default
  (see `secrets.variable_names`), so freshly generated collections no longer
  write it to disk even as an empty placeholder
- `EnvironmentConfig::$variables` changed from `array<string, string>` to
  `array<int, EnvironmentVariable>`; `FormatSerializerInterface::serializeEnvironment()`
  updated to match
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
