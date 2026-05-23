/**
 * Shared TypeScript types for the Feather Performance admin UI.
 * Mirror these in packages/feather-performance/src/Rest/* serialization shapes.
 */

export type FeatureRisk = 'safe' | 'gated' | 'risky';
export type FeatureImpact = 'low' | 'medium' | 'high';
export type FeatureRecommendation =
	| 'safe'
	| 'scan_recommended'
	| 'risky'
	| 'dangerous';
export type FeatureCategory =
	| 'elementor'
	| 'wp'
	| 'media'
	| 'hints'
	| 'db'
	| 'conditional'
	| 'compat'
	| 'reporting'
	| 'advanced';

export type Preset = 'conservative' | 'balanced' | 'aggressive' | 'custom';
export type Theme = 'system' | 'light' | 'dark';

export interface Feature {
	id: string;
	label: string;
	description: string;
	category: FeatureCategory;
	risk: FeatureRisk;
	impact: FeatureImpact;
	default_enabled: boolean;
	/**
	 * When true, this feature is surfaced through a composite UI element
	 * (e.g. the Dashboard "Block Elementor telemetry" toggle) and should
	 * not render as its own card on the Features page.
	 */
	hidden?: boolean;
	enabled: boolean;
	recommendation: FeatureRecommendation;
}

export type ScanState = 'idle' | 'running' | 'complete' | 'failed' | 'canceled';

export interface ScanStatus {
	state: ScanState;
	total: number;
	processed: number;
	remaining: number;
	started_at: number;
	finished_at: number;
	error: string;
}

export interface ScanAggregate {
	total_pages: number;
	has_results: boolean;
	eicons_usage_count: number;
	fa_icons_usage_count: number;
	google_fonts_usage_count: number;
	lottie_usage_count: number;
	global_classes_usage_count?: number;
	has_atomic_widgets_anywhere?: boolean;
	widget_counts: Record< string, number >;
	asset_counts: Record< string, number >;
	last_scanned_at: number | null;
}

export interface ScanResultRow {
	post_id: number;
	widget_types: string[];
	asset_handles: string[];
	settings_flags: Record< string, boolean >;
	scanned_at: string;
}

export interface ScanResultsPage {
	rows: ScanResultRow[];
	total: number;
}

export type DbToolName =
	| 'transients'
	| 'elementor_revisions'
	| 'oembed_cache'
	| 'spam_comments'
	| 'auto_drafts';

export interface DbAutoloadEntry {
	option_name: string;
	bytes: number;
}

export interface DbAutoloadAudit {
	total_bytes: number;
	top: DbAutoloadEntry[];
}

export interface DbHealth {
	score: number;
	autoload_bytes: number;
	autoload_largest: DbAutoloadEntry[];
	expired_transients: number;
	elementor_orphan_revisions: number;
	oembed_cached_entries: number;
	spam_comments: number;
	auto_drafts: number;
}

export interface DbCleanupResult {
	deleted: number;
	remaining?: number;
	health: DbHealth;
}

export interface PageWeightSnapshot {
	url: string;
	html_bytes: number;
	scripts: number;
	stylesheets: number;
	images: number;
	iframes: number;
	fonts: number;
	total_assets: number;
	measured_at: number;
	http_status: number;
	response_ms?: number;
	ttfb_ms?: number;
	largest_image_bytes?: number;
	error: string | null;
	recorded_at?: string;
}

export interface MetricsHistory {
	rows: PageWeightSnapshot[];
	count: number;
}

export type OnboardingStateName = 'pending' | 'scanned' | 'completed';

export interface OnboardingStatus {
	state: OnboardingStateName;
	show_banner: boolean;
}

export interface DetectedPlugin {
	slug: string;
	name: string;
}

export interface SystemInfo {
	plugin: {
		version: string;
		install_date: string;
		schema_version: number;
	};
	env: {
		wp_version: string;
		php_version: string;
		multisite: boolean;
		locale: string;
	};
	detected: {
		cache: DetectedPlugin[];
		image_optimizer: DetectedPlugin[];
		builders: DetectedPlugin[];
	};
}

export interface FeaturesResponse {
	features: Feature[];
	preset: Preset;
}

export interface SettingsResponse {
	schema_version: number;
	preset: Preset;
	theme: Theme;
	usage_opt_in: boolean;
	optimizers_paused: boolean;
	advanced?: {
		heartbeat?: HeartbeatAdvanced;
		experiments?: Record< string, boolean >;
	};
}

export interface HeartbeatAdvanced {
	frontend_disabled: boolean;
	editor_interval: number;
	admin_interval: number;
}

export interface SettingsExport {
	kind: 'feather-settings-export';
	export_version: number;
	feather_version: string;
	schema_version: number;
	generated_at: string;
	settings: {
		preset: Preset;
		theme: Theme;
		usage_opt_in: boolean;
		optimizers_paused: boolean;
		features: Record< string, boolean >;
		advanced: Record< string, unknown >;
	};
}

export interface SettingsImportSummary {
	applied: boolean;
	feature_count: number;
	unknown_features: string[];
}

export interface PostOverrides {
	post_id: number;
	disabled: string[];
}

export interface BootstrapPayload {
	version: string;
	restRoot: string;
	restNonce: string;
	siteUrl: string;
	adminUrl: string;
	locale: string;
	isMultisite: boolean;
	currentRoute: string;
	routeMap: Record< string, string >;
	theme: Theme;
	logos: {
		ink: string;
		cream: string;
		brand: string;
	};
	user: {
		id: number;
		displayName: string;
	};
}

declare global {
	interface Window {
		feather?: {
			boot?: BootstrapPayload;
		};
	}
}
