<div align="center">

<img src="assets/icons/Feather.png" alt="Feather Performance" width="160" height="160" />

# Feather Performance

**A performance plugin for WordPress sites built with Elementor.**

[**featherplugin.com**](https://featherplugin.com/) &nbsp;·&nbsp; [WordPress.org listing](https://wordpress.org/plugins/feather-performance/) &nbsp;·&nbsp; [Report an issue](https://github.com/featherr/feather-performance/issues)

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-0.2.9-e37339.svg)](feather-performance.php)

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

# Changelog

## 0.2.9 — May 23, 2026

### Fixed
- "Edit with Elementor" admin bar item is now reliably removed on Elementor 4.0.x.
- Duplicate telemetry toggles cleaned up. The three Elementor telemetry options no longer appear as individual cards on the Features page alongside the Dashboard "Block Elementor telemetry" composite — the composite toggle still reads and writes their individual states.
- Site Scan results converge live. Partial results now accumulate progressively during the scan and the final state lands without a manual page reload.

### Changed
- New brand mark. Refreshed feather logo across every surface, including the WordPress.org listing icon and banner.

---

## 0.2.8 — May 22, 2026

### Compatibility
- Tested up to WordPress 7.0 and Elementor 4.0.9. The scanner now recognises Elementor 4.0.x atomic Self-Hosted Video and the standalone Video element alongside the existing atomic set.

### Added
- **Block Elementor telemetry — Dashboard switch.** A single Dashboard toggle bundles tracker flags, the my.elementor.com phone-home (banners, what's-new feed, update prompts, upsells), and the AI editor bundle. Per-feature granularity remains on the Features page.
- **Remove "Edit with Elementor" from the admin bar.** New optimizer drops the floating link Elementor adds to the WordPress admin bar. Post-edit screen buttons still open the editor.
- **Background auto-rescan on post save.** When an Elementor page is saved, Feather refreshes that post's scan row in the background, so gated optimizations stay accurate without a manual rescan. 60-second per-post throttle.
- **Per-page feature overrides.** A sidebar meta box on the post edit screen lets editors disable individual Feather features on a single page without touching the sitewide setting. Wired into JS defer, the frontend asset gate, per-page asset trim, iframe lazy-load, image dimensions, and below-fold rendering.
- **Settings export / import.** Download your Feather configuration as JSON from Settings → Portability, then re-import on another site. Scan history and metrics history are not included in the export.
- **Core Web Vitals signals on the Dashboard.** The page-weight probe now records response time, a TTFB estimate, and a hero-image byte count for same-origin images. New tiles appear on the Dashboard whenever a measurement includes these signals.
- **Elementor 4.0.x Global Classes detection.** Gated optimizers now refuse to strip handles referenced by a Global Class.
- **Database cleanup expanded.** New tools for spam comments and auto-draft posts, batched in groups of 200 per click.
- **Per-context Heartbeat tuning.** The WP Heartbeat optimizer now exposes separate intervals for the block editor and other admin pages, with the public-frontend disable still default-on.

### Changed
- Consolidated Elementor experiments into a single feature card. Existing on/off state is preserved and sub-toggles remain available.
- Pause-all card copy clarified. The toggle now reads "Pause all optimizations for editing" with a "Resume optimizations" button in the paused state.
- Topbar brand mark now resolves the correct light/dark variant on the first paint, even when the OS color preference disagrees with the saved Feather theme.
- WordPress.org listing title sharpened to *Feather – Elementor Performance Optimizer | Asset Cleanup, Page Speed & Database Cleanup* for stronger search discovery.

---

## 0.2.7 — May 16, 2026

### Fixed
- Stale settings on object-cache hosts. Toggled optimizations could briefly show as "not applied" on sites running Redis, Memcached, or LiteSpeed object caches. Follow-up requests now read the canonical value immediately after every save.
- Page caches holding REST responses. Site Scan results and "Take measurement" output could appear stale until a full page reload on sites with LiteSpeed Cache, WP Rocket, or Cloudflare. Authenticated REST responses are no longer held by page caches.

### Changed
- In-WordPress plugin name shortened to **Feather** so the Plugins list and Updates screen show a clean brand name. The longer descriptive title remains on the WordPress.org listing for search visibility.

---

## 0.2.6 — May 15, 2026

### Fixed
- Plugins-list Settings link. The "Settings" link in the wp-admin Plugins row produced a "Sorry, you are not allowed to access this page" error. It now opens correctly.
- Site Scan refresh. The Scan view now refreshes results on any transition out of the running state, not only on completion.
- Take-measurement refresh. The Dashboard now re-reads the latest metrics from the server after capturing, so the "Last measured" tile and homepage-weight value update immediately.

### Added
- Review link in the admin. A persistent footer on every Feather admin page invites you to leave a review on WordPress.org when you find the plugin useful.

### Changed
- WordPress.org listing title rewritten to *Feather – Elementor Optimizer, Asset Cleanup, Page Speed, Database Tools* for search discoverability. The short tagline now reads *Strip unused Elementor assets per page, measure page weight, and clean your database from one dashboard.*
- Em dashes removed from the plugin header and readme for cleaner rendering across the WordPress.org listing and translation tooling.

---

## 0.2.5 — May 15, 2026

### Changed
- Inline CSS now follows the WordPress.org enqueue guidelines.

### Removed
- Placeholder feature toggles that had no implementation. Every shipped feature is now fully functional out of the box.
- Redundant translation loader. WordPress.org auto-loads translations for hosted plugins since WP 4.6.


## [0.2.4] — 2026-05-11

### Fixed
- **Below-fold rendering no longer causes massive layout shifts.** The previous version auto-applied `content-visibility:auto` with a fixed 800px placeholder to every section past the first, which almost never matched the real section height and registered as a layout shift.

### Changed
- **Below-fold rendering is now opt-in per section.** Add a `feather-cv` class in Elementor → Advanced → CSS Classes. Sized variants `feather-cv-300` / `400` / `500` / `600` / `700` / `900` / `1000` / `1200` / `1500` / `2000` reserve a matching placeholder so the section keeps its real height. Plain `feather-cv` defaults to 800px. Users who had the toggle on keep their toggle state but stop hitting the regression; the optimization returns when opted in deliberately.

---

## [0.2.3] — 2026-05-11

### Added
- **Element cache forcer (gated).** Forces Elementor's `e_element_cache` experiment active so each widget's rendered HTML is cached by its settings hash. Static widgets (Heading, Image, Text, Button, Spacer, Divider, Icon) skip rendering entirely on cache hits. Pages with many widgets typically drop from 2–3s renders to 300–500ms. Default-off because custom widgets that read dynamic data inside their render method without declaring it can serve stale output.

### Changed
- **External CSS print method is now default-ON for new installs.** The previous default of OFF left fresh installs shipping ~700 KB of inlined CSS in every HTML response; defaulting on makes Elementor emit external cacheable `.css` files instead. Existing users keep their stored toggle state. The feature description now reminds users to run **Elementor → Tools → Regenerate Files & Data** after enabling.

---

## [0.2.2] — 2026-05-11

### Fixed
- **CLS regression from v0.2.1 experiment forcer.** Sites hit layout-shift spikes after v0.2.1 began force-activating `e_lazyload`, `e_optimized_assets_loading`, and `e_css_smooth_scroll`. `e_lazyload` lazy-loaded above-fold images on sites whose images lacked explicit width/height attributes; `e_optimized_assets_loading` requires *Elementor → Tools → Regenerate Files & Data* first or widgets render briefly unstyled. The experiment forcer now activates only the original two experiments (`e_font_icon_svg`, `e_optimized_markup`). v0.2.0 default behaviour is restored for everyone.
- **Image dimensions are now added inside Elementor's render pipeline.** Previous coverage missed Image and Image Box widget output because Elementor renders widget HTML through its own pipeline that bypasses the WordPress content filter. Root-cause fix for the CLS path that v0.2.1's `e_lazyload` exposed.

### Added
- **Extra experiment forcer (gated, opt-in).** The three experiments that v0.2.1 force-enabled by default are now reachable behind an opt-in flag, with the description warning to regenerate Elementor files and pair with *Auto-fix image dimensions* before enabling.

---

## [0.2.1] — 2026-05-11

### Added
- **External CSS print method optimizer.** Forces Elementor to emit cacheable external `.css` files per post instead of inlining widget CSS in every HTML response.
- **Frontend localize trimmer.** Strips editor-only keys (`i18n`, `loaderUrl`, `beta`, non-edit `environmentMode`) from the `elementorFrontendConfig` JSON inlined on every page. Saves 1–5 KB per pageview.
- **Loading overlay remover.** Pure-CSS hide of Elementor's loading overlay. On Feather-optimized sites the page paints fully before JS runs, so the overlay only delays perceived first paint.
- **CSS overrides for CLS.** Inlines ~2 KB of CSS on Elementor pages addressing layout shift on image, image-box, video, counter, and carousel widgets; reserves a 16:9 ratio for video embeds; respects `prefers-reduced-motion`; strips interactive widgets from print output.
- **Widget lazy init (gated).** IntersectionObserver-driven init for Swiper carousels, animated counters, Vimeo/YouTube iframes, and entrance animations. Above-fold widgets boot immediately; below-fold wait until the section enters the viewport. Companion to the JS deferrer — that defers script parse, this defers the work the script does.

### Fixed
- **Elementor page detector no longer keeps assets on feeds, attachments, and 404s.** The previous behaviour leaked the entire Elementor frontend bundle onto RSS feeds, attachment pages, and 404 templates. Archive and search behavior unchanged.
- **Google Fonts disabler now also dequeues Kit-level font handles** (`elementor-google-fonts` and `e-google-fonts`, introduced in Elementor 3.8+) that previously bypassed the filter.
- **Below-fold renderer selector now matches Flexbox Container.** The previous selector targeted only the legacy Section/Column layout and missed Flexbox Container (default on Elementor 3.6+).

### Changed
- **Experiment forcer extended.** Now force-activates `e_optimized_assets_loading`, `e_lazyload`, and `e_css_smooth_scroll` in addition to the original `e_font_icon_svg` and `e_optimized_markup`. *(Reverted in v0.2.2 — see above.)*

---

## [0.2.0] — 2026-05-11

### Added
- **Elementor 4.0.x atomic widget support.** Scanner detects atomic widgets (`e-heading`, `e-button`, `e-image`, `e-paragraph`, `e-divider`, `e-svg`, `e-self-hosted-video`, `e-youtube`, `e-component`) and atomic element types (`e-flexbox`, `e-div-block`, `e-tabs`, `e-tabs-menu`, `e-tab`, `e-tabs-content-area`, `e-tab-content`).
- **Atomic asset gate (gated).** On Elementor pages that contain only legacy widgets, dequeues the v4 atomic widget handler bundle. Auto-bails on Elementor Pro to protect theme-builder injected atomic widgets.
- **Elementor host firewall (gated).** Refuses every outbound request to `elementor.com` unless it is user-initiated (AJAX non-heartbeat, REST, editor page load). Catches every background telemetry, marketing, and discovery vector at the network layer — including future ones Elementor may add.
- **Master "Pause all optimizations" toggle.** New top-level setting; when on, no optimizers register their hooks. Exposed via a card at the top of the Dashboard with optimistic-update and rollback behavior
---

## License

Feather Performance is released under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Copyright (C) 2026  Feather

This program is free software; however you may not redistribute it a under the terms of the GNU General Public License without permession from the author, as
published by the Free Software Foundation.
```

---

<div align="center">

Built by [Feather](https://featherplugin.com/) — **[featherplugin.com](https://featherplugin.com/)**

</div>
