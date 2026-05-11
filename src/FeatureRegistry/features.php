<?php
/**
 * Static feature definitions for Feather.
 *
 * Add-ons can extend this list via the `feather/feature_registry` filter.
 *
 * @package Feather
 */

declare( strict_types=1 );

use Feather\FeatureRegistry\FeatureMetadata;
use Feather\Optimizers\Elementor\AdminBloatDisabler;
use Feather\Optimizers\Elementor\AdminTopBarOptimizer;
use Feather\Optimizers\Elementor\AiModuleDisabler;
use Feather\Optimizers\Elementor\ApiFetcherDisabler;
use Feather\Optimizers\Elementor\AtomicAssetGate;
use Feather\Optimizers\Elementor\CssOverridesEmitter;
use Feather\Optimizers\Elementor\CssPrintMethodEnforcer;
use Feather\Optimizers\Elementor\DomBloatRemover;
use Feather\Optimizers\Elementor\EiconsDisabler;
use Feather\Optimizers\Elementor\ElementCacheForcer;
use Feather\Optimizers\Elementor\ElementorHostFirewall;
use Feather\Optimizers\Elementor\ExperimentForcer;
use Feather\Optimizers\Elementor\ExtraExperimentForcer;
use Feather\Optimizers\Elementor\FA4ShimDisabler;
use Feather\Optimizers\Elementor\FrontendAssetGate;
use Feather\Optimizers\Elementor\FrontendLocalizeTrimmer;
use Feather\Optimizers\Elementor\GoogleFontsDisabler;
use Feather\Optimizers\Elementor\JsDeferer;
use Feather\Optimizers\Elementor\LoadingOverlayRemover;
use Feather\Optimizers\Elementor\PerPageAssetTrimmer;
use Feather\Optimizers\Elementor\RevisionsLimiter;
use Feather\Optimizers\Elementor\TelemetryDisabler;
use Feather\Optimizers\Elementor\UnusedWidgetBundleStripper;
use Feather\Optimizers\Elementor\WidgetLazyInit;
use Feather\Optimizers\Media\BelowFoldRenderer;
use Feather\Optimizers\Media\IframeLazyloader;
use Feather\Optimizers\Media\ImageDimensionsAdder;
use Feather\Optimizers\Wp\EmbedsDisabler;
use Feather\Optimizers\Wp\EmojisDisabler;
use Feather\Optimizers\Wp\HeadCleanup;
use Feather\Optimizers\Wp\HeartbeatThrottler;
use Feather\Optimizers\Wp\JqueryMigrateRemover;

defined( 'ABSPATH' ) || exit;

return array(

	// ───────────────────────────────────────────────────────────────────
	// Group A — Elementor frontend assets
	// ───────────────────────────────────────────────────────────────────
	array(
		'id'              => 'f.elementor.skip_non_pages',
		'label'           => __( 'Skip Elementor assets on non-Elementor pages', 'feather-performance' ),
		'description'     => __( 'When a page or post is not built with Elementor, drop Elementor\'s frontend bundle entirely. Highest-impact win on most sites.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => FrontendAssetGate::class,
	),
	array(
		'id'              => 'f.elementor.per_page_trim',
		'label'           => __( 'Trim per-widget assets to what each page uses', 'feather-performance' ),
		'description'     => __( 'On Elementor pages, dequeue widget-specific stylesheets (widget-heading, widget-image, etc.) and conditional scripts (swiper, jquery-numerator) that the page\'s scan row says it doesn\'t use. Auto-bails when Elementor Pro is installed so theme-builder headers/footers can\'t lose assets.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => PerPageAssetTrimmer::class,
	),
	array(
		'id'              => 'f.elementor.force_experiments',
		'label'           => __( 'Force-enable Elementor performance experiments', 'feather-performance' ),
		'description'     => __( 'Activate Elementor\'s built-in inline-SVG icons and optimized-markup experiments. With these on, Elementor itself stops enqueuing icon-font libraries and strips redundant wrapper tags — the cleanest win because the vendor ships the optimization. Default-active on new sites since 3.30.0; legacy installs stay off without this.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => ExperimentForcer::class,
	),
	array(
		'id'              => 'f.elementor.force_extra_experiments',
		'label'           => __( 'Force-enable extra Elementor experiments (advanced)', 'feather-performance' ),
		'description'     => __( 'Activate Elementor\'s optimized-assets-loading, image lazy-load, and CSS smooth-scroll experiments. These look like pure wins but can cause CLS / LCP regressions: optimized-assets-loading needs you to run Elementor → Tools → Regenerate Files & Data first, and lazy-load lazy-loads above-fold images too — pair it with Auto-fix image dimensions. Default-off after the v0.2.1 regression.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => ExtraExperimentForcer::class,
	),
	array(
		'id'              => 'f.elementor.element_cache',
		'label'           => __( 'Cache per-widget Elementor render output', 'feather-performance' ),
		'description'     => __( 'Force-enable Elementor\'s e_element_cache experiment. Elementor caches each widget\'s rendered HTML by settings hash; static widgets (Heading, Image, Text, Button…) return cached output and skip their PHP render entirely. Single biggest lever against Elementor TTFB — pages with many widgets typically go from 2-3s renders to 300-500ms. Gated because third-party widgets that read dynamic data inside render_content() without declaring it can serve stale output; audit any custom widget plugins first.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => ElementCacheForcer::class,
	),
	array(
		'id'              => 'f.elementor.fa4_shim',
		'label'           => __( 'Disable Font Awesome 4 shim', 'feather-performance' ),
		'description'     => __( 'Remove Elementor\'s Font Awesome 4 backwards-compatibility stylesheets (~70KB).', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => FA4ShimDisabler::class,
	),
	array(
		'id'              => 'f.elementor.eicons_global',
		'label'           => __( 'Disable eicons globally', 'feather-performance' ),
		'description'     => __( 'Remove the Elementor icons font and stylesheet site-wide. Only safe when no widget references an eicon.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => EiconsDisabler::class,
	),
	array(
		'id'              => 'f.elementor.unused_widget_bundles',
		'label'           => __( 'Drop unused Elementor sub-bundles', 'feather-performance' ),
		'description'     => __( 'After a site scan confirms which widgets you actually use, removes Elementor sub-bundles your site doesn\'t need (Swiper for carousels, e-Lightbox for galleries, Lottie runtime, entrance animations). Targets the biggest TBT offender on most Elementor sites.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => UnusedWidgetBundleStripper::class,
	),
	array(
		'id'              => 'f.elementor.skip_atomic_chain',
		'label'           => __( 'Skip atomic widget JS on legacy-widget pages', 'feather-performance' ),
		'description'     => __( 'On Elementor pages built entirely with legacy widgets (no e-heading, e-button, e-tabs, etc.), drops the v4 atomic widget handler bundle — including Alpine.js — from the enqueue queue. Saves ~3 JS files plus Alpine on every legacy-only page. Auto-bails when Elementor Pro is active so theme-builder injected atomic widgets aren\'t broken.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => AtomicAssetGate::class,
	),
	array(
		'id'              => 'f.elementor.google_fonts',
		'label'           => __( 'Disable Elementor Google Fonts', 'feather-performance' ),
		'description'     => __( 'Stop Elementor from emitting Google Fonts. Use only when your theme provides fonts or you self-host them.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => GoogleFontsDisabler::class,
	),
	array(
		'id'              => 'f.elementor.defer_js',
		'label'           => __( 'Defer Elementor JavaScript', 'feather-performance' ),
		'description'     => __( 'Add the defer attribute to Elementor\'s frontend scripts so they no longer block the parser.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => JsDeferer::class,
	),
	array(
		'id'              => 'f.elementor.dom_bloat',
		'label'           => __( 'Remove Elementor DOM artifacts', 'feather-performance' ),
		'description'     => __( 'Strip cache-busting query strings from Elementor asset URLs and detach the redundant Google Fonts preconnect tag.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => DomBloatRemover::class,
	),
	array(
		'id'              => 'f.elementor.revisions_cap',
		'label'           => __( 'Cap Elementor post revisions', 'feather-performance' ),
		'description'     => __( 'Limit stored revisions on Elementor-built posts to 5 per post. Keeps the database trim during long edit sessions.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => RevisionsLimiter::class,
	),
	array(
		'id'              => 'f.elementor.telemetry',
		'label'           => __( 'Disable Elementor telemetry', 'feather-performance' ),
		'description'     => __( 'Force Elementor\'s tracking, beta-tester, and tracker-event flags off.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => TelemetryDisabler::class,
	),
	array(
		'id'              => 'f.elementor.admin_bloat',
		'label'           => __( 'Hide Elementor admin bloat', 'feather-performance' ),
		'description'     => __( 'Remove Elementor\'s dashboard widget, promo notices, menu upsells, and stretch the editor autosave interval.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => AdminBloatDisabler::class,
	),
	array(
		'id'              => 'f.elementor.admin_top_bar',
		'label'           => __( 'Disable Elementor admin top bar', 'feather-performance' ),
		'description'     => __( 'Remove the floating Elementor top bar from every wp-admin screen — including its unconditional fonts.googleapis.com request for Roboto. Pure cosmetic UI; editor continues to work without it.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => AdminTopBarOptimizer::class,
	),
	array(
		'id'              => 'f.elementor.ai_module',
		'label'           => __( 'Disable Elementor AI module', 'feather-performance' ),
		'description'     => __( 'Suppress Elementor\'s AI features when not in use: short-circuits the is_ai_enabled gate (stops ~30 AJAX endpoint registrations) and dequeues the editor-side AI script bundles. Enable only if you don\'t use Elementor AI.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => AiModuleDisabler::class,
	),
	array(
		'id'              => 'f.elementor.api_fetcher',
		'label'           => __( 'Block Elementor remote phone-home', 'feather-performance' ),
		'description'     => __( 'Short-circuit periodic fetches to my.elementor.com (Black Friday banners, Birthday banners, what\'s-new feed, pro-widget upsells, canary deployment check) and block all outbound HTTP to that host as a safety net.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => ApiFetcherDisabler::class,
	),
	array(
		'id'              => 'f.elementor.network_firewall',
		'label'           => __( 'Block all Elementor outbound HTTP except user-initiated', 'feather-performance' ),
		'description'     => __( 'Aggressive: blocks every outbound HTTP to elementor.com and its subdomains EXCEPT during user-initiated actions (editor page loads, AJAX inside the editor, REST API calls from the editor). Catches every background telemetry/marketing/discovery vector at the network layer — including future ones that ship in later Elementor versions. Template Library, Connect login, and Pro activation flows still work because they run as AJAX/REST. Side effects: dashboard widgets that fetch Elementor news on admin page load go blank. Recommended for privacy-conscious admins.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => ElementorHostFirewall::class,
	),
	array(
		'id'              => 'f.elementor.css_print_external',
		'label'           => __( 'Force Elementor CSS to "External File"', 'feather-performance' ),
		'description'     => __( 'Short-circuit the elementor_css_print_method option so Elementor always emits its widget CSS as a cacheable .css file per post instead of inlining it into every HTML response. After enabling, run Elementor → Tools → Regenerate Files & Data so the per-page .css files are written to uploads/elementor/css. No database write — your stored Elementor setting is preserved and surfaces unchanged after Feather is deactivated.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => CssPrintMethodEnforcer::class,
	),
	array(
		'id'              => 'f.elementor.localize_trim',
		'label'           => __( 'Trim Elementor frontend config payload', 'feather-performance' ),
		'description'     => __( 'Strip editor-only keys (i18n strings, loaderUrl, beta flag) from the elementorFrontendConfig JSON that Elementor inlines into every page. Saves 1–5 KB of inline JS per pageview without affecting the editor.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => FrontendLocalizeTrimmer::class,
	),
	array(
		'id'              => 'f.elementor.loading_overlay',
		'label'           => __( 'Hide Elementor loading overlay', 'feather-performance' ),
		'description'     => __( 'Hide the full-viewport white spinner overlay Elementor injects at body open. On Feather-optimized sites the page paints fully before JS runs, so the overlay only delays perceived first paint. Pure CSS — no DOM mutation.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => LoadingOverlayRemover::class,
	),
	array(
		'id'              => 'f.elementor.css_overrides_cls',
		'label'           => __( 'Apply Elementor CLS / motion overrides', 'feather-performance' ),
		'description'     => __( 'Inline ~2 KB of CSS on Elementor pages that fixes Cumulative Layout Shift on image, image-box, video, counter, and carousel widgets; reserves a 16:9 ratio for video embeds; respects prefers-reduced-motion; and strips interactive widgets from print output. Theme-coupled overrides (fonts, fluid typography, grid replacement) are intentionally not shipped.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => CssOverridesEmitter::class,
	),
	array(
		'id'              => 'f.elementor.widget_lazy_init',
		'label'           => __( 'Lazy-init heavy Elementor widgets', 'feather-performance' ),
		'description'     => __( 'IntersectionObserver-driven initialization for Swiper carousels, animated counters, Vimeo/YouTube iframes, and entrance animations: above-the-fold widgets boot immediately, the rest wait until the section enters the viewport. Companion to "Defer Elementor JavaScript" — that defers script parse, this defers the work the script does. Default-off because it shifts widget timing; test before enabling on production.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_ELEMENTOR,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => false,
		'default_enabled' => false,
		'optimizer'       => WidgetLazyInit::class,
	),

	// ───────────────────────────────────────────────────────────────────
	// Group B — WordPress hygiene
	// ───────────────────────────────────────────────────────────────────
	array(
		'id'              => 'f.wp.emojis',
		'label'           => __( 'Disable WP emojis', 'feather-performance' ),
		'description'     => __( 'Remove the WordPress emoji detection script and fallback styles. Modern browsers render emoji natively.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_WP,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => EmojisDisabler::class,
	),
	array(
		'id'              => 'f.wp.embeds',
		'label'           => __( 'Disable wp-embed', 'feather-performance' ),
		'description'     => __( 'Drop the wp-embed script and oEmbed discovery links from <head>.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_WP,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => EmbedsDisabler::class,
	),
	array(
		'id'              => 'f.wp.jquery_migrate',
		'label'           => __( 'Remove jquery-migrate from frontend', 'feather-performance' ),
		'description'     => __( 'Drop the legacy jQuery Migrate dependency on public pages. Admin keeps it for plugin compatibility.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_WP,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => JqueryMigrateRemover::class,
	),
	array(
		'id'              => 'f.wp.heartbeat',
		'label'           => __( 'Throttle WP Heartbeat', 'feather-performance' ),
		'description'     => __( 'Stretch the admin heartbeat to 60 seconds and remove it entirely from the public frontend.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_WP,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => HeartbeatThrottler::class,
	),
	array(
		'id'              => 'f.wp.head_cleanup',
		'label'           => __( 'Clean up wp_head() output', 'feather-performance' ),
		'description'     => __( 'Remove RSD, Windows Live Writer manifest, shortlink, and meta-generator tags from <head>.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_WP,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_LOW,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => HeadCleanup::class,
	),

	// ───────────────────────────────────────────────────────────────────
	// Group C — Lazy Loading & Media
	// ───────────────────────────────────────────────────────────────────
	array(
		'id'              => 'f.media.iframe_lazy',
		'label'           => __( 'Lazy-load iframes', 'feather-performance' ),
		'description'     => __( 'Add loading="lazy" to every iframe so off-screen embeds (YouTube, Maps, etc.) do not block initial paint.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => IframeLazyloader::class,
	),
	array(
		'id'              => 'f.media.image_dimensions',
		'label'           => __( 'Auto-fix image dimensions', 'feather-performance' ),
		'description'     => __( 'Add explicit width/height to images that are missing them, preventing Cumulative Layout Shift on load.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => ImageDimensionsAdder::class,
	),
	array(
		'id'              => 'f.media.below_fold_render',
		'label'           => __( 'Skip rendering off-screen sections (opt-in per section)', 'feather-performance' ),
		'description'     => __( 'Emits content-visibility:auto CSS rules that defer rendering work for sections you mark with a feather-cv class in Elementor → Advanced → CSS Classes. Variants feather-cv-300 / 400 / 500 / 600 / 700 / 900 / 1000 / 1200 / 1500 / 2000 set a matching intrinsic-size placeholder so the section reserves its real height. Plain feather-cv defaults to 800px. v0.2.3 and earlier auto-applied this to every below-first section with a fixed 800px placeholder; that caused catastrophic CLS (up to 0.9) because the placeholder rarely matched real section heights. v0.2.4 onwards requires the class — safe by default.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => BelowFoldRenderer::class,
	),
	array(
		'id'              => 'f.media.youtube_lite',
		'label'           => __( 'Lazy-load YouTube embeds', 'feather-performance' ),
		'description'     => __( 'Replace YouTube iframes with a click-to-play poster so the YouTube player only loads when the visitor presses play.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => null,
	),
	array(
		'id'              => 'f.media.vimeo_lite',
		'label'           => __( 'Lazy-load Vimeo embeds', 'feather-performance' ),
		'description'     => __( 'Replace Vimeo iframes with a click-to-play poster so the Vimeo player only loads on demand.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => null,
	),
	array(
		'id'              => 'f.media.bg_image_lazy',
		'label'           => __( 'Lazy-load Elementor background images', 'feather-performance' ),
		'description'     => __( 'Defer loading of CSS background images on Elementor sections until they enter the viewport.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => null,
	),
	array(
		'id'              => 'f.media.comments_lazy',
		'label'           => __( 'Lazy-render the comments section', 'feather-performance' ),
		'description'     => __( 'Defer rendering and asset loading for the comments area until the visitor scrolls near it.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => null,
	),
);
