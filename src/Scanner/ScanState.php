<?php
/**
 * Persisted state for an in-progress site scan.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight value object stored as a transient while a scan runs.
 *
 * The scanner reads this between ticks to know which post ids remain in
 * the queue and pushes updates after each batch. The REST status
 * endpoint serializes this directly to the React client.
 */
final class ScanState {

	public const STATE_IDLE     = 'idle';
	public const STATE_RUNNING  = 'running';
	public const STATE_COMPLETE = 'complete';
	public const STATE_FAILED   = 'failed';
	public const STATE_CANCELED = 'canceled';

	public const TRANSIENT_KEY = 'feather_scan_state';

	/**
	 * Current state.
	 *
	 * @var string
	 */
	private $state;

	/**
	 * Total post ids in the original queue.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Post ids still to process.
	 *
	 * @var int[]
	 */
	private $queue;

	/**
	 * Number of posts processed so far.
	 *
	 * @var int
	 */
	private $processed;

	/**
	 * UNIX timestamp the scan started.
	 *
	 * @var int
	 */
	private $started_at;

	/**
	 * UNIX timestamp the scan finished, or 0 while running.
	 *
	 * @var int
	 */
	private $finished_at;

	/**
	 * Optional last error message.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Constructor.
	 *
	 * @param string $state       State constant.
	 * @param int[]  $queue       Remaining queue.
	 * @param int    $total       Total queue size at start.
	 * @param int    $processed   Items processed so far.
	 * @param int    $started_at  Start timestamp.
	 * @param int    $finished_at Finish timestamp (0 while running).
	 * @param string $error       Optional error message.
	 */
	public function __construct(
		string $state,
		array $queue,
		int $total,
		int $processed,
		int $started_at,
		int $finished_at = 0,
		string $error = ''
	) {
		$this->state       = $state;
		$this->queue       = array_values( array_map( 'intval', $queue ) );
		$this->total       = $total;
		$this->processed   = $processed;
		$this->started_at  = $started_at;
		$this->finished_at = $finished_at;
		$this->error       = $error;
	}

	public function state(): string {
		return $this->state;
	}

	/**
	 * @return int[]
	 */
	public function queue(): array {
		return $this->queue;
	}

	public function total(): int {
		return $this->total;
	}

	public function processed(): int {
		return $this->processed;
	}

	public function started_at(): int {
		return $this->started_at;
	}

	public function finished_at(): int {
		return $this->finished_at;
	}

	public function error(): string {
		return $this->error;
	}

	public function is_running(): bool {
		return self::STATE_RUNNING === $this->state;
	}

	public function is_terminal(): bool {
		return in_array(
			$this->state,
			array( self::STATE_COMPLETE, self::STATE_FAILED, self::STATE_CANCELED ),
			true
		);
	}

	/**
	 * REST-friendly serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'state'        => $this->state,
			'total'        => $this->total,
			'processed'    => $this->processed,
			'remaining'    => count( $this->queue ),
			'started_at'   => $this->started_at,
			'finished_at'  => $this->finished_at,
			'error'        => $this->error,
		);
	}

	/**
	 * Hydrate from a transient payload.
	 *
	 * @param mixed $raw Raw transient value.
	 */
	public static function from_array( $raw ): ?self {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$queue = isset( $raw['queue'] ) && is_array( $raw['queue'] ) ? $raw['queue'] : array();
		return new self(
			isset( $raw['state'] ) ? (string) $raw['state'] : self::STATE_IDLE,
			$queue,
			isset( $raw['total'] ) ? (int) $raw['total'] : 0,
			isset( $raw['processed'] ) ? (int) $raw['processed'] : 0,
			isset( $raw['started_at'] ) ? (int) $raw['started_at'] : 0,
			isset( $raw['finished_at'] ) ? (int) $raw['finished_at'] : 0,
			isset( $raw['error'] ) ? (string) $raw['error'] : ''
		);
	}

	/**
	 * Storage shape — distinct from REST shape because storage keeps the queue.
	 *
	 * @return array<string, mixed>
	 */
	public function to_storage(): array {
		return array(
			'state'        => $this->state,
			'queue'        => $this->queue,
			'total'        => $this->total,
			'processed'    => $this->processed,
			'started_at'   => $this->started_at,
			'finished_at'  => $this->finished_at,
			'error'        => $this->error,
		);
	}
}
