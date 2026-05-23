<?php
/**
 * Per-post Feather feature overrides meta box.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\PostOverrides;

use Feather\Admin\Capability;
use Feather\FeatureRegistry\FeatureRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a sidebar meta box on the post-edit screen of any post type that
 * carries `_elementor_data` post meta (or any public post type, when no
 * Elementor data exists yet on a draft). The meta box lets editors opt out
 * of specific Feather features for that page only.
 */
final class MetaBox {

	public const NONCE_NAME   = 'feather_overrides_nonce';
	public const NONCE_ACTION = 'feather_save_overrides';
	public const FIELD_NAME   = 'feather_overrides';

	/**
	 * Feature registry.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Overrides repository.
	 *
	 * @var OverridesRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param FeatureRegistry     $registry   Feature registry.
	 * @param OverridesRepository $repository Repository.
	 */
	public function __construct( FeatureRegistry $registry, OverridesRepository $repository ) {
		$this->registry   = $registry;
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Register the meta box for public post types.
	 */
	public function add_meta_boxes(): void {
		if ( ! Capability::user_can() ) {
			return;
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'feather-page-overrides',
				__( 'Feather — Per-page overrides', 'feather-performance' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Meta box renderer.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render( $post ): void {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$this->registry->load();
		$features = $this->registry->all();
		$disabled = $this->repository->disabled_for_post( (int) $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<p style="margin:0 0 8px;color:#646970;font-size:12px;">';
		echo esc_html__( 'Check a feature to disable it on this page only. Sitewide settings are not affected.', 'feather-performance' );
		echo '</p>';

		if ( empty( $features ) ) {
			echo '<p>' . esc_html__( 'No Feather features registered yet.', 'feather-performance' ) . '</p>';
			return;
		}

		echo '<div class="feather-overrides-list" style="max-height:240px;overflow:auto;padding-right:4px;">';
		foreach ( $features as $metadata ) {
			$id = $metadata->id();
			if ( '' === $id ) {
				continue;
			}
			$checked = in_array( $id, $disabled, true ) ? ' checked' : '';
			echo '<label style="display:block;margin:6px 0;line-height:1.4;">';
			echo '<input type="checkbox" name="' . esc_attr( self::FIELD_NAME ) . '[]" value="' . esc_attr( $id ) . '"' . esc_attr( $checked ) . '> ';
			echo '<span style="font-weight:500;">' . esc_html( $metadata->label() ) . '</span>';
			echo '<br><span style="color:#646970;font-size:11px;">' . esc_html( $id ) . '</span>';
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * save_post handler — persist meta box selections.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta_box( $post_id, $post ): void {
		unset( $post );

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		// Skip autosaves, revisions, AJAX heartbeats — they don't carry the form.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bail if the nonce/field isn't present — protects against being called
		// from non-form contexts (REST autosaves, quick-edit, etc.).
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST[ self::FIELD_NAME ] ) && is_array( $_POST[ self::FIELD_NAME ] )
			? wp_unslash( $_POST[ self::FIELD_NAME ] )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$ids = array();
		foreach ( $raw as $value ) {
			if ( is_string( $value ) ) {
				$ids[] = sanitize_text_field( $value );
			}
		}

		$this->repository->set_disabled_for_post( $post_id, $ids );
	}
}
