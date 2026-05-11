<?php
/**
 * Chunked site scanner orchestrator.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and processes a chunked scan of every Elementor-built post.
 *
 * Why chunked: a site with 500 Elementor pages takes ~10-15s to fully
 * parse. That's far too long for a single web request. Instead the
 * scanner queues post ids in a transient and processes ~25 per WP-Cron
 * tick (`feather/scan/tick`), persisting after each batch.
 *
 * The React client polls the status endpoint and renders a progress
 * ring. Cron is triggered by the polling itself (admin pageloads fire
 * `wp-cron`), so polling keeps the scan moving forward.
 */
final class SiteScanner {

	public const HOOK_TICK = 'feather/scan/tick';
	public const BATCH_SIZE = 25;
	public const MAX_QUEUE_SIZE = 500;
	public const TRANSIENT_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * JSON parser.
	 *
	 * @var ElementorJsonParser
	 */
	private $parser;

	/**
	 * Scan repository.
	 *
	 * @var ScanRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param ElementorJsonParser $parser     Parser.
	 * @param ScanRepository      $repository Repository.
	 */
	public function __construct( ElementorJsonParser $parser, ScanRepository $repository ) {
		$this->parser     = $parser;
		$this->repository = $repository;
	}

	/**
	 * Register the cron tick handler. Called once during plugin boot.
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_TICK, array( $this, 'tick' ) );
	}

	/**
	 * Begin a new scan. Replaces any in-progress scan.
	 *
	 * @return ScanState The fresh state.
	 */
	public function start(): ScanState {
		// Refuse to start when the destination table is missing — otherwise the
		// scan would report "N pages scanned" while every save silently failed.
		if ( ! $this->repository->table_exists() ) {
			$failed = new ScanState(
				ScanState::STATE_FAILED,
				array(),
				0,
				0,
				time(),
				time(),
				__( "Feather's scan table is missing. Re-activate Feather (Plugins → Feather → Deactivate, then Activate) and try again.", 'feather-performance' )
			);
			$this->save_state( $failed );
			return $failed;
		}

		$queue   = $this->build_queue();
		$total   = count( $queue );
		$state   = new ScanState(
			ScanState::STATE_RUNNING,
			$queue,
			$total,
			0,
			time()
		);

		$this->save_state( $state );

		// Truncate previous results so the user sees a clean slate while the new
		// scan repopulates rows. (Aggregate cache is invalidated by ScanRepository.)
		$this->repository->truncate();

		$this->schedule_next_tick();
		return $state;
	}

	/**
	 * Cancel an in-progress scan.
	 */
	public function cancel(): ScanState {
		$state = $this->load_state();
		if ( null === $state || ! $state->is_running() ) {
			return $state ?? $this->idle_state();
		}
		$canceled = new ScanState(
			ScanState::STATE_CANCELED,
			array(),
			$state->total(),
			$state->processed(),
			$state->started_at(),
			time()
		);
		$this->save_state( $canceled );
		return $canceled;
	}

	/**
	 * Current scan status, or an idle state if no scan has run.
	 */
	public function status(): ScanState {
		return $this->load_state() ?? $this->idle_state();
	}

	/**
	 * Cron handler — process the next batch.
	 */
	public function tick(): void {
		$state = $this->load_state();
		if ( null === $state || ! $state->is_running() ) {
			return;
		}

		$queue = $state->queue();
		if ( empty( $queue ) ) {
			$this->finish( $state );
			return;
		}

		$batch     = array_slice( $queue, 0, self::BATCH_SIZE );
		$remaining = array_slice( $queue, self::BATCH_SIZE );

		$saved    = 0;
		$attempted = 0;
		foreach ( $batch as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}
			++$attempted;
			try {
				$result   = $this->parser->parse( $post_id );
				$post     = get_post( $post_id );
				$modified = $post instanceof \WP_Post ? (string) $post->post_modified_gmt : '';
				if ( $this->repository->save( $result, $modified ) ) {
					++$saved;
				}
			} catch ( \Throwable $e ) {
				// Continue scanning even if one post is broken; the queue itself
				// is the source of truth — a swallowed error here just means that
				// post has no row in the results table.
				continue;
			}
		}

		// If we tried to save anything in this batch and not a single insert
		// stuck, the table is missing or otherwise unwritable. Fail loudly
		// instead of marching the queue down to zero with no rows persisted.
		if ( $attempted > 0 && 0 === $saved ) {
			$failed_state = new ScanState(
				ScanState::STATE_FAILED,
				array(),
				$state->total(),
				$state->processed(),
				$state->started_at(),
				time(),
				__( "Feather's scan table is missing or unwritable — no rows could be saved. Re-activate Feather to recreate it.", 'feather-performance' )
			);
			$this->save_state( $failed_state );
			return;
		}

		$next_state = new ScanState(
			ScanState::STATE_RUNNING,
			$remaining,
			$state->total(),
			$state->processed() + $saved,
			$state->started_at()
		);

		if ( empty( $remaining ) ) {
			$this->finish( $next_state );
			return;
		}

		$this->save_state( $next_state );
		$this->schedule_next_tick();
	}

	/**
	 * Build the queue of post ids to scan.
	 *
	 * Currently: every public post type that has `_elementor_data` meta.
	 * Capped at MAX_QUEUE_SIZE for v1 to keep scan time bounded; bigger
	 * sites get a "sample mode" notice in the UI.
	 *
	 * @return int[]
	 */
	private function build_queue(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_status = %s
				 ORDER BY p.post_modified_gmt DESC
				 LIMIT %d",
				'_elementor_data',
				'publish',
				self::MAX_QUEUE_SIZE
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Mark the scan complete.
	 */
	private function finish( ScanState $state ): void {
		$final = new ScanState(
			ScanState::STATE_COMPLETE,
			array(),
			$state->total(),
			$state->processed(),
			$state->started_at(),
			time()
		);
		$this->save_state( $final );
	}

	/**
	 * Schedule the next tick if one isn't already pending.
	 */
	private function schedule_next_tick(): void {
		if ( false === wp_next_scheduled( self::HOOK_TICK ) ) {
			wp_schedule_single_event( time() + 1, self::HOOK_TICK );
		}
	}

	/**
	 * Persist scan state to a transient.
	 */
	private function save_state( ScanState $state ): void {
		set_transient( ScanState::TRANSIENT_KEY, $state->to_storage(), self::TRANSIENT_TTL );
	}

	/**
	 * Load scan state from the transient.
	 */
	private function load_state(): ?ScanState {
		$raw = get_transient( ScanState::TRANSIENT_KEY );
		return ScanState::from_array( $raw );
	}

	/**
	 * Synthetic idle state when no scan has ever run on this install.
	 */
	private function idle_state(): ScanState {
		return new ScanState( ScanState::STATE_IDLE, array(), 0, 0, 0 );
	}
}
