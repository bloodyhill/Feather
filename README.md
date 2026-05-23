<div align="center">

<img src="assets/icons/Feather.png" alt="Feather Performance" width="160" height="160" />

# Feather Performance

**A performance plugin for WordPress sites built with Elementor.**

[**featherplugin.com**](https://featherplugin.com/) &nbsp;·&nbsp; [WordPress.org listing](https://wordpress.org/plugins/feather-performance/) &nbsp;·&nbsp; [Report an issue](https://github.com/featherr/feather-performance/issues)

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
<<<<<<< HEAD
[![Version](https://img.shields.io/badge/version-0.2.9-e37339.svg)](feather-performance.php)
=======
[![Version](https://img.shields.io/badge/version-0.2.7-orange.svg)](feather-performance.php)
>>>>>>> 03b86e818577993781498fb2034fde8a05ed608a

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

1. Download `feather-performance.zip` from the [Releases](https://github.com/bloodyhill/Feather/releases) page.
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

## [0.2.4] — 2026-05-11

### Fixed
- **`BelowFoldRenderer` no longer carpet-bombs every section with `content-visibility:auto`.** Production A/B testing isolated this optimizer as the sole cause of a CLS spike from 0.019 to 0.908. Root cause: the optimizer auto-applied `content-visibility:auto` with a fixed `contain-intrinsic-size: 0 800px` placeholder to every second-and-onwards `.elementor-section` / `.e-con` / `.wp-block-group` / `article`. The 800px placeholder almost never matched the real section height, and every mismatch registered as a layout shift Lighthouse counted.

### Changed
- **`f.media.below_fold_render` is now opt-in per section.** Users add a `feather-cv` class in Elementor → Advanced → CSS Classes. Sized variants `feather-cv-300` / `400` / `500` / `600` / `700` / `900` / `1000` / `1200` / `1500` / `2000` set a matching intrinsic-size placeholder so the section reserves its real height. Plain `feather-cv` defaults to 800px. Users who had the toggle on and were silently hitting the CLS regression keep their toggle state but stop hitting the bug; the optimization returns when they opt in deliberately.

## [0.2.3] — 2026-05-11

### Added
- **`ElementCacheForcer` optimizer (gated).** New feature `f.elementor.element_cache`. `pre_option_*` filter forces Elementor's `e_element_cache` experiment active so each widget's rendered HTML is cached by settings hash. Static widgets (Heading, Image, Text, Button, Spacer, Divider, Icon) skip `render_content()` entirely on cache hits. Targets the dominant Elementor TTFB cost — per-widget render execution — that A/B testing on jawlah.co isolated at ~3.7s per request. Pages with many widgets typically drop from 2-3s renders to 300-500ms. Default-off because custom widgets that read dynamic data inside `render_content()` without declaring it can serve stale output.

### Changed
- **`f.elementor.css_print_external` is now default-ON for new installs.** The v0.2.1 default of OFF left fresh installs shipping ~700 KB of inlined CSS in every HTML response; defaulting on makes Elementor emit external cacheable `.css` files instead. Existing users keep their stored toggle state. Impact metadata bumped from MEDIUM to HIGH. Description now reminds users to run **Elementor → Tools → Regenerate Files & Data** after enabling.

## [0.2.2] — 2026-05-11

### Fixed
- **CLS regression from v0.2.1 `ExperimentForcer`.** Production sites hit CLS spikes (0.482 measured) after v0.2.1 began force-activating `e_lazyload`, `e_optimized_assets_loading`, and `e_css_smooth_scroll`. `e_lazyload` lazy-loaded above-fold images on sites whose images lacked explicit `width`/`height` attrs; `e_optimized_assets_loading` requires `Elementor → Tools → Regenerate Files & Data` first or widgets render briefly unstyled. Reverted: `ExperimentForcer` now forces only the original 2 experiments (`e_font_icon_svg`, `e_optimized_markup`). v0.2.0 default behaviour restored for everyone.
- **`ImageDimensionsAdder` now also filters `elementor/widget/render_content`.** Elementor renders widget HTML through its own pipeline that bypasses `the_content`; the previous coverage missed Image and Image Box widget output entirely. Root-cause fix for the CLS path that v0.2.1's `e_lazyload` exposed.

### Added
- **`ExtraExperimentForcer` optimizer (gated, opt-in).** New feature `f.elementor.force_extra_experiments`. The three experiments that v0.2.1 force-enabled by default are now reachable behind an opt-in flag, with the description warning to regenerate Elementor files and pair with Auto-fix image dimensions before enabling.

## [0.2.1] — 2026-05-11

### Added
- **`CssPrintMethodEnforcer` optimizer.** New feature `f.elementor.css_print_external`. `pre_option_*` filter short-circuits `elementor_css_print_method` so Elementor emits cacheable external `.css` files per post instead of inlining widget CSS in every HTML response. No database write.
- **`FrontendLocalizeTrimmer` optimizer.** New feature `f.elementor.localize_trim`. Strips editor-only keys (`i18n`, `loaderUrl`, `beta`, non-edit `environmentMode`) from the `elementorFrontendConfig` JSON inlined on every page. Saves 1–5 KB per pageview.
- **`LoadingOverlayRemover` optimizer.** New feature `f.elementor.loading_overlay`. Pure-CSS hide of `#elementor-loading` / `.elementor-loading-overlay` at `wp_head:0`. On Feather-optimized sites the page paints fully before JS runs, so the overlay only delays perceived first paint.
- **`CssOverridesEmitter` optimizer.** New feature `f.elementor.css_overrides_cls`. Inlines ~2 KB of CSS on Elementor pages addressing CLS on image, image-box, video, counter, and carousel widgets; reserves a 16:9 ratio for video embeds; respects `prefers-reduced-motion`; strips interactive widgets from print output.
- **`WidgetLazyInit` optimizer (gated).** New feature `f.elementor.widget_lazy_init`. IntersectionObserver-driven init for Swiper carousels, animated counters, Vimeo/YouTube iframes, and entrance animations. Above-fold widgets boot immediately; below-fold wait until the section enters the viewport. Ships `assets/js/widget-lazy-init.js`. Companion to `JsDeferer` — that defers script parse, this defers the work the script does.

### Fixed
- **`ElementorPageDetector` no longer keeps Elementor assets on feeds, attachments, and 404s.** The previous behaviour returned `true` (keep assets) for any request with `get_queried_object_id() <= 0`, which leaked the entire Elementor frontend bundle onto RSS feeds, attachment pages, and 404 templates. Now explicitly returns `false` for `is_feed()`, `is_attachment()`, and `is_404()`. Archive / search behavior unchanged.
- **`GoogleFontsDisabler` now also dequeues Kit-level font handles.** `elementor-google-fonts` and `e-google-fonts` (introduced in Elementor 3.8+) bypass the `elementor/frontend/print_google_fonts` filter; the optimizer now late-dequeues and deregisters them at `wp_print_styles:100`.
- **`BelowFoldRenderer` selector now matches Flexbox Container.** The previous selector targeted only `.elementor-section` (legacy Section/Column layout) and missed `.e-con` (Flexbox Container, default on Elementor 3.6+).

### Changed
- **`ExperimentForcer` extended.** Now force-activates `e_optimized_assets_loading`, `e_lazyload`, and `e_css_smooth_scroll` in addition to the original `e_font_icon_svg` and `e_optimized_markup`. *(Reverted in v0.2.2 — see below.)*

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
