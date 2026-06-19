# MCP Abilities - Cache Enabler

Cache Enabler diagnostics and cache management for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-cache-enabler)](https://github.com/bjornfix/mcp-abilities-cache-enabler/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Tested up to:** 7.0
**Stable tag:** 1.0.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

Cache Enabler diagnostics and cache management for WordPress via MCP.

This plugin is part of the Devenia MCP abilities ecosystem. It gives an MCP-capable agent a focused, authenticated way to inspect Cache Enabler state, clear stale cached pages, and verify cache behavior through WordPress.

**Example:** "Clear the stale Cache Enabler version of this page and verify what cache layer is serving it." - The agent can inspect the site, call the relevant ability, and return the result without making the human click through wp-admin for every step.

## The Real Workflow

In practice, the human should not have to memorize every ability name.

The normal pattern is:

1. install the base MCP stack
2. install only the add-ons the site actually needs
3. let the agent discover the available abilities
4. give the agent a clear task with boundaries
5. verify the result in WordPress

The human's job is mostly to describe the goal.
The agent's job is to figure out the mechanics.

## Why This Feels Different

Most WordPress automation still leaves the repetitive part to the human.

This plugin is different because the agent can act inside the site through a narrow, authenticated ability surface:

- inspect current Cache Enabler state before changing anything
- run the specific cache action needed for the task
- return structured results that are easy to verify
- keep the workflow inside WordPress instead of a separate checklist

That changes the experience from:

- `Here is what you should do in wp-admin`

to:

- `Tell the agent what needs doing, and let it carry out the work`

## Before vs After

### Before

- ask the AI what to do
- manually open WordPress cache settings
- clear broad caches because the stale layer is unclear
- recheck pages by hand

### After

- tell the agent what page or cache problem needs fixing
- let it inspect Cache Enabler status and cached files
- let it purge exactly the relevant scope
- verify the result and move on

## Who It Is For

This is a good fit for:

- agencies managing WordPress sites with AI-assisted maintenance
- operators who want agents to do real WordPress work instead of producing instructions
- teams already using MCP Expose Abilities
- sites using Cache Enabler where stale HTML can hide fresh plugin, theme, or content changes

It is especially useful when cache state needs to be inspected and fixed repeatedly across multiple WordPress sites.

## Documentation

Start with the main plugin page and base stack documentation:

- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)
- [Install Order and Dependencies](https://github.com/bjornfix/mcp-expose-abilities/wiki/Install-Order-and-Dependencies)

If you are using an AI agent, the simplest instruction is often just:

- `Read https://github.com/bjornfix/mcp-expose-abilities and figure out the stack before making changes.`

## Start Here

If you are new to the stack, use this order:

1. Install **Abilities API**.
2. Install **MCP Adapter**.
3. Install **MCP Expose Abilities**.
4. Install **Cache Enabler**.
5. Install **MCP Abilities - Cache Enabler**.
6. Confirm the new abilities appear in discovery.
7. Give the agent a clear task that uses this add-on.

If you skip base-stack verification and start with add-ons immediately, troubleshooting gets harder than it needs to be.

## Abilities (13)

| Ability | Description |
|---------|-------------|
| `cache-enabler/status` | Inspect plugin state, version, `WP_CACHE`, backend files, cache directory stats, and settings |
| `cache-enabler/get-settings` | Read Cache Enabler settings |
| `cache-enabler/update-settings` | Update known Cache Enabler settings, dry-run by default |
| `cache-enabler/purge-all` | Clear the complete Cache Enabler cache |
| `cache-enabler/purge-site` | Clear the current site's Cache Enabler cache |
| `cache-enabler/purge-expired` | Clear expired cached files |
| `cache-enabler/purge-url` | Clear one same-site URL |
| `cache-enabler/purge-post` | Clear a post or page by post ID |
| `cache-enabler/diagnose-page` | Fetch a same-site URL and report cache headers/signatures |
| `cache-enabler/list-cached-urls` | List cached files for diagnostics |
| `cache-enabler/set-enabled` | Activate or deactivate Cache Enabler |
| `cache-enabler/refresh-backend` | Rebuild Cache Enabler backend files/settings when supported |
| `cache-enabler/delete-cache-directory` | Emergency path-guarded cache directory cleanup with explicit confirmation |

## Usage Examples

### Inspect Cache Enabler

```json
{
  "ability_name": "cache-enabler/status",
  "parameters": {}
}
```

### Purge one URL

```json
{
  "ability_name": "cache-enabler/purge-url",
  "parameters": {
    "url": "/important-page/"
  }
}
```

### Preview a settings change

```json
{
  "ability_name": "cache-enabler/update-settings",
  "parameters": {
    "dry_run": true,
    "settings": {
      "cache_expiry_time": 24
    }
  }
}
```

## Changelog

### 1.0.0
- Initial release with Cache Enabler status, settings, cache stats, full/site/expired/URL/post purges, page diagnosis, cached-file listing, enable/disable, backend refresh, and path-guarded emergency cache directory cleanup.

## Contributing

PRs welcome. Keep changes focused on the plugin's WordPress ability surface and preserve authenticated, explicit workflows.

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/mcp-expose-abilities/#add-ons)
- [MCP Expose Abilities](https://devenia.com/plugins/mcp-expose-abilities/)
- [GitHub Releases](https://github.com/bjornfix/mcp-abilities-cache-enabler/releases)

## Star and Share

If this plugin saves you time or makes WordPress cache maintenance easier to verify, please:

- star the repo
- share it with people running WordPress sites
- point them to the main plugin page so they can see what the ecosystem can actually do

Why do it?

Because agent-friendly open WordPress tooling helps more of the boring but important work get done.
