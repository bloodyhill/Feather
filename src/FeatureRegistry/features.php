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
use Feather\Optimizers\Elementor\DomBloatRemover;
use Feather\Optimizers\Elementor\EiconsDisabler;
use Feather\Optimizers\Elementor\ExperimentForcer;
use Feather\Optimizers\Elementor\FA4ShimDisabler;
use Feather\Optimizers\Elementor\FrontendAssetGate;
use Feather\Optimizers\Elementor\GoogleFontsDisabler;
use Feather\Optimizers\Elementor\JsDeferer;
use Feather\Optimizers\Elementor\PerPageAssetTrimmer;
use Feather\Optimizers\Elementor\RevisionsLimiter;
use Feather\Optimizers\Elementor\TelemetryDisabler;
use Feather\Optimizers\Elementor\UnusedWidgetBundleStripper;
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
		'label'           => __( 'Skip rendering off-screen sections', 'feather-performance' ),
		'description'     => __( 'Apply CSS content-visibility:auto to below-the-fold sections so the browser defers their layout work until they enter the viewport.', 'feather-performance' ),
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
