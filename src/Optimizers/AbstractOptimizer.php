<?php
/**
 * Optimizer contract.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers;

use Feather\PostOverrides\OverridesRepository;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Base class every Feather optimizer extends.
 *
 * Optimizers register themselves declaratively via FeatureMetadata in
 * src/FeatureRegistry/features.php. The Plugin orchestrator instantiates
 * each optimizer whose:
 *   1. Feature is unlocked by FeatureGate, AND
 *   2. User setting is enabled, AND
 *   3. is_safe() returns true,
 * then calls apply() during plugins_loaded.
 */
abstract class AbstractOptimizer {

	/**
	 * Settings repository, available to subclasses for reading user prefs.
	 *
	 * @var SettingsRepository
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Stable feature id this optimizer implements.
	 *
	 * Must match a FeatureMetadata::id() registered in features.php.
	 *
	 * Example: "f.elementor.fa4_shim".
	 */
	abstract public function id(): string;

	/**
	 * Whether the optimizer can be safely applied right now.
	 *
	 * Subclasses override to consult the scanner verdict, detect cache
	 * plugins, or check WP/Elementor version constraints.
	 *
	 * Default: safe.
	 */
	public function is_safe(): bool {
		return true;
	}

	/**
	 * Register WordPress hooks to perform the optimization.
	 *
	 * Called once during plugins_loaded after the gate confirms the
	 * feature is unlocked and the user setting is enabled.
	 */
	abstract public function apply(): void;

	/**
	 * Detach previously-registered hooks.
	 *
	 * Used when the feature is toggled off within the same request
	 * (e.g. via REST). Default no-op for hooks that only matter at boot —
	 * those will simply not be re-registered next request.
	 */
	public function revert(): void {
		// Default no-op.
	}

	/**
	 * Default WordPress hook priority for any add_action/add_filter
	 * inside apply(). Override per-feature when order matters.
	 */
	public function priority(): int {
		return 10;
	}

	/**
	 * Whether this optimizer should skip work for the current request because
	 * the queried post opted out via the per-page overrides meta box.
	 *
	 * Frontend-affecting callbacks should call this at the top and return
	 * early when it reports true. Returns false in admin / REST / cron / CLI
	 * contexts so the override never interferes with WP-admin or background
	 * work.
	 */
	protected function is_disabled_for_current_request(): bool {
		static $repository = null;
		if ( null === $repository ) {
			$repository = new OverridesRepository();
		}
		return $repository->is_disabled_for_current_request( $this->id() );
	}
}
