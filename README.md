<div align="center">

# Feather Performance

**A performance plugin for WordPress sites built with Elementor.**

Trims Elementor's asset load, throttles WordPress overhead, cleans the database, and surfaces per-page measurements — all from one dashboard.

[**featherplugin.com**](https://featherplugin.com/) &nbsp;·&nbsp; [WordPress.org listing](https://wordpress.org/plugins/feather-performance/) &nbsp;·&nbsp; [Report an issue](https://github.com/featherr/feather-performance/issues)

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-0.1.0-orange.svg)](feather-performance.php)

</div>

---

## Why Feather

Most performance plugins are content-blind: they minify, defer, and cache without knowing what your pages actually use. Feather reads each page's saved Elementor data, builds a map of which widgets and assets are in use, and only strips what's safe to strip. The result: smaller pages, fewer requests, no broken layouts.

- **Per-page asset awareness.** A background scan inspects your saved Elementor data and produces a widget/asset map. Asset-stripping optimizations only unlock when the scan confirms zero usages site-wide.
- **Safe defaults.** Conservative optimizations are active on first activation — skip Elementor's frontend bundle on non-Elementor pages, defer JavaScript, strip cache-busting query strings, throttle the admin heartbeat, disable WP emojis and embeds.
- **Measured impact.** Page-weight snapshots track bytes, asset count, and HTTP request count against your real frontend over time.
- **Database tooling.** One-click cleanup for expired transients, orphaned Elementor revisions, and oEmbed cache rows. Autoload audit flags any oversized always-loaded options.
- **Privacy by default.** No telemetry. Scans run on your server. Page-weight measurements hit your own URLs. Nothing leaves your site without explicit opt-in.

Learn more at **[featherplugin.com](https://featherplugin.com/)**.

---

## What Feather optimizes

| Category | Examples |
|----------|---------|
| **Elementor frontend assets** | Font Awesome 4 shim, eicons, Google Fonts, JS defer, asset gating on non-Elementor pages, DOM-cruft removal |
| **WordPress hygiene** | Emojis, embeds, jquery-migrate, heartbeat throttle, head cleanup, version disclosure |
| **Lazy loading & media** | Iframe lazy attributes, image-dimension auto-fix, content-visibility for off-screen sections |
| **Database** | Transient cleanup, Elementor revision pruning, oEmbed cache flush, autoload audit |
| **Reporting** | Page-weight history, site-scan results, database health score |

---

## Requirements

- WordPress **6.0** or newer
- PHP **7.4** or newer
- Elementor (free or Pro) installed for the asset-aware features

---

## Installation

### From WordPress.org (recommended)

1. In your WordPress admin, go to **Plugins → Add New**.
2. Search for **Feather Performance**.
3. Click **Install Now**, then **Activate**.
4. Open **Feather** in the admin sidebar, run the welcome scan, and apply a recommended preset.

### From a release zip

1. Download the latest `feather-performance.zip` from the [Releases](https://github.com/featherr/feather-performance/releases) page.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip and click **Install Now**, then **Activate**.

### From source (developers)

```bash
git clone https://github.com/featherr/feather-performance.git
cd feather-performance/ui
npm install
npm run build
```

Then symlink or copy the repository root into `wp-content/plugins/feather-performance/`.

---

## Compatibility

Feather auto-detects the following plugins and disables overlapping optimizations so it never duplicates work another plugin already handles:

- WP Rocket
- LiteSpeed Cache
- SiteGround Optimizer
- W3 Total Cache
- Imagify
- Smush
- Most other caching and image-optimizer plugins

Multisite is supported in compatible mode (per-site configuration). Network-level controls are planned for a later release.

---

## Privacy & security

- **No telemetry.** No outbound network calls in the default configuration.
- **Local scans only.** The site scanner reads `_elementor_data` post meta from the local database. It never makes external requests.
- **Same-origin measurements.** Page-weight measurements issue a single loopback `GET` to your own site's URL via `wp_remote_get()`, triggered explicitly from the dashboard.
- **Capability-gated.** All admin pages and REST endpoints gate on a custom `manage_feather` capability, mapped to `manage_options`.
- **Hardened.** All input sanitized, all output escaped, all `$wpdb` queries use `prepare()`.
- **No vendor lock-in.** All data lives in your database. Two custom tables (`wp_feather_scan`, `wp_feather_metrics`) are dropped on uninstall.

Full reviewer-facing security notes are kept in the upstream submission docs.

---

## Development

### Repository layout

```
.
├── feather-performance.php   # Plugin bootstrap
├── uninstall.php             # Drops custom tables on plugin deletion
├── src/                      # PHP source, PSR-4 autoloaded under Feather\
│   ├── Optimizers/           # Optimization implementations
│   ├── Scanner/              # Per-page Elementor data scanner
│   ├── Metrics/              # Page-weight measurement
│   ├── Db/                   # Database hygiene tools
│   ├── Rest/                 # REST endpoints
│   ├── Admin/                # Admin pages and asset registration
│   └── …
├── ui/                       # React admin app (TypeScript)
│   ├── src/                  # TypeScript source
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

- Strict types declared in every file (`declare( strict_types=1 );`).
- PSR-4 autoloading under the `Feather\` namespace via `src/Autoloader.php`.
- No Composer runtime dependencies — the plugin ships without a `vendor/` directory.

---

## Contributing

Issues and pull requests are welcome.

1. Fork the repository and create a feature branch from `main`.
2. Make your changes. Match the surrounding code style.
3. Run the linters: `cd ui && npm run lint:js && npm run lint:css`.
4. Open a pull request describing the change and why.

For larger changes, please open an issue first to discuss the approach.

---

## Changelog

See the **WordPress.org Changelog** in [`readme.txt`](readme.txt), or the [Releases](https://github.com/featherr/feather-performance/releases) page on GitHub for tagged builds.

### 0.1.0

Initial release.

---

## License

Feather Performance is free software, released under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html). See [`LICENSE`](LICENSE) for the full text.

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
