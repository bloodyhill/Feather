/**
 * Thin wrapper around @wordpress/api-fetch that targets the feather/v1 namespace.
 *
 * apiFetch already handles cookie auth + the X-WP-Nonce header when WP enqueues
 * `wp-api-fetch`. We just need to set the rest_root + nonce from the bootstrap
 * payload before any request fires.
 */

import apiFetch from '@wordpress/api-fetch';
import type {
	DbAutoloadAudit,
	DbCleanupResult,
	DbHealth,
	DbToolName,
	Feature,
	FeaturesResponse,
	MetricsHistory,
	OnboardingStateName,
	OnboardingStatus,
	PageWeightSnapshot,
	Preset,
	SystemInfo,
	ScanAggregate,
	ScanResultsPage,
	ScanStatus,
	SettingsResponse,
	Theme,
} from '../types';

let initialized = false;

export function initApiClient(): void {
	if ( initialized ) {
		return;
	}
	const boot = window.feather?.boot;
	if ( ! boot ) {
		// eslint-disable-next-line no-console
		console.warn(
			'[feather] No bootstrap payload found on window.feather.boot. REST calls may fail.'
		);
		initialized = true;
		return;
	}
	apiFetch.use( apiFetch.createRootURLMiddleware( boot.restRoot.replace( /\/feather\/v1\/?$/, '/' ) ) );
	apiFetch.use( apiFetch.createNonceMiddleware( boot.restNonce ) );
	initialized = true;
}

function path( route: string ): string {
	return `/feather/v1${ route.startsWith( '/' ) ? route : `/${ route }` }`;
}

export async function fetchFeatures(): Promise< FeaturesResponse > {
	return apiFetch< FeaturesResponse >( { path: path( '/features' ) } );
}

export async function toggleFeature(
	id: string,
	enabled: boolean
): Promise< Feature > {
	return apiFetch< Feature >( {
		path: path( `/features/${ encodeURIComponent( id ) }/toggle` ),
		method: 'POST',
		data: { id, enabled },
	} );
}

export async function bulkUpdateFeatures(
	updates: Record< string, boolean >
): Promise< { applied: string[]; skipped: Array< { id: string; reason: string } > } > {
	return apiFetch( {
		path: path( '/features/bulk' ),
		method: 'POST',
		data: { updates },
	} );
}

export async function fetchSettings(): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( { path: path( '/settings' ) } );
}

export async function updateSettings(
	patch: Partial< { preset: Preset; theme: Theme; usage_opt_in: boolean; optimizers_paused: boolean } >
): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( {
		path: path( '/settings' ),
		method: 'POST',
		data: patch,
	} );
}

export async function startScan(): Promise< ScanStatus > {
	return apiFetch< ScanStatus >( {
		path: path( '/scan/start' ),
		method: 'POST',
	} );
}

export async function cancelScan(): Promise< ScanStatus > {
	return apiFetch< ScanStatus >( {
		path: path( '/scan/cancel' ),
		method: 'POST',
	} );
}

export async function fetchScanStatus(): Promise< ScanStatus > {
	return apiFetch< ScanStatus >( { path: path( '/scan/status' ) } );
}

export async function fetchScanAggregate(): Promise< ScanAggregate > {
	return apiFetch< ScanAggregate >( { path: path( '/scan/aggregate' ) } );
}

export async function fetchScanResults(
	page = 1,
	perPage = 50
): Promise< ScanResultsPage > {
	return apiFetch< ScanResultsPage >( {
		path: path( `/scan/results?page=${ page }&per_page=${ perPage }` ),
	} );
}

export async function fetchDbHealth(): Promise< DbHealth > {
	return apiFetch< DbHealth >( { path: path( '/db/health' ) } );
}

export async function fetchDbAutoload(): Promise< DbAutoloadAudit > {
	return apiFetch< DbAutoloadAudit >( { path: path( '/db/autoload' ) } );
}

export async function runDbCleanup( tool: DbToolName ): Promise< DbCleanupResult > {
	return apiFetch< DbCleanupResult >( {
		path: path( `/db/cleanup/${ encodeURIComponent( tool ) }` ),
		method: 'POST',
	} );
}

export async function captureMetrics(): Promise< PageWeightSnapshot > {
	return apiFetch< PageWeightSnapshot >( {
		path: path( '/metrics/capture' ),
		method: 'POST',
	} );
}

export async function fetchLatestMetrics(): Promise< PageWeightSnapshot | null > {
	return apiFetch< PageWeightSnapshot | null >( { path: path( '/metrics/latest' ) } );
}

export async function fetchMetricsHistory( limit = 30 ): Promise< MetricsHistory > {
	return apiFetch< MetricsHistory >( {
		path: path( `/metrics/history?limit=${ limit }` ),
	} );
}

export async function fetchOnboardingState(): Promise< OnboardingStatus > {
	return apiFetch< OnboardingStatus >( { path: path( '/onboarding/state' ) } );
}

export async function setOnboardingState(
	state: OnboardingStateName
): Promise< OnboardingStatus > {
	return apiFetch< OnboardingStatus >( {
		path: path( '/onboarding/state' ),
		method: 'POST',
		data: { state },
	} );
}

export async function fetchSystemInfo(): Promise< SystemInfo > {
	return apiFetch< SystemInfo >( { path: path( '/system/info' ) } );
}

export async function resetSettings(): Promise< unknown > {
	return apiFetch( { path: path( '/settings/reset' ), method: 'POST' } );
}

export async function clearScanData(): Promise< unknown > {
	return apiFetch( { path: path( '/scan/clear' ), method: 'POST' } );
}

export async function clearMetricsData(): Promise< unknown > {
	return apiFetch( { path: path( '/metrics/clear' ), method: 'POST' } );
}
