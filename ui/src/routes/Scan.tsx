import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import {
	cancelScan,
	fetchScanAggregate,
	fetchScanResults,
	fetchScanStatus,
	startScan,
} from '../api/client';
import type { ScanAggregate, ScanResultsPage, ScanStatus } from '../types';

const POLL_INTERVAL_MS = 1500;

export default function Scan(): JSX.Element {
	const [ status, setStatus ] = useState< ScanStatus | null >( null );
	const [ aggregate, setAggregate ] = useState< ScanAggregate | null >( null );
	const [ results, setResults ] = useState< ScanResultsPage | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ pending, setPending ] = useState( false );
	const pollTimer = useRef< number | null >( null );
	const prevStateRef = useRef< string | undefined >( undefined );

	useEffect( () => {
		void refreshAll();
		return () => stopPolling();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		const prev = prevStateRef.current;
		prevStateRef.current = status?.state;

		if ( status?.state === 'running' ) {
			startPolling();
			return;
		}
		stopPolling();

		// Refresh aggregate + results on any transition out of `running`, not
		// only on `complete`. Some object-cache configurations briefly return
		// `idle` after the transient is overwritten by a final state, and the
		// rows themselves are already persisted — re-reading on every exit
		// from `running` makes the UI converge without a full page reload.
		if ( prev === 'running' ) {
			void refreshAggregate();
			void refreshResults();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ status?.state ] );

	function startPolling(): void {
		stopPolling();
		pollTimer.current = window.setInterval( () => {
			void fetchScanStatus()
				.then( setStatus )
				.catch( ( err: Error ) => setError( err.message ) );
		}, POLL_INTERVAL_MS );
	}

	function stopPolling(): void {
		if ( pollTimer.current !== null ) {
			window.clearInterval( pollTimer.current );
			pollTimer.current = null;
		}
	}

	async function refreshAll(): Promise< void > {
		try {
			const [ s, a, r ] = await Promise.all( [
				fetchScanStatus(),
				fetchScanAggregate(),
				fetchScanResults( 1, 50 ),
			] );
			setStatus( s );
			setAggregate( a );
			setResults( r );
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Failed to load scan state.', 'feather-performance' )
			);
		}
	}

	async function refreshAggregate(): Promise< void > {
		try {
			setAggregate( await fetchScanAggregate() );
		} catch ( _ ) {
			/* swallow — UI just shows the previous values */
		}
	}

	async function refreshResults(): Promise< void > {
		try {
			setResults( await fetchScanResults( 1, 50 ) );
		} catch ( _ ) {
			/* swallow */
		}
	}

	async function handleStart(): Promise< void > {
		setPending( true );
		setError( null );
		try {
			const s = await startScan();
			setStatus( s );
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Could not start scan.', 'feather-performance' )
			);
		} finally {
			setPending( false );
		}
	}

	async function handleCancel(): Promise< void > {
		setPending( true );
		try {
			const s = await cancelScan();
			setStatus( s );
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Could not cancel scan.', 'feather-performance' )
			);
		} finally {
			setPending( false );
		}
	}

	if ( ! status ) {
		return (
			<div className="feather-loading">
				<Spinner />
			</div>
		);
	}

	const progressPct =
		status.total > 0 ? Math.round( ( status.processed / status.total ) * 100 ) : 0;

	return (
		<div className="feather-scan">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Card className="feather-scan-header">
				<CardHeader>
					<h2>{ __( 'Site Scan', 'feather-performance' ) }</h2>
					<ScanActions
						state={ status.state }
						pending={ pending }
						onStart={ handleStart }
						onCancel={ handleCancel }
					/>
				</CardHeader>
				<CardBody>
					<ScanStatusLine status={ status } progressPct={ progressPct } />
				</CardBody>
			</Card>

			{ aggregate && aggregate.has_results && (
				<>
					<AggregateSummary aggregate={ aggregate } />
					<ResultsTabs aggregate={ aggregate } results={ results } />
				</>
			) }

			{ aggregate && ! aggregate.has_results && status.state !== 'running' && (
				<Card>
					<CardBody>
						<p>
							{ __(
								'No scan results yet. Run a scan to see which Elementor widgets and assets your site uses.',
								'feather-performance'
							) }
						</p>
						<p>
							<small>
								{ __(
									'Feather reads your saved Elementor data only. No pages are rendered. No data leaves your server.',
									'feather-performance'
								) }
							</small>
						</p>
					</CardBody>
				</Card>
			) }
		</div>
	);
}

function ScanActions( {
	state,
	pending,
	onStart,
	onCancel,
}: {
	state: ScanStatus[ 'state' ];
	pending: boolean;
	onStart: () => void;
	onCancel: () => void;
} ): JSX.Element {
	if ( state === 'running' ) {
		return (
			<Button variant="secondary" onClick={ onCancel } isBusy={ pending } disabled={ pending }>
				{ __( 'Cancel scan', 'feather-performance' ) }
			</Button>
		);
	}
	return (
		<Button variant="primary" onClick={ onStart } isBusy={ pending } disabled={ pending }>
			{ state === 'complete' ? __( 'Re-run scan', 'feather-performance' ) : __( 'Run scan', 'feather-performance' ) }
		</Button>
	);
}

function ScanStatusLine( {
	status,
	progressPct,
}: {
	status: ScanStatus;
	progressPct: number;
} ): JSX.Element {
	if ( status.state === 'running' ) {
		return (
			<div>
				<p>
					{ sprintf(
						/* translators: %1$d processed, %2$d total */
						__( 'Scanning %1$d of %2$d pages…', 'feather-performance' ),
						status.processed,
						status.total
					) }
				</p>
				<div className="feather-progress" role="progressbar" aria-valuenow={ progressPct } aria-valuemin={ 0 } aria-valuemax={ 100 }>
					<div className="feather-progress-bar" style={ { width: `${ progressPct }%` } } />
				</div>
			</div>
		);
	}
	if ( status.state === 'complete' ) {
		return (
			<p>
				{ sprintf(
					/* translators: %d pages */
					__( 'Last scan: %d pages.', 'feather-performance' ),
					status.processed
				) }
			</p>
		);
	}
	if ( status.state === 'idle' ) {
		return <p>{ __( 'No scan has run yet.', 'feather-performance' ) }</p>;
	}
	if ( status.state === 'canceled' ) {
		return <p>{ __( 'Scan was canceled.', 'feather-performance' ) }</p>;
	}
	if ( status.state === 'failed' ) {
		return <p>{ status.error || __( 'Scan failed.', 'feather-performance' ) }</p>;
	}
	return <p>{ status.state }</p>;
}

function AggregateSummary( { aggregate }: { aggregate: ScanAggregate } ): JSX.Element {
	const widgetTotal = Object.keys( aggregate.widget_counts ).length;
	const assetTotal = Object.keys( aggregate.asset_counts ).length;
	// Highlight rule: a count of 0 is "success" (nothing to trim/optimize away)
	// because each tile flags a specific asset family Feather can remove.
	return (
		<div className="feather-stat-row">
			<StatTile label={ __( 'Pages scanned', 'feather-performance' ) } value={ aggregate.total_pages } />
			<StatTile label={ __( 'Widget types', 'feather-performance' ) } value={ widgetTotal } />
			<StatTile label={ __( 'Assets tracked', 'feather-performance' ) } value={ assetTotal } />
			<StatTile
				label={ __( 'eicons usages', 'feather-performance' ) }
				value={ aggregate.eicons_usage_count }
				highlight={ aggregate.eicons_usage_count === 0 ? 'success' : 'warning' }
			/>
			<StatTile
				label={ __( 'FA icon usages', 'feather-performance' ) }
				value={ aggregate.fa_icons_usage_count }
				highlight={ aggregate.fa_icons_usage_count === 0 ? 'success' : 'warning' }
			/>
			<StatTile
				label={ __( 'Google Fonts pages', 'feather-performance' ) }
				value={ aggregate.google_fonts_usage_count }
				highlight={ aggregate.google_fonts_usage_count === 0 ? 'success' : 'warning' }
			/>
			<StatTile
				label={ __( 'Lottie usages', 'feather-performance' ) }
				value={ aggregate.lottie_usage_count }
				highlight={ aggregate.lottie_usage_count === 0 ? 'success' : 'warning' }
			/>
		</div>
	);
}

function StatTile( {
	label,
	value,
	highlight,
}: {
	label: string;
	value: number;
	highlight?: 'success' | 'warning';
} ): JSX.Element {
	return (
		<div className={ `feather-stat-tile${ highlight ? ` is-${ highlight }` : '' }` }>
			<div className="feather-stat-value">{ value }</div>
			<div className="feather-stat-label">{ label }</div>
		</div>
	);
}

function ResultsTabs( {
	aggregate,
	results,
}: {
	aggregate: ScanAggregate;
	results: ScanResultsPage | null;
} ): JSX.Element {
	return (
		<Card className="feather-scan-results">
			<CardBody>
				<TabPanel
					tabs={ [
						{ name: 'widgets', title: __( 'Widgets', 'feather-performance' ), className: '' },
						{ name: 'assets', title: __( 'Assets', 'feather-performance' ), className: '' },
						{ name: 'pages', title: __( 'Pages', 'feather-performance' ), className: '' },
					] }
				>
					{ ( tab ) => {
						if ( tab.name === 'widgets' ) {
							return <CountTable counts={ aggregate.widget_counts } />;
						}
						if ( tab.name === 'assets' ) {
							return <CountTable counts={ aggregate.asset_counts } />;
						}
						return <PagesTable rows={ results?.rows ?? [] } />;
					} }
				</TabPanel>
			</CardBody>
		</Card>
	);
}

function CountTable( { counts }: { counts: Record< string, number > } ): JSX.Element {
	const sorted = Object.entries( counts ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] );
	if ( sorted.length === 0 ) {
		return <p>{ __( 'No entries.', 'feather-performance' ) }</p>;
	}
	return (
		<table className="feather-table">
			<thead>
				<tr>
					<th>{ __( 'Name', 'feather-performance' ) }</th>
					<th style={ { textAlign: 'right' } }>{ __( 'Pages', 'feather-performance' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ sorted.map( ( [ name, count ] ) => (
					<tr key={ name }>
						<td><code>{ name }</code></td>
						<td style={ { textAlign: 'right' } }>{ count }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

function PagesTable( { rows }: { rows: import( '../types' ).ScanResultRow[] } ): JSX.Element {
	if ( rows.length === 0 ) {
		return <p>{ __( 'No pages scanned yet.', 'feather-performance' ) }</p>;
	}
	return (
		<table className="feather-table">
			<thead>
				<tr>
					<th>{ __( 'Post', 'feather-performance' ) }</th>
					<th>{ __( 'Widgets', 'feather-performance' ) }</th>
					<th>{ __( 'Assets', 'feather-performance' ) }</th>
					<th>{ __( 'Scanned', 'feather-performance' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( row ) => (
					<tr key={ row.post_id }>
						<td>#{ row.post_id }</td>
						<td>{ row.widget_types.length }</td>
						<td>{ row.asset_handles.length }</td>
						<td>{ row.scanned_at }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
