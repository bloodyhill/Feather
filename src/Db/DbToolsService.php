<?php
/**
 * Database tools — audit and cleanup operations.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Db;

defined( 'ABSPATH' ) || exit;

/**
 * One-shot operations the user invokes from the Database screen.
 *
 * Distinct from the AbstractOptimizer pattern (which is for always-on
 * frontend hooks): each method here performs a discrete read or write,
 * returns a stats array, and is invoked via the REST DbToolsEndpoint.
 *
 * Every method is read-only-by-default and performs writes only when the
 * caller explicitly opts in via a $execute argument.
 */
final class DbToolsService {

	public const TOOL_TRANSIENTS         = 'transients';
	public const TOOL_ELEMENTOR_REVISIONS = 'elementor_revisions';
	public const TOOL_OEMBED_CACHE       = 'oembed_cache';
	public const TOOL_AUTOLOAD_AUDIT     = 'autoload_audit';
	public const TOOL_SPAM_COMMENTS      = 'spam_comments';
	public const TOOL_AUTO_DRAFTS        = 'auto_drafts';

	public const BATCH_LIMIT = 200;

	/**
	 * Composite "health" snapshot for the dashboard tile.
	 *
	 * @return array<string, mixed>
	 */
	public function health(): array {
		$autoload = $this->autoload_audit();

		$transient_count = $this->count_expired_transients();
		$revisions_count = $this->count_orphan_elementor_revisions();
		$oembed_count    = $this->count_oembed_transients();
		$spam_count      = $this->count_spam_comments();
		$auto_drafts     = $this->count_auto_drafts();

		// Score: 100 - penalties, clamped 0..100.
		$score = 100;
		if ( $autoload['total_bytes'] > 1_500_000 ) {
			$score -= 20;
		} elseif ( $autoload['total_bytes'] > 800_000 ) {
			$score -= 10;
		}
		if ( $transient_count > 500 ) {
			$score -= 15;
		} elseif ( $transient_count > 100 ) {
			$score -= 5;
		}
		if ( $revisions_count > 200 ) {
			$score -= 10;
		}
		if ( $oembed_count > 100 ) {
			$score -= 5;
		}
		if ( $spam_count > 200 ) {
			$score -= 10;
		} elseif ( $spam_count > 50 ) {
			$score -= 5;
		}
		if ( $auto_drafts > 50 ) {
			$score -= 5;
		}
		$score = max( 0, min( 100, $score ) );

		return array(
			'score'                       => $score,
			'autoload_bytes'              => $autoload['total_bytes'],
			'autoload_largest'            => $autoload['top'],
			'expired_transients'          => $transient_count,
			'elementor_orphan_revisions'  => $revisions_count,
			'oembed_cached_entries'       => $oembed_count,
			'spam_comments'               => $spam_count,
			'auto_drafts'                 => $auto_drafts,
		);
	}

	/**
	 * Audit autoloaded options: total size + the 10 largest entries.
	 *
	 * @return array{total_bytes: int, top: array<int, array<string, int|string>>}
	 */
	public function autoload_audit(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on') ORDER BY bytes DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$top = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$top[] = array(
					'option_name' => isset( $row['option_name'] ) ? (string) $row['option_name'] : '',
					'bytes'       => isset( $row['bytes'] ) ? (int) $row['bytes'] : 0,
				);
			}
		}

		return array(
			'total_bytes' => $total,
			'top'         => $top,
		);
	}

	/**
	 * Count transients whose timeout sibling indicates expiration.
	 */
	public function count_expired_transients(): int {
		global $wpdb;
		$now = (int) time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			)
		);
	}

	/**
	 * Delete every expired transient and its companion value row.
	 *
	 * @return array{deleted: int}
	 */
	public function cleanup_transients(): array {
		global $wpdb;
		$now = (int) time();

		// First, find the timeout rows that are expired.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			)
		);

		if ( empty( $expired ) ) {
			return array( 'deleted' => 0 );
		}

		$deleted = 0;
		foreach ( $expired as $timeout_name ) {
			$value_name = '_transient_' . substr( (string) $timeout_name, strlen( '_transient_timeout_' ) );
			delete_option( (string) $timeout_name );
			delete_option( $value_name );
			++$deleted;
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Count Elementor revisions on already-trashed/auto-draft posts.
	 */
	public function count_orphan_elementor_revisions(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE p.post_type = %s
				   AND pm.meta_key = %s",
				'revision',
				'_elementor_data'
			)
		);
	}

	/**
	 * Bulk-delete revision posts that carry `_elementor_data` meta.
	 *
	 * @return array{deleted: int}
	 */
	public function cleanup_elementor_revisions(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE p.post_type = %s
				   AND pm.meta_key = %s",
				'revision',
				'_elementor_data'
			)
		);

		$deleted = 0;
		foreach ( $ids as $id ) {
			$post_id = (int) $id;
			if ( $post_id > 0 && wp_delete_post_revision( $post_id ) ) {
				++$deleted;
			}
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Count cached oEmbed entries.
	 */
	public function count_oembed_transients(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
				'_oembed_%',
				'_oembed_time_%'
			)
		);
	}

	/**
	 * Drop oEmbed cache rows.
	 *
	 * @return array{deleted: int}
	 */
	public function cleanup_oembed(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
				'_oembed_%',
				'_oembed_time_%'
			)
		);
		return array( 'deleted' => max( 0, $deleted ) );
	}

	/**
	 * Count comments marked as spam.
	 */
	public function count_spam_comments(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
				'spam'
			)
		);
	}

	/**
	 * Delete spam comments in batches. Returns the number deleted.
	 *
	 * @return array{deleted: int, remaining: int}
	 */
	public function cleanup_spam_comments(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT %d",
				'spam',
				self::BATCH_LIMIT
			)
		);

		$deleted = 0;
		foreach ( (array) $ids as $id ) {
			$comment_id = (int) $id;
			if ( $comment_id > 0 && wp_delete_comment( $comment_id, true ) ) {
				++$deleted;
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => $this->count_spam_comments(),
		);
	}

	/**
	 * Count posts in the `auto-draft` post_status. WordPress periodically
	 * leaves these behind when users click "Add New" and walk away.
	 */
	public function count_auto_drafts(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
				'auto-draft'
			)
		);
	}

	/**
	 * Delete auto-draft posts in batches via wp_delete_post() so attached
	 * meta / terms / comments are cleaned up via core's hooks.
	 *
	 * @return array{deleted: int, remaining: int}
	 */
	public function cleanup_auto_drafts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = %s LIMIT %d",
				'auto-draft',
				self::BATCH_LIMIT
			)
		);

		$deleted = 0;
		foreach ( (array) $ids as $id ) {
			$post_id = (int) $id;
			if ( $post_id > 0 && wp_delete_post( $post_id, true ) ) {
				++$deleted;
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => $this->count_auto_drafts(),
		);
	}
}
