# MCP Abilities - Cache Enabler

WordPress Abilities API plugin for operating Cache Enabler through the WordPress MCP proxy.

## Abilities

- `cache-enabler/status` - plugin state, version, `WP_CACHE`, backend file state, cache directory stats, and current settings.
- `cache-enabler/get-settings` - current Cache Enabler settings.
- `cache-enabler/update-settings` - update known Cache Enabler settings. Defaults to `dry_run: true`.
- `cache-enabler/purge-all` - clear the complete Cache Enabler cache through Cache Enabler's public hook.
- `cache-enabler/purge-site` - clear the current site's Cache Enabler cache.
- `cache-enabler/purge-expired` - clear expired cached files.
- `cache-enabler/purge-url` - clear a same-site URL.
- `cache-enabler/purge-post` - clear a post/page by post ID.
- `cache-enabler/diagnose-page` - fetch a same-site URL and report cache-related headers/signatures.
- `cache-enabler/list-cached-urls` - list cached files for diagnostics.
- `cache-enabler/set-enabled` - activate or deactivate Cache Enabler.
- `cache-enabler/refresh-backend` - ask Cache Enabler to rebuild backend files/settings.
- `cache-enabler/delete-cache-directory` - emergency fallback that deletes only `wp-content/cache/cache-enabler` after `confirm: true`.

## Requirements

- WordPress 6.9+
- PHP 8.0+
- Abilities API / MCP exposure plugin active
- Cache Enabler installed for cache-specific operations

## Safety

The plugin requires `manage_options` for all abilities. URL purge and page diagnostics accept only same-site absolute or root-relative URLs. The emergency directory deletion ability refuses paths outside `wp-content/cache/cache-enabler`.

## Examples

```bash
npm run http:call -- execute_ability --site example --ability cache-enabler/status --params '{}'
npm run http:call -- execute_ability --site example --ability cache-enabler/purge-url --params '{"url":"/"}'
npm run http:call -- execute_ability --site example --ability cache-enabler/update-settings --params '{"dry_run":false,"settings":{"cache_expiry_time":24}}'
```

