<?php
/**
 * Plugin orchestrator.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather;

use Feather\Admin\AdminMenu;
use Feather\Admin\AssetEnqueue;
use Feather\Admin\Capability;
use Feather\Compat\PluginDetector;
use Feather\Db\DbToolsService;
use Feather\FeatureRegistry\FeatureGate;
use Feather\FeatureRegistry\FeatureRegistry;
use Feather\Metrics\MetricsRepository;
use Feather\Metrics\PageWeightProbe;
use Feather\Onboarding\OnboardingState;
use Feather\Optimizers\AbstractOptimizer;
use Feather\Rest\DbToolsEndpoint;
use Feather\Rest\FeaturesEndpoint;
use Feather\Rest\MetricsEndpoint;
use Feather\Rest\OnboardingEndpoint;
use Feather\Rest\RestController;
use Feather\Rest\ScanEndpoint;
use Feather\Rest\SettingsEndpoint;
use Feather\Rest\SystemInfoEndpoint;
use Feather\Scanner\ElementorJsonParser;
use Feather\Scanner\ScanRepository;
use Feather\Scanner\SiteScanner;
use Feather\Scanner\WidgetAssetMap;
use Feather\Settings\SchemaMigrator;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services into the container, fires the registration hook so
 * third-party add-ons can join, loads features, and applies optimizers
 * whose user setting is enabled and whose safety check passes.
 */
final class Plugin {

	public const VERSION = '0.2.0';

	/**
	 * Process-wide instance for the `feather_plugin()` global helper.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Get the global instance set by feather.php.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Set the global instance. Called once from feather.php.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public static function set_instance( Plugin $plugin ): void {
		self::$instance = $plugin;
	}

	/**
	 * Service container accessor.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Wire services and schedule plugins_loaded boot.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->register_services();

		// Translations are auto-loaded by WordPress.org for the plugin slug
		// since WP 4.6 — no load_plugin_textdomain() call needed.

		// Surface a Settings link in the plugins list row.
		add_filter( 'plugin_action_links_' . FEATHER_BASENAME, array( $this, 'plugin_action_links' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );

		$this->booted = true;
	}

	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param mixed $links Existing action links.
	 * @return array<int, string>
	 */
	public function plugin_action_links( $links ): array {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=feather' ) ),
			esc_html__( 'Settings', 'feather-performance' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Plugins-loaded handler. Lets add-ons register, then applies optimizers.
	 */
	public function on_plugins_loaded(): void {
		// Run schema migrations (cheap when up-to-date — guarded by version option).
		( new SchemaMigrator() )->migrate();

		/**
		 * Fires after Feather has wired its core services and before the
		 * feature registry is loaded, giving add-ons a chance to register
		 * their own services and to add `feather/feature_registry` filters.
		 *
		 * @param Container $container Service container.
		 */
		do_action( 'feather/register_addons', $this->container );

		// Now that add-ons are registered, hydrate the registry and apply.
		$registry = $this->container->get( FeatureRegistry::class );
		$registry->load();

		// Seed any feature toggles that are missing from saved settings with
		// their declared default. This makes new installs land with the safe
		// optimizations already on, and gracefully back-fills new features
		// that are introduced in future plugin updates.
		$this->seed_feature_defaults();

		$this->apply_optimizers();
	}

	/**
	 * Idempotent: writes default_enabled values into wp_options for every
	 * registered feature that doesn't already have an explicit user setting.
	 *
	 * Toggling a feature off does not "miss" — once a feature id is present
	 * in settings, this method ignores it.
	 */
	private function seed_feature_defaults(): void {
		$registry = $this->container->get( FeatureRegistry::class );
		$settings = $this->container->get( SettingsRepository::class );

		$all      = $settings->all();
		$features = isset( $all['features'] ) && is_array( $all['features'] )
			? $all['features']
			: array();

		$changed = false;
		foreach ( $registry->all() as $metadata ) {
			$id = $metadata->id();
			if ( ! array_key_exists( $id, $features ) ) {
				$features[ $id ] = $metadata->default_enabled();
				$changed         = true;
			}
		}

		if ( $changed ) {
			$all['features'] = $features;
			$settings->save( $all );
		}
	}

	/**
	 * Register core services in the container.
	 */
	private function register_services(): void {
		$this->container->singleton(
			SettingsRepository::class,
			static function (): SettingsRepository {
				return new SettingsRepository();
			}
		);

		$this->container->singleton(
			FeatureRegistry::class,
			static function (): FeatureRegistry {
				return new FeatureRegistry();
			}
		);

		$this->container->singleton(
			ScanRepository::class,
			static function (): ScanRepository {
				return new ScanRepository();
			}
		);

		$this->container->singleton(
			WidgetAssetMap::class,
			static function (): WidgetAssetMap {
				return new WidgetAssetMap();
			}
		);

		$this->container->singleton(
			ElementorJsonParser::class,
			static function ( Container $c ): ElementorJsonParser {
				return new ElementorJsonParser( $c->get( WidgetAssetMap::class ) );
			}
		);

		$this->container->singleton(
			SiteScanner::class,
			static function ( Container $c ): SiteScanner {
				return new SiteScanner(
					$c->get( ElementorJsonParser::class ),
					$c->get( ScanRepository::class )
				);
			}
		);

		$this->container->singleton(
			FeatureGate::class,
			static function ( Container $c ): FeatureGate {
				return new FeatureGate(
					$c->get( FeatureRegistry::class ),
					$c->get( ScanRepository::class )
				);
			}
		);

		$this->container->singleton(
			Capability::class,
			static function (): Capability {
				return new Capability();
			}
		);

		$this->container->singleton(
			AdminMenu::class,
			static function (): AdminMenu {
				return new AdminMenu();
			}
		);

		$this->container->singleton(
			AssetEnqueue::class,
			static function ( Container $c ): AssetEnqueue {
				return new AssetEnqueue(
					$c->get( AdminMenu::class ),
					$c->get( SettingsRepository::class )
				);
			}
		);

		$this->container->singleton(
			FeaturesEndpoint::class,
			static function ( Container $c ): FeaturesEndpoint {
				return new FeaturesEndpoint(
					$c->get( FeatureRegistry::class ),
					$c->get( FeatureGate::class ),
					$c->get( SettingsRepository::class )
				);
			}
		);

		$this->container->singleton(
			SettingsEndpoint::class,
			static function ( Container $c ): SettingsEndpoint {
				return new SettingsEndpoint( $c->get( SettingsRepository::class ) );
			}
		);

		$this->container->singleton(
			ScanEndpoint::class,
			static function ( Container $c ): ScanEndpoint {
				return new ScanEndpoint(
					$c->get( SiteScanner::class ),
					$c->get( ScanRepository::class )
				);
			}
		);

		$this->container->singleton(
			DbToolsService::class,
			static function (): DbToolsService {
				return new DbToolsService();
			}
		);

		$this->container->singleton(
			DbToolsEndpoint::class,
			static function ( Container $c ): DbToolsEndpoint {
				return new DbToolsEndpoint( $c->get( DbToolsService::class ) );
			}
		);

		$this->container->singleton(
			PageWeightProbe::class,
			static function (): PageWeightProbe {
				return new PageWeightProbe();
			}
		);

		$this->container->singleton(
			MetricsRepository::class,
			static function (): MetricsRepository {
				return new MetricsRepository();
			}
		);

		$this->container->singleton(
			MetricsEndpoint::class,
			static function ( Container $c ): MetricsEndpoint {
				return new MetricsEndpoint(
					$c->get( PageWeightProbe::class ),
					$c->get( MetricsRepository::class )
				);
			}
		);

		$this->container->singleton(
			OnboardingState::class,
			static function (): OnboardingState {
				return new OnboardingState();
			}
		);

		$this->container->singleton(
			OnboardingEndpoint::class,
			static function ( Container $c ): OnboardingEndpoint {
				return new OnboardingEndpoint( $c->get( OnboardingState::class ) );
			}
		);

		$this->container->singleton(
			PluginDetector::class,
			static function (): PluginDetector {
				return new PluginDetector();
			}
		);

		$this->container->singleton(
			SystemInfoEndpoint::class,
			static function ( Container $c ): SystemInfoEndpoint {
				return new SystemInfoEndpoint(
					$c->get( PluginDetector::class )
				);
			}
		);

		$this->container->singleton(
			RestController::class,
			static function ( Container $c ): RestController {
				return new RestController(
					array(
						$c->get( FeaturesEndpoint::class ),
						$c->get( SettingsEndpoint::class ),
						$c->get( ScanEndpoint::class ),
						$c->get( DbToolsEndpoint::class ),
						$c->get( MetricsEndpoint::class ),
						$c->get( OnboardingEndpoint::class ),
						$c->get( SystemInfoEndpoint::class ),
					)
				);
			}
		);

		// Always-on registrations: capability mapping, REST routes, scanner cron handler.
		// These have no user toggle — Feather always exposes them.
		$this->container->get( Capability::class )->register();
		$this->container->get( RestController::class )->register();
		$this->container->get( SiteScanner::class )->register_hooks();

		// Admin-only registrations.
		if ( is_admin() ) {
			$this->container->get( AdminMenu::class )->register();
			$this->container->get( AssetEnqueue::class )->register();
		}
	}

	/**
	 * Iterate every registered feature and apply its optimizer when the
	 * user setting is enabled and the optimizer reports it is safe.
	 */
	private function apply_optimizers(): void {
		/** @var SettingsRepository $settings */
		$settings = $this->container->get( SettingsRepository::class );
		if ( $settings->is_optimizers_paused() ) {
			return;
		}

		$registry = $this->container->get( FeatureRegistry::class );

		foreach ( $registry->all() as $metadata ) {
			$class = $metadata->optimizer_class();
			if ( ! is_string( $class ) || '' === $class || ! class_exists( $class ) ) {
				continue;
			}
			if ( ! $settings->is_enabled( $metadata->id() ) ) {
				continue;
			}

			$optimizer = new $class( $settings );
			if ( ! $optimizer instanceof AbstractOptimizer ) {
				continue;
			}
			if ( ! $optimizer->is_safe() ) {
				continue;
			}

			$optimizer->apply();
		}
	}
}
