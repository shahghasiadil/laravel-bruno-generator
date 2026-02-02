# Upgrade Guide

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
