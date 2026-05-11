<?php
/**
 * Suppress Elementor's AI module on sites that don't use AI features.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor's AI module (`/modules/ai/module.php`) registers ~30 AJAX
 * endpoints, two editor script bundles, and remote-config fetches whenever
 * `is_ai_enabled()` returns true. The check gates a single block of code
 * (module.php:48-100); if we can prevent that branch from running, all the
 * AI plumbing stays cold.
 *
 * Strategy is two-pronged for resilience:
 *
 *   1. Filter `elementor/ai/is_ai_enabled` to false. If Elementor reads its
 *      own toggle through this filter (the convention in 4.x), the heavy
 *      block in `Module::__construct` short-circuits before registering any
 *      AJAX action or editor script.
 *
 *   2. As a safety net, dequeue the editor-side AI handles at admin enqueue
 *      time in case Connect-side state still flipped enabled past the filter.
 *      The handle names follow Elementor's `elementor-ai-*` convention.
 */
final class AiModuleDisabler extends AbstractOptimizer {

	private const AI_SCRIPT_HANDLES = array(
		'elementor-ai',
		'elementor-ai-layout',
		'elementor-ai-media',
		'elementor-ai-product-image-unification',
	);

	public function id(): string {
		return 'f.elementor.ai_module';
	}

	public function apply(): void {
		add_filter( 'elementor/ai/is_ai_enabled', '__return_false', 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'dequeue_ai_scripts' ), 9999 );
	}

	public function dequeue_ai_scripts(): void {
		foreach ( self::AI_SCRIPT_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}
}
