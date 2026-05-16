import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import {
	captureMetrics,
	fetchFeatures,
	fetchLatestMetrics,
	fetchMetricsHistory,
	fetchOnboardingState,
	setOnboardingState,
	toggleFeature,
} from '../api/client';
import PauseAllCard from '../components/PauseAllCard';
import type {
	Feature,
	MetricsHistory,
	OnboardingStatus,
	PageWeightSnapshot,
} from '../types';

export default function Dashboard(): JSX.Element {
	const [ features, setFeatures ] = useState< Feature[] | null >( null );
	const [ latest, setLatest ] = useState< PageWeightSnapshot | null >( null );
	const [ history, setHistory ] = useState< MetricsHistory | null >( null );
	const [ onboarding, setOnboarding ] = useState< OnboardingStatus | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ measuring, setMeasuring ] = useState( false );
	const [ flashId, setFlashId ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;
		Promise.all( [
			fetchFeatures(),
			fetchLatestMetrics().catch( () => null ),
			fetchMetricsHistory( 30 ).catch( () => ( { rows: [], count: 0 } ) ),
			fetchOnboardingState().catch( () => null ),
		] )
			.then( ( [ feat, lm, hist, onb ] ) => {
				if ( cancelled ) {
					return;
				}
				setFeatures( feat.features );
				setLatest( lm );
				setHistory( hist );
				setOnboarding( onb );
			} )
			.catch( ( err: Error ) => {
				if ( ! cancelled ) {
					setError( err.message );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	async function handleMeasure(): Promise< void > {
		setMeasuring( true );
		setError( null );
		try {
			await captureMetrics();
			// Re-read from the server instead of trusting the capture response.
			// Some object caches return stale or partial reads immediately after
			// a write, so the canonical /latest endpoint is the source of truth.
			const [ fresh, hist ] = await Promise.all( [
				fetchLatestMetrics().catch( () => null ),
				fetchMetricsHistory( 30 ).catch( () => ( { rows: [], count: 0 } ) ),
			] );
			setLatest( fresh );
			setHistory( hist );
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Measurement failed.', 'feather-performance' )
			);
		} finally {
			setMeasuring( false );
		}
	}

	async function dismissBanner(): Promise< void > {
		try {
			const next = await setOnboardingState( 'completed' );
			setOnboarding( next );
		} catch ( _ ) {
			// Non-fatal — banner stays.
		}
	}

	async function handleQuickWin( id: string ): Promise< void > {
		setFlashId( id );
		try {
			const updated = await toggleFeature( id, true );
			setFeatures( ( prev ) =>
				prev
					? prev.map( ( f ) => ( f.id === id ? { ...f, ...updated } : f ) )
					: prev
			);
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Could not enable feature.', 'feather-performance' )
			);
		} finally {
			setFlashId( null );
		}
	}

	const stats = useMemo( () => computeStats( features ?? [] ), [ features ] );
	const quickWins = useMemo( () => pickQuickWins( features ?? [] ), [ features ] );
	const showBanner = onboarding?.show_banner === true;

	if ( ! features ) {
		return (
			<div className="feather-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="feather-dashboard">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<PauseAllCard />

			{ showBanner && (
				<WelcomeBanner
					stats={ stats }
					onDismiss={ dismissBanner }
				/>
			) }

			<div className="feather-hero-row">
				<HeroTile
					label={ __( 'Optimizations active', 'feather-performance' ) }
					value={ String( stats.activeCount ) }
					trail={ sprintf(
						/* translators: %d total feature count */
						__( 'of %d available', 'feather-performance' ),
						stats.totalImplementedCount
					) }
				/>
				<HeroTile
					label={ __( 'Homepage weight', 'feather-performance' ) }
					value={ latest ? formatBytes( latest.html_bytes ) : '—' }
					trail={
						latest
							? sprintf(
									/* translators: %d asset count */
									__( '%d assets', 'feather-performance' ),
									latest.total_assets
							  )
							: __( 'Run a measurement', 'feather-performance' )
					}
				/>
				<HeroTile
					label={ __( 'Last measured', 'feather-performance' ) }
					value={ latest ? formatRelative( latest.recorded_at ?? '' ) : __( 'Never', 'feather-performance' ) }
					trail={
						<Button
							variant="primary"
							isBusy={ measuring }
							disabled={ measuring }
							onClick={ handleMeasure }
						>
							{ measuring
								? __( 'Measuring…', 'feather-performance' )
								: __( 'Take measurement', 'feather-performance' ) }
						</Button>
					}
				/>
			</div>

			{ history && history.rows.length > 1 && (
				<Card>
					<CardHeader>
						<h2>{ __( 'Homepage weight over time', 'feather-performance' ) }</h2>
						<span className="feather-text-muted-mono">
							{ sprintf(
								/* translators: %d snapshot count */
								__( 'last %d snapshots', 'feather-performance' ),
								history.rows.length
							) }
						</span>
					</CardHeader>
					<CardBody>
						<Sparkline rows={ history.rows } />
					</CardBody>
				</Card>
			) }

			{ quickWins.length > 0 && (
				<Card>
					<CardHeader>
						<h2>{ __( 'Not running', 'feather-performance' ) }</h2>
						<span className="feather-text-muted-mono">
							{ __( 'high-impact', 'feather-performance' ) }
						</span>
					</CardHeader>
					<CardBody>
						<ul className="feather-quickwin-list">
							{ quickWins.map( ( feat ) => (
								<li key={ feat.id } className="feather-quickwin">
									<div className="feather-quickwin-text">
										<strong>{ feat.label }</strong>
										<small>{ feat.description }</small>
									</div>
									<Button
										variant="primary"
										isBusy={ flashId === feat.id }
										disabled={ flashId === feat.id }
										onClick={ () => handleQuickWin( feat.id ) }
									>
										{ __( 'Enable', 'feather-performance' ) }
									</Button>
								</li>
							) ) }
						</ul>
					</CardBody>
				</Card>
			) }
		</div>
	);
}

function WelcomeBanner( {
	stats,
	onDismiss,
}: {
	stats: ReturnType< typeof computeStats >;
	onDismiss: () => void;
} ): JSX.Element {
	const brandLogo = window.feather?.boot?.logos?.brand ?? '';
	return (
		<Card className="feather-welcome">
			<CardBody>
				<div className="feather-welcome-grid">
					{ brandLogo && (
						<img
							className="feather-welcome-mark"
							src={ brandLogo }
							alt=""
							width={ 96 }
							height={ 96 }
						/>
					) }
					<div className="feather-welcome-text">
						<h2>{ __( 'Welcome to Feather', 'feather-performance' ) }</h2>
						<p>
							{ sprintf(
								/* translators: %d count of optimizations */
								__(
									'%d optimizations are already running on safe defaults. Run a site scan to unlock the rest — Feather will only enable changes the scan confirms are safe for your site.',
									'feather-performance'
								),
								stats.activeCount
							) }
						</p>
					</div>
					<div className="feather-welcome-actions">
						<Button
							variant="primary"
							href={ `${ window.feather?.boot?.adminUrl ?? '' }admin.php?page=feather-scan` }
						>
							{ __( 'Run scan now', 'feather-performance' ) }
						</Button>
						<Button variant="tertiary" onClick={ onDismiss }>
							{ __( 'Dismiss', 'feather-performance' ) }
						</Button>
					</div>
				</div>
			</CardBody>
		</Card>
	);
}

function HeroTile( {
	label,
	value,
	trail,
}: {
	label: string;
	value: string;
	trail: React.ReactNode;
} ): JSX.Element {
	return (
		<div className="feather-hero-tile">
			<div className="feather-hero-label">{ label }</div>
			<div className="feather-hero-value">{ value }</div>
			<div className="feather-hero-trail">{ trail }</div>
		</div>
	);
}

function Sparkline( { rows }: { rows: PageWeightSnapshot[] } ): JSX.Element {
	const points = rows.map( ( r ) => r.html_bytes );
	const min = Math.min( ...points );
	const max = Math.max( ...points );
	const range = Math.max( 1, max - min );
	const width = 800;
	const height = 80;
	const stepX = points.length > 1 ? width / ( points.length - 1 ) : 0;

	const path = points
		.map( ( v, i ) => {
			const x = i * stepX;
			const y = height - ( ( v - min ) / range ) * height;
			return `${ i === 0 ? 'M' : 'L' }${ x.toFixed( 1 ) },${ y.toFixed( 1 ) }`;
		} )
		.join( ' ' );

	return (
		<div className="feather-sparkline-wrap">
			<svg
				viewBox={ `0 0 ${ width } ${ height }` }
				width="100%"
				height={ height }
				preserveAspectRatio="none"
				role="img"
				aria-label={ __( 'Homepage weight history', 'feather-performance' ) }
			>
				<path
					d={ path }
					fill="none"
					stroke="var(--feather-accent)"
					strokeWidth={ 2 }
					strokeLinejoin="round"
					strokeLinecap="round"
				/>
			</svg>
			<div className="feather-sparkline-axis">
				<span>{ formatBytes( min ) }</span>
				<span>{ formatBytes( max ) }</span>
			</div>
		</div>
	);
}

function computeStats( features: Feature[] ): {
	activeCount: number;
	totalImplementedCount: number;
} {
	let active = 0;
	const total = features.length;
	for ( const f of features ) {
		if ( f.enabled ) {
			active += 1;
		}
	}
	return { activeCount: active, totalImplementedCount: total };
}

function pickQuickWins( features: Feature[] ): Feature[] {
	return features
		.filter(
			( f ) =>
				! f.enabled &&
				( f.impact === 'high' || f.impact === 'medium' ) &&
				f.recommendation !== 'dangerous' &&
				f.recommendation !== 'risky'
		)
		.sort( ( a, b ) => impactWeight( b.impact ) - impactWeight( a.impact ) )
		.slice( 0, 3 );
}

function impactWeight( impact: Feature[ 'impact' ] ): number {
	if ( impact === 'high' ) {
		return 3;
	}
	if ( impact === 'medium' ) {
		return 2;
	}
	return 1;
}

function formatBytes( bytes: number ): string {
	if ( ! Number.isFinite( bytes ) || bytes <= 0 ) {
		return '0 B';
	}
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let i = 0;
	let v = bytes;
	while ( v >= 1024 && i < units.length - 1 ) {
		v /= 1024;
		i += 1;
	}
	return `${ v.toFixed( v < 10 && i > 0 ? 1 : 0 ) } ${ units[ i ] }`;
}

function formatRelative( gmtDate: string ): string {
	if ( ! gmtDate ) {
		return __( 'Never', 'feather-performance' );
	}
	const then = Date.parse( gmtDate.replace( ' ', 'T' ) + 'Z' );
	if ( Number.isNaN( then ) ) {
		return gmtDate;
	}
	const seconds = Math.max( 0, Math.floor( ( Date.now() - then ) / 1000 ) );
	if ( seconds < 60 ) {
		return __( 'just now', 'feather-performance' );
	}
	if ( seconds < 3600 ) {
		const m = Math.floor( seconds / 60 );
		return sprintf(
			/* translators: %d minutes ago */
			__( '%dm ago', 'feather-performance' ),
			m
		);
	}
	if ( seconds < 86400 ) {
		const h = Math.floor( seconds / 3600 );
		return sprintf(
			/* translators: %d hours ago */
			__( '%dh ago', 'feather-performance' ),
			h
		);
	}
	const d = Math.floor( seconds / 86400 );
	return sprintf(
		/* translators: %d days ago */
		__( '%dd ago', 'feather-performance' ),
		d
	);
}
