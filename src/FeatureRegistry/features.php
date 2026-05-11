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
		'description'     => __( 'Drop Elementor\'s frontend bundle on pages not built with Elementor.', 'feather-performance' ),
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
		'description'     => __( 'Per page, dequeue widget styles and conditional scripts the scan didn\'t find used. Auto-bails on Elementor Pro.', 'feather-performance' ),
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
		'description'     => __( 'Force Elementor\'s inline-SVG icons and optimized-markup experiments active. Stops icon-font enqueues, strips wrapper divs.', 'feather-performance' ),
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
		'description'     => __( 'Force e_optimized_assets_loading, e_lazyload, and e_css_smooth_scroll active. Run Elementor → Tools → Regenerate Files & Data first.', 'feather-performance' ),
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
		'description'     => __( 'Force e_element_cache active. Caches per-widget render output; biggest TTFB win on widget-heavy pages. Custom third-party widgets may serve stale output.', 'feather-performance' ),
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
		'description'     => __( 'Remove Elementor\'s Font Awesome 4 backward-compat CSS (~70 KB).', 'feather-performance' ),
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
		'description'     => __( 'Remove the eicons font sitewide. Safe only when no widget uses an eicon.', 'feather-performance' ),
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
		'description'     => __( 'After a scan, remove Elementor sub-bundles unused sitewide: Swiper, e-Lightbox, Lottie, entrance animations.', 'feather-performance' ),
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
		'description'     => __( 'On legacy-widget-only pages, drop the v4 atomic widget handler bundle plus Alpine.js. Auto-bails on Elementor Pro.', 'feather-performance' ),
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
		'description'     => __( 'Stop Elementor from emitting Google Fonts. Enable only if you self-host fonts or use a font plugin.', 'feather-performance' ),
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
		'description'     => __( 'Add defer to Elementor\'s frontend scripts so they don\'t block the HTML parser.', 'feather-performance' ),
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
		'description'     => __( 'Strip ?ver= cache-busters from Elementor asset URLs; detach the duplicate Google Fonts preconnect tag.', 'feather-performance' ),
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
		'description'     => __( 'Cap revisions on Elementor-built posts at 5 per post.', 'feather-performance' ),
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
		'description'     => __( 'Remove Elementor\'s dashboard widget, promo notices, and menu upsells; stretch the editor autosave interval.', 'feather-performance' ),
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
		'description'     => __( 'Remove the floating Elementor top bar from wp-admin, including its Google Fonts call for Roboto.', 'feather-performance' ),
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
		'description'     => __( 'Short-circuit is_ai_enabled and dequeue Elementor AI editor bundles. Enable only if you don\'t use Elementor AI.', 'feather-performance' ),
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
		'description'     => __( 'Block periodic phone-home to my.elementor.com (banners, what\'s-new feed, upsells, canary checks).', 'feather-performance' ),
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
		'description'     => __( 'Block all outbound HTTP to elementor.com except user-initiated editor actions. Template Library and Connect login still work.', 'feather-performance' ),
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
		'description'     => __( 'Force Elementor to emit cacheable external CSS files per post instead of inlining CSS in every page. Run Regenerate Files after enabling.', 'feather-performance' ),
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
		'description'     => __( 'Strip editor-only keys (i18n, loaderUrl, beta) from the inline elementorFrontendConfig JSON. Saves 1–5 KB per page.', 'feather-performance' ),
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
		'description'     => __( 'Hide the white spinner overlay Elementor injects at body open. CSS only — no DOM mutation.', 'feather-performance' ),
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
		'description'     => __( 'Inline ~2 KB CSS that fixes CLS on image, video, counter, and carousel widgets; respects prefers-reduced-motion.', 'feather-performance' ),
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
		'description'     => __( 'IntersectionObserver init for Swiper, counter, video, and entrance animations. Above-fold runs immediately, the rest waits until visible.', 'feather-performance' ),
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
		'description'     => __( 'Remove the WP emoji detection script and fallback styles. Browsers render emoji natively.', 'feather-performance' ),
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
		'description'     => __( 'Drop wp-embed and oEmbed discovery links from <head>.', 'feather-performance' ),
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
		'description'     => __( 'Drop jquery-migrate from the frontend. Admin keeps it for plugin compatibility.', 'feather-performance' ),
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
		'description'     => __( 'Stretch admin heartbeat to 60 s; remove it from the frontend entirely.', 'feather-performance' ),
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
		'description'     => __( 'Remove RSD, WLW manifest, shortlink, and meta-generator tags from <head>.', 'feather-performance' ),
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
		'description'     => __( 'Add loading="lazy" to iframes so off-screen embeds (YouTube, Maps) don\'t block paint.', 'feather-performance' ),
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
		'description'     => __( 'Add width/height to <img> tags missing them so the browser reserves space before the image loads.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => false,
		'default_enabled' => true,
		'optimizer'       => ImageDimensionsAdder::class,
	),
	array(
		'id'              => 'f.media.below_fold_render',
		'label'           => __( 'Skip rendering off-screen sections', 'feather-performance' ),
		'description'     => __( 'Adds content-visibility:auto to sections with the feather-cv class in Elementor → Advanced → CSS Classes. Variants like feather-cv-300 to feather-cv-2000 set matching placeholder heights, while feather-cv defaults to 800px.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_GATED,
		'impact'          => FeatureMetadata::IMPACT_HIGH,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => BelowFoldRenderer::class,
	),
	array(
		'id'              => 'f.media.youtube_lite',
		'label'           => __( 'Lazy-load YouTube embeds', 'feather-performance' ),
		'description'     => __( 'Replace YouTube iframes with a click-to-play poster; the player loads only on press.', 'feather-performance' ),
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
		'description'     => __( 'Replace Vimeo iframes with a click-to-play poster; the player loads only on press.', 'feather-performance' ),
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
		'description'     => __( 'Defer Elementor section background images until they enter the viewport.', 'feather-performance' ),
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
		'description'     => __( 'Defer rendering and assets of the comments area until the visitor scrolls near it.', 'feather-performance' ),
		'category'        => FeatureMetadata::CATEGORY_MEDIA,
		'risk'            => FeatureMetadata::RISK_SAFE,
		'impact'          => FeatureMetadata::IMPACT_MEDIUM,
		'pro_candidate'   => true,
		'default_enabled' => false,
		'optimizer'       => null,
	),
);
