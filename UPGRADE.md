# Upgrade Guide

## Upgrading to spec-conformant YAML, real path params, and collection-level auth

This release fixes YAML output that did not conform to Bruno's OpenCollection
spec, and changes two defaults that were previously incorrect or silently
broken. If you regenerate your collection after upgrading, expect the
following differences:

### Breaking changes

1. **YAML file extension is now `.yml`, not `.yaml`.** Bruno's OpenCollection
   format uses `.yml`. Delete your old `.yaml` output directory and
   regenerate; Bruno will not recognize the old files as the same requests.

2. **YAML request/environment shape changed to match OpenCollection.**
   Headers and params are now arrays of `{name, value, ...}` objects instead
   of maps, bodies use `{type, data}` instead of `{mode, json}`, auth is a
   flat `{type, ...credentials}` object (or the scalar `inherit`), and
   scripts/tests live under `runtime.scripts[]`. Previously generated YAML
   collections only partially loaded in Bruno; regenerate to get a working
   collection.

3. **Path parameters now default to `:id` + a `params:path` block**, instead
   of rewriting `{id}` to `{{id}}`. This is the convention Bruno's UI expects
   for editable path parameters. To keep the old behavior, set:
   ```php
   // config/bruno-generator.php
   'request_generation' => [
       'path_param_style' => 'double_brace',
   ],
   ```

4. **Protected requests now use `auth: inherit`** and point at a new
   `collection.bru` file, instead of repeating full credentials in every
   request file. Set `auth.inherit_from_collection` to `false` to restore the
   previous per-request inline auth behavior. This currently applies to
   `.bru` output only — YAML collections still inline auth per request, since
   the collection-root YAML schema (`opencollection.yml`) isn't implemented
   yet (see `ROADMAP.md`).

5. **Routes without an auth middleware no longer get an auth block.**
   Previously, every request got an auth block whenever `auth.mode` was not
   `none`, regardless of whether the route actually required authentication.
   Public routes are now correctly generated without one.

6. **`AuthType::AWS_SIG_V4`'s value changed from `aws-sig-v4` to `awsv4`**,
   matching Bruno's own identifier. Only relevant if you referenced the raw
   string value directly.

### Non-breaking additions

- Query parameters are now generated from FormRequest rules on GET/HEAD
  routes (`request_generation.generate_query_params`, already documented but
  previously a no-op).
- Request tags are now written to the output (`meta.tags` / `info.tags`).
- The `settings` block (timeout, redirects, etc.) is no longer silently
  dropped after route sorting.

## Upgrading to YAML Format Support

### Backward Compatibility

All existing collections continue to work. YAML format support is opt-in with no breaking changes.

### Using YAML Format

Add to your `.env` file:
```env
BRUNO_OUTPUT_FORMAT=yaml
```

Or use command line options:
```bash
php artisan bruno:generate --format=yaml
```

### Benefits of YAML Format

1. **Full Documentation** - No 200 character limit on PHPDoc extraction
2. **Better Git Diffs** - YAML provides cleaner diffs than .bru format
3. **IDE Support** - Syntax highlighting in all editors
4. **Manual Editing** - Easier to edit by hand if needed

### Migration Steps

If you want to migrate from .bru format to YAML format:

1. **Update your environment**:
   ```env
   BRUNO_OUTPUT_FORMAT=yaml
   ```

2. **Regenerate your collection**:
   ```bash
   php artisan bruno:generate --format=yaml
   ```

3. **Open in Bruno**:
   - Open the regenerated collection in Bruno
   - Verify all requests load correctly

### Configuration Changes

The package now supports a new configuration option:

```php
// config/bruno-generator.php
'output_format' => env('BRUNO_OUTPUT_FORMAT', 'bru'),
```

### Command Options

The `bruno:generate` command now accepts a format option:

```bash
# Generate with YAML format
php artisan bruno:generate --format=yaml

# Generate with .bru format (default)
php artisan bruno:generate --format=bru
```

### What's New

- **OpenCollection YAML Format** - Generate collections in YAML format
- **Full Documentation Support** - No character limits when using YAML format
- **Format-Aware Documentation** - PHPDoc extraction respects the output format
- **Backward Compatible** - Default behavior unchanged, .bru format still works

### Breaking Changes

None. This is a fully backward-compatible update.
