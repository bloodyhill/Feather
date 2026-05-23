<?php
/**
 * Feature metadata value object.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\FeatureRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable description of a single Feather feature.
 *
 * Each entry in src/FeatureRegistry/features.php (and contributions from
 * the 'feather/feature_registry' filter) is hydrated into one of these.
 */
final class FeatureMetadata {

	public const RISK_SAFE  = 'safe';
	public const RISK_GATED = 'gated';
	public const RISK_RISKY = 'risky';

	public const IMPACT_LOW    = 'low';
	public const IMPACT_MEDIUM = 'medium';
	public const IMPACT_HIGH   = 'high';

	public const CATEGORY_ELEMENTOR   = 'elementor';
	public const CATEGORY_WP          = 'wp';
	public const CATEGORY_MEDIA       = 'media';
	public const CATEGORY_HINTS       = 'hints';
	public const CATEGORY_DB          = 'db';
	public const CATEGORY_CONDITIONAL = 'conditional';
	public const CATEGORY_COMPAT      = 'compat';
	public const CATEGORY_REPORTING   = 'reporting';
	public const CATEGORY_ADVANCED    = 'advanced';

	/**
	 * Stable feature id (e.g. "f.elementor.fa4_shim").
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Translatable human label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Translatable one-line description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Category bucket from the constants above.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * Risk level: safe | gated | risky.
	 *
	 * @var string
	 */
	private $risk;

	/**
	 * Impact level: low | medium | high.
	 *
	 * @var string
	 */
	private $impact;

	/**
	 * Whether the feature is enabled by default for new installs.
	 *
	 * @var bool
	 */
	private $default_enabled;

	/**
	 * Optimizer class name (extends AbstractOptimizer) implementing this feature,
	 * or null while the optimizer has not yet been built.
	 *
	 * @var string|null
	 */
	private $optimizer_class;

	/**
	 * When true, the feature is omitted from the Features-page listing.
	 *
	 * Use this for component features that are surfaced through a composite
	 * UI elsewhere (e.g. the Dashboard "Block Elementor telemetry" toggle
	 * controls three component feature ids — we don't also want them to
	 * appear as separate cards on Features).
	 *
	 * The setting still applies at runtime; the optimizer still runs when
	 * the feature is enabled; bulk-update REST calls still operate on it.
	 * It just doesn't render as its own card.
	 *
	 * @var bool
	 */
	private $hidden;

	/**
	 * Hydrate metadata from a definition array.
	 *
	 * @param array<string, mixed> $args Definition array.
	 */
	public function __construct( array $args ) {
		$this->id              = isset( $args['id'] ) ? (string) $args['id'] : '';
		$this->label           = isset( $args['label'] ) ? (string) $args['label'] : '';
		$this->description     = isset( $args['description'] ) ? (string) $args['description'] : '';
		$this->category        = isset( $args['category'] ) ? (string) $args['category'] : self::CATEGORY_WP;
		$this->risk            = isset( $args['risk'] ) ? (string) $args['risk'] : self::RISK_SAFE;
		$this->impact          = isset( $args['impact'] ) ? (string) $args['impact'] : self::IMPACT_LOW;
		$this->default_enabled = ! empty( $args['default_enabled'] );
		$this->hidden          = ! empty( $args['hidden'] );
		$this->optimizer_class = isset( $args['optimizer'] ) && is_string( $args['optimizer'] )
			? $args['optimizer']
			: null;
	}

	/**
	 * Whether this feature should be hidden from the Features-page listing.
	 *
	 * Composite-bundled features (those controlled by a Dashboard composite
	 * toggle) set this to true so they don't show as duplicate cards.
	 */
	public function is_hidden(): bool {
		return $this->hidden;
	}

	/**
	 * Stable feature id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Translatable label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Translatable description.
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Category bucket.
	 */
	public function category(): string {
		return $this->category;
	}

	/**
	 * Risk level.
	 */
	public function risk(): string {
		return $this->risk;
	}

	/**
	 * Impact level.
	 */
	public function impact(): string {
		return $this->impact;
	}

	/**
	 * Default-enabled state for new installs.
	 */
	public function default_enabled(): bool {
		return $this->default_enabled;
	}

	/**
	 * Implementing optimizer class, or null.
	 */
	public function optimizer_class(): ?string {
		return $this->optimizer_class;
	}

	/**
	 * REST-friendly serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'label'           => $this->label,
			'description'     => $this->description,
			'category'        => $this->category,
			'risk'            => $this->risk,
			'impact'          => $this->impact,
			'default_enabled' => $this->default_enabled,
			'hidden'          => $this->hidden,
		);
	}
}
