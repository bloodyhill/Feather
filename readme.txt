=== Feather - Elementor Optimizer, Asset Cleanup, Page Speed, Database Tools ===
Contributors: featherr
Tags: elementor, performance, optimization, page speed, asset cleanup
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Strip unused Elementor assets per page, measure page weight, and clean your database from one dashboard.

== Description ==

Feather is an optimizer for WordPress sites built with Elementor. It reads each page's saved Elementor data, knows which widgets and assets are actually in use, and trims the rest from your frontend.

= What it does =

* **Per-page asset awareness.** Inspects your saved Elementor data to build a map of which widgets each page uses. Asset-stripping optimizations only activate when the scan confirms zero usages site-wide.
* **Safe defaults.** Universally-safe optimizations are active on first activation: skip Elementor's frontend bundle on non-Elementor pages, defer JavaScript, strip cache-busting query strings, throttle the admin heartbeat, disable WP emojis and embeds, and more.
* **Measured impact.** Take page-weight snapshots from the dashboard and track the trend across bytes, asset count, and HTTP request count, all measured against your real frontend.
* **Database tooling.** One-click cleanup for expired transients, orphaned Elementor revisions, and oEmbed cache rows. Autoload audit flags any large always-loaded options.
* **Privacy by default.** No telemetry. Scans run on your server. Page-weight measurements hit your own URLs. Nothing leaves your site without explicit opt-in.

= Optimization categories =

* Elementor frontend assets: Font Awesome 4 shim, eicons, Google Fonts, JS defer, asset gating on non-Elementor pages, DOM-cruft removal
* WordPress hygiene: emojis, embeds, jquery-migrate, heartbeat throttle, head cleanup, version disclosure
* Lazy loading & media: iframe lazy attributes, image-dimension auto-fix, content-visibility for off-screen sections
* Database: transient cleanup, Elementor revision pruning, oEmbed cache flush, autoload audit
* Reporting: page-weight history, site-scan results, database health score

= Compatibility =

Feather auto-detects WP Rocket, LiteSpeed Cache, SiteGround Optimizer, W3 Total Cache, Imagify, Smush, and most other performance / image-optimizer plugins. Overlapping optimizations refuse to enable so they don't duplicate work another plugin already handles.

== Installation ==

1. Upload the `feather` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate Feather through the Plugins menu in WordPress.
3. Visit Feather in the admin sidebar. Run the welcome scan and apply a recommended preset.

== Frequently Asked Questions ==

= Will Feather break my site? =

Feather's defaults are conservative. Aggressive optimizations require a recent site scan and an explicit confirmation. Every change is reversible from the dashboard.

= Does Feather work alongside WP Rocket / LiteSpeed Cache? =

Yes. Feather detects them and disables overlapping optimizations automatically.

= Does Feather send any data to a remote server? =

No. Scans, metrics, and configuration all live on your server. The Lighthouse-on-demand feature uses your own Google PageSpeed Insights API key to call Google directly. Feather never proxies this through a third party.

= Does Feather work on multisite? =

Yes, in compatible mode (per-site configuration). Network-level controls are planned for a later release.

== Screenshots ==

1. Dashboard with measured savings and quick wins.
2. Features browser, grouped by category, with risk badges.
3. Site Scan view showing widget and asset usage per page.
4. Database health and cleanup tools.
5. Dashboard in dark mode.

== Changelog ==

= 0.2.7 =
* Fixed: Optimizations briefly showed as "not applied" after toggling them, on sites using persistent object caches (Redis, Memcached, LiteSpeed). The settings option now busts the WordPress options cache explicitly after every save so follow-up requests see the canonical database value immediately.
* Fixed: Site Scan results and "Take measurement" output sometimes appeared stale until a page reload on sites with page caches (LiteSpeed Cache, WP Rocket, Cloudflare). The feather/v1 REST namespace now returns strict no-store, no-cache headers so page caches stop holding onto authenticated responses.
* Changed: In-WordPress plugin name shortened to "Feather" so the Plugins list and Updates screen show a clean brand name. The longer descriptive title remains on the WordPress.org listing for search visibility.

= 0.2.6 =
* Fixed: "Settings" link in the Plugins list now points to the correct admin page slug. Previously it linked to `admin.php?page=feather`, which produced a "Sorry, you are not allowed to access this page" error.
* Fixed: Site Scan now refreshes results in the UI as soon as the scan finishes, instead of requiring a full page reload. The old code only refreshed when the scan state landed exactly on `complete`, missing edge cases where the cached state briefly read as `idle` after the cron tick.
* Fixed: Dashboard "Take measurement" now re-reads the latest snapshot from the server after capturing, so the "Last measured" tile and homepage-weight value update immediately instead of after a page reload.
* New: Footer link on every Feather admin page that opens the WordPress.org review form in a new tab.
* Changed: Plugin display title rewritten to describe what it does in search results and on the plugin page.
* Changed: Replaced em dashes throughout the readme and plugin header with regular punctuation.

= 0.2.5 =
* Changed: Inline CSS now emitted via `wp_register_style()` + `wp_add_inline_style()` instead of direct `<style>` echoes. Follows WordPress.org enqueue guidelines.
* Removed: Placeholder feature toggles that had no implementation. Every shipped feature is now fully functional out of the box.
* Removed: `load_plugin_textdomain()` call. WordPress.org auto-loads translations for hosted plugins since WP 4.6.

= 0.2.0 =
* New: Elementor 4.0.x support. Scanner detects atomic widgets (e-heading, e-button, e-image, e-paragraph, e-divider, e-svg, e-self-hosted-video, e-youtube, e-component) and atomic element types (e-flexbox, e-div-block, e-tabs, etc.) in saved page data.
* New: "Skip atomic widget JS on legacy-widget pages" optimizer (gated). Drops the v4 atomic widget handler bundle, including Alpine.js, on pages built entirely with legacy widgets. Auto-bails on Elementor Pro.
* New: "Pause all optimizations" master toggle on the Dashboard. Temporarily disable every Feather optimization while editing in Elementor, without uninstalling.
* New: "Block all Elementor outbound HTTP except user-initiated" optimizer (gated). Refuses every background request to elementor.com and subdomains. Template Library, Connect login, and Pro flows still work because they run as AJAX/REST.
* New: PerPageAssetTrimmer now drops `elementor-tabs-handler` and `elementor-youtube-handler` on pages that don't use those atomic elements.
* New: UnusedWidgetBundleStripper deregisters the entire v4 JS chain when no scanned post on the site uses atomic widgets.
* Changed: Default theme switched from "system" to "light" on fresh installs. Existing users keep their preference.
* Fixed: Third-party widget packs (Rivax, etc.) that throw inside `get_style_depends()` no longer leak error strings into Feather's asset map.
* Fixed: Elementor's `Widget_WordPress` class incorrectly declares Swiper as a dependency for every `wp-widget-*` type. Feather now strips that incorrect claim so sidebar widgets don't falsely require Swiper.
* Fixed: Dashboard "Last measured" tile no longer shows "Never" immediately after running a measurement.
* Fixed: Dashboard "Pause all optimizations" card now renders with proper spacing between label and description.

= 0.1.0 =
* Initial pre-release scaffold.

== Upgrade Notice ==

= 0.2.0 =
First release with full Elementor 4.0.x support. Adds a master pause toggle and an aggressive network firewall (both opt-in). Existing installations keep all current settings; the new features are off by default.

= 0.1.0 =
Pre-alpha. Not for production use.
