<?php
/**
 * Auto-rescan an Elementor post when it is saved or published.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `save_post` and queues a single-post rescan so gated optimizations
 * stay accurate without waiting for the next full site scan.
 *
 * Why per-post and not "schedule a full scan": full scans truncate the
 * results table and walk the whole site over ~25-post cron batches —
 * disproportionate work for a single edit. The single-post path reuses the
 * scanner's parser + repository, persists one row, and invalidates the
 * aggregate cache through ScanRepository::save().
 *
 * Throttling: the listener stamps a per-post transient on each run and
 * skips re-scans within RESCAN_THROTTLE_SECONDS to avoid duplicate work
 * when WordPress fires save_post multiple times for a single editor save
 * (autosaves, revisions, REST autosaves, page-builder background saves).
 */
final class PostSaveRescanListener {

	public const FEATURE_ID = 'f.scanner.auto_rescan';

	public const RESCAN_THROTTLE_SECONDS = 60;

	public const TRANSIENT_PREFIX = 'feather_rescan_throttle_';

	/**
	 * Settings repository — read to confirm the auto-rescan feature is on.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Scanner.
	 *
	 * @var SiteScanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 * @param SiteScanner        $scanner  Scanner.
	 */
	public function __construct( SettingsRepository $settings, SiteScanner $scanner ) {
		$this->settings = $settings;
		$this->scanner  = $scanner;
	}

	/**
	 * Register save_post hooks.
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
	}

	/**
	 * save_post handler.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_save_post( $post_id, $post, $update ): void {
		unset( $update );

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		// Skip revisions, autosaves, and trash transitions.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status && 'private' !== $post->post_status ) {
			return;
		}

		if ( ! $this->settings->is_enabled( self::FEATURE_ID ) ) {
			return;
		}

		// Only rescan posts that actually have Elementor data.
		$has_elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $has_elementor_data ) || '' === $has_elementor_data ) {
			return;
		}

		// Per-post throttle: skip if we rescanned this post within the window.
		$transient_key = self::TRANSIENT_PREFIX . $post_id;
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, self::RESCAN_THROTTLE_SECONDS );

		$this->scanner->rescan_post( $post_id );
	}
}
