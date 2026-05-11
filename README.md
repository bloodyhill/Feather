<div align="center">

<img src="assets/icons/Feather.png" alt="Feather Performance" width="128" height="128" />

# Feather Performance

**A performance plugin for WordPress sites built with Elementor.**

[**featherplugin.com**](https://featherplugin.com/) &nbsp;·&nbsp; [WordPress.org listing](https://wordpress.org/plugins/feather-performance/) &nbsp;·&nbsp; [Report an issue](https://github.com/featherr/feather-performance/issues)

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-0.2.3-orange.svg)](feather-performance.php)

</div>

---

## What Feather does

Feather reads your saved Elementor data to learn which widgets and assets each page actually uses, then removes the rest. Around that scan, it also turns off WordPress features most sites never use, throttles the parts that run on every admin tick, and cleans rows out of the database that accumulate over time.

Everything Feather does is reversible from the dashboard. Nothing runs against your site from outside it.

Project website: **[featherplugin.com](https://featherplugin.com/)**.

---

## Concretely, on activation Feather can:

### Stop things WordPress runs that most sites don't need

- Disable the WP emoji loader (`wp-emoji-release.min.js` and the inline detection script)
- Disable oEmbed discovery and the embed JS bundle
- Disable `jquery-migrate` on the frontend
- Remove `<meta name="generator">` (WordPress version disclosure)
- Remove RSD link, Windows Live Writer manifest, shortlink, REST link, and wlwmanifest from `<head>`
- Disable XML-RPC pingback advertising
- Strip cache-busting query strings (`?ver=…`) from static asset URLs
- Turn off automatic feed links Elementor sites rarely use

### Throttle things that run on every request or tick

- Slow the admin heartbeat from 15s to 60s (configurable)
- Disable heartbeat entirely on the dashboard and on post-edit screens when you don't need autosave broadcasts
- Limit post revisions (configurable cap; default 5)
- Increase autosave interval

### Trim Elementor's asset load

These activate **only** after a site scan confirms zero usages site-wide for each asset:

- Dequeue Elementor's frontend bundle on pages that aren't built with Elementor
- Dequeue the Font Awesome 4 shim when no widget references it
- Dequeue eicons when no widget references it
- Dequeue Google Fonts loaded by Elementor when no widget uses a Google font
- Remove Elementor's DOM-cruft (empty wrapper divs, redundant inline styles)
- Defer non-critical JavaScript

### Improve media and rendering

- Add `loading="lazy"` to iframes that don't already have it
- Auto-fix `<img>` tags missing `width`/`height` attributes (prevents layout shift)
- Apply `content-visibility: auto` to off-screen sections so the browser can skip their work

### Clean the database

One-click actions from the **Database** tab:

- Delete expired transients (rows where `option_name LIKE '_transient_timeout_%'` and the value is in the past)
- Prune orphaned Elementor revisions beyond a configurable keep count
- Flush oEmbed cache rows (`_oembed_*` post meta)
- Audit autoloaded options — flag any single option with autoload=`yes` over a configurable size threshold

### Measure what changed

- Take a page-weight snapshot of any URL from your site (bytes transferred, asset count, HTTP request count, response time)
- Compare snapshots over time on the dashboard
- Store the last 90 days of measurements in a custom table; older rows are pruned automatically

### Stay off the network

- **Zero outbound HTTP calls** in the default configuration
- No telemetry, no analytics, no remote check-in pings
- The scanner reads `_elementor_data` post meta from your local database only
- Page-weight measurements are same-origin loopback `GET` requests to your own URLs via `wp_remote_get()`, triggered explicitly from the dashboard
- No third-party CDNs, no Google Fonts, no remote JS loaded by the admin UI — the React bundle ships pre-built and only depends on WordPress's own bundled scripts

---

## Requirements

- WordPress **6.0** or newer
- PHP **7.4** or newer
- Elementor (free or Pro) installed for the asset-aware features

---

## Installation

### From WordPress.org

1. In your WordPress admin: **Plugins → Add New**.
2. Search for **Feather Performance**.
3. Click **Install Now**, then **Activate**.
4. Open **Feather** in the admin sidebar, run the welcome scan, and apply a recommended preset.

### From a release zip

1. Download `feather-performance.zip` from the [Releases](https://github.com/featherr/feather-performance/releases) page.
2. In WordPress admin: **Plugins → Add New → Upload Plugin**.
3. Choose the zip, click **Install Now**, then **Activate**.

### From source

```bash
git clone https://github.com/featherr/feather-performance.git
cd feather-performance/ui
npm install
npm run build
```

Then symlink or copy the repo root into `wp-content/plugins/feather-performance/`.

---

## Plays well with caching and image plugins

Feather detects these on activation and refuses to enable any optimization they already handle:

- WP Rocket
- LiteSpeed Cache
- SiteGround Optimizer
- W3 Total Cache
- Imagify
- Smush

So you can leave your existing caching plugin in place. Feather only fills the gaps it leaves.

Multisite is supported per-site. Network-level controls are planned.

---

## Security and data handling

- All admin pages and REST endpoints gate on a custom `manage_feather` capability, mapped to `manage_options` via the `user_has_cap` filter
- Every REST endpoint implements `permission_callback`
- Input is sanitized (`sanitize_text_field`, `absint`, enum allow-lists); output is escaped (`esc_html__`, `esc_url`, `esc_attr`)
- Every `$wpdb` query with user-supplied values uses `prepare()`
- Direct database operations (table truncate, OPTIMIZE TABLE, DELETE for cleanup) are scoped to plugin-owned tables and known WordPress core tables

### Custom tables created on activation

- `wp_feather_scan` — one row per Elementor-built post, holding widget types, asset handles, and settings flags
- `wp_feather_metrics` — page-weight measurement history; auto-pruned to 90 days

Both tables are dropped by `uninstall.php` when you delete the plugin from WP admin.

---

## Development

### Repository layout

```
.
├── feather-performance.php   # Plugin bootstrap
├── uninstall.php             # Drops custom tables on plugin deletion
├── src/                      # PHP source, PSR-4 autoloaded under Feather\
│   ├── Optimizers/           # Each optimization above lives here
│   ├── Scanner/              # Per-page Elementor data scanner
│   ├── Metrics/              # Page-weight measurement
│   ├── Db/                   # Database hygiene tools
│   ├── Rest/                 # REST endpoints
│   ├── Admin/                # Admin pages and asset registration
│   └── …
├── ui/                       # React admin app (TypeScript)
│   ├── src/
│   └── package.json
├── assets/admin/             # Compiled React bundle (committed)
├── assets/icons/             # Plugin icons
├── assets/img/               # Brand marks
└── languages/                # Translation files
```

### Building the admin UI

```bash
cd ui
npm install
npm run build        # production build → ../assets/admin/
npm run start        # watch mode for development
npm run lint:js      # ESLint via @wordpress/scripts
npm run lint:css     # Stylelint via @wordpress/scripts
npm test             # Jest unit tests via @wordpress/scripts
```

### PHP standards

- `declare( strict_types=1 );` in every file
- PSR-4 autoloading under the `Feather\` namespace via `src/Autoloader.php`
- No Composer runtime dependencies — no `vendor/` directory ships

---

## Contributing

Issues and pull requests are welcome.

1. Fork the repository and branch from `main`.
2. Match the surrounding code style.
3. Run the linters: `cd ui && npm run lint:js && npm run lint:css`.
4. Open a pull request describing the change.

For larger changes, please open an issue first.

---

## Changelog

See [`readme.txt`](readme.txt) for the WordPress.org changelog, or the [Releases](https://github.com/featherr/feather-performance/releases) page for tagged builds.

## [0.2.0] — 2026-05-11

### Added
- **Elementor 4.0.x atomic widget support.** Scanner detects atomic widgets (`e-heading`, `e-button`, `e-image`, `e-paragraph`, `e-divider`, `e-svg`, `e-self-hosted-video`, `e-youtube`, `e-component`) and atomic element types (`e-flexbox`, `e-div-block`, `e-tabs`, `e-tabs-menu`, `e-tab`, `e-tabs-content-area`, `e-tab-content`) inside `_elementor_data`.
- **`AtomicAssetGate` optimizer (gated).** New feature `f.elementor.skip_atomic_chain`. On Elementor pages that contain only legacy widgets, dequeues the v4 atomic widget handler bundle — `elementor-v2-widgets-frontend`, `elementor-v2-frontend-handlers`, `elementor-v2-alpinejs`, `elementor-tabs-handler`, `elementor-youtube-handler`. Auto-bails on Elementor Pro to protect theme-builder injected atomic widgets.
- **`ElementorHostFirewall` optimizer (gated).** New feature `f.elementor.network_firewall`. Hooks `pre_http_request` at priority 1 and refuses every outbound request whose host matches `(^|\.)elementor\.com$`, unless the request is user-initiated (AJAX non-heartbeat, REST, editor page load). Catches every background telemetry / marketing / discovery vector at the network layer — including future ones Elementor may add. Coexists with `ApiFetcherDisabler`.
- **Master "Pause all optimizations" toggle.** New `optimizers_paused` top-level setting on `SettingsRepository`. When true, `Plugin::apply_optimizers()` returns before registering any optimizer hooks. Exposed via a `<PauseAllCard />` component at the top of the React Dashboard, with optimistic-update + rollback behavior.
- **`PerPageAssetTrimmer`** now drops `elementor-tabs-handler` and `elementor-youtube-handler` per-page when the page's scan row doesn't list the corresponding atomic element.
- **`UnusedWidgetBundleStripper`** new branch deregisters the entire v4 chain at `wp_default_scripts` when `ScanRepository::has_any_atomic_widgets_site_wide()` returns false and Elementor Pro is not active.
- **`WidgetAssetMap::introspection_failures()`** / **`reset_introspection_failures()`** static accessors — count and reset rejected handle entries per request.
- **`ScanRepository::flags_for_post()`** — symmetric to `handles_for_post()`, returns decoded `settings_flags` map for a single post.
- **`ScanRepository::has_any_atomic_widgets_site_wide()`** — site-wide accessor backed by the existing aggregate cache.

### Changed
- Default theme switched from `system` to `light` on fresh installs. Existing users keep their stored preference; the `system` option remains selectable.
- `feather_aggregated_scan_verdicts` aggregate now carries `has_atomic_widgets_anywhere: bool` field.
- Scan rows' `settings_flags` JSON now carries `has_atomic_widgets: bool`.
- `WidgetAssetMap::introspect()` now validates each declared handle against `/^[a-z0-9_-]+$/`. Garbage entries (e.g., the class-FQN-as-error strings some third-party widgets leak from broken `get_*_depends()` methods) are filtered out and the rejection counter is incremented.
- `WidgetAssetMap` now strips `swiper` and `e-swiper` from any `wp-widget-*` introspection result — works around an upstream bug in Elementor's `Widget_WordPress` class that incorrectly declares those deps for every WordPress sidebar widget.
- `WidgetAssetMap::defaults()` overlay adds `e-tabs => ['elementor-tabs-handler']`.

### Fixed
- `MetricsEndpoint::capture()` now re-reads via `MetricsRepository::latest()` after save, so the response carries `recorded_at`. Fixes the Dashboard "Last measured" tile showing "Never" immediately after running a measurement.
- Dashboard "Pause all optimizations" card now renders with proper spacing — label and description stack on separate lines, toggle sits on the right with a flex layout, mobile breakpoint at 600px drops the toggle below the text. Warning gradient applied when paused (light + dark mode variants).

### Developer
- New file: `src/Optimizers/Elementor/AtomicAssetGate.php`
- New file: `src/Optimizers/Elementor/ElementorHostFirewall.php`
- New file: `ui/src/components/PauseAllCard.tsx`
- Plugin orchestrator (`Plugin::apply_optimizers`) now resolves `SettingsRepository` once at the top and consults it for the pause flag before any feature iteration.


## [0.1.0]

Initial release.

---

## License

Feather Performance is released under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Copyright (C) 2026  Feather

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.
```

---

<div align="center">

Built by [Feather](https://featherplugin.com/) — **[featherplugin.com](https://featherplugin.com/)**

</div>
