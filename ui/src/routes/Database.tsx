import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { fetchDbHealth, runDbCleanup } from '../api/client';
import type { DbHealth, DbToolName } from '../types';

interface ToolDef {
	id: DbToolName;
	label: string;
	describe: ( h: DbHealth ) => string;
	count: ( h: DbHealth ) => number;
}

const TOOLS: ToolDef[] = [
	{
		id: 'transients',
		label: __( 'Expired transients', 'feather-performance' ),
		describe: () =>
			__(
				'Caches that have already expired but were never cleaned up. Safe to remove.',
				'feather-performance'
			),
		count: ( h ) => h.expired_transients,
	},
	{
		id: 'elementor_revisions',
		label: __( 'Orphaned Elementor revisions', 'feather-performance' ),
		describe: () =>
			__(
				'Revision posts carrying _elementor_data that bloat the posts table. Removing these does not affect published content.',
				'feather-performance'
			),
		count: ( h ) => h.elementor_orphan_revisions,
	},
	{
		id: 'oembed_cache',
		label: __( 'oEmbed cache', 'feather-performance' ),
		describe: () =>
			__(
				'Stored copies of remote embed lookups (YouTube, Twitter, etc.). They will rebuild on next view.',
				'feather-performance'
			),
		count: ( h ) => h.oembed_cached_entries,
	},
];

export default function Database(): JSX.Element {
	const [ health, setHealth ] = useState< DbHealth | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ pending, setPending ] = useState< DbToolName | null >( null );
	const [ flash, setFlash ] = useState< string | null >( null );

	useEffect( () => {
		fetchDbHealth()
			.then( setHealth )
			.catch( ( err: Error ) => setError( err.message ) );
	}, [] );

	async function handleCleanup( tool: DbToolName ): Promise< void > {
		setPending( tool );
		setError( null );
		setFlash( null );
		try {
			const result = await runDbCleanup( tool );
			setHealth( result.health );
			setFlash(
				sprintf(
					/* translators: %d: number of rows removed */
					__( 'Cleaned up %d entries.', 'feather-performance' ),
					result.deleted
				)
			);
		} catch ( err ) {
			setError(
				err instanceof Error ? err.message : __( 'Cleanup failed.', 'feather-performance' )
			);
		} finally {
			setPending( null );
		}
	}

	if ( error && ! health ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! health ) {
		return (
			<div className="feather-loading">
				<Spinner />
			</div>
		);
	}

	const scoreLabel =
		health.score >= 80
			? __( 'Healthy', 'feather-performance' )
			: health.score >= 50
			? __( 'Could use cleanup', 'feather-performance' )
			: __( 'Needs attention', 'feather-performance' );

	const scoreClass =
		health.score >= 80 ? 'is-success' : health.score >= 50 ? 'is-warning' : 'is-danger';

	return (
		<div className="feather-database">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }
			{ flash && (
				<Notice status="success" isDismissible onRemove={ () => setFlash( null ) }>
					{ flash }
				</Notice>
			) }

			<Card className="feather-db-health">
				<CardHeader>
					<h2>{ __( 'Database health', 'feather-performance' ) }</h2>
					<span className={ `feather-health-pill ${ scoreClass }` }>
						{ scoreLabel }
					</span>
				</CardHeader>
				<CardBody>
					<div className="feather-health-grid">
						<HealthScore score={ health.score } />
						<div className="feather-health-stats">
							<HealthStat
								label={ __( 'Autoloaded options', 'feather-performance' ) }
								value={ formatBytes( health.autoload_bytes ) }
								hint={ __(
									'Loaded on every WP request. Aim for under 800KB.',
									'feather-performance'
								) }
							/>
							<HealthStat
								label={ __( 'Expired transients', 'feather-performance' ) }
								value={ String( health.expired_transients ) }
								hint={ __( 'Stale cache rows still on disk.', 'feather-performance' ) }
							/>
							<HealthStat
								label={ __( 'Elementor revisions', 'feather-performance' ) }
								value={ String( health.elementor_orphan_revisions ) }
								hint={ __( 'Old revision rows we can remove.', 'feather-performance' ) }
							/>
							<HealthStat
								label={ __( 'oEmbed cache', 'feather-performance' ) }
								value={ String( health.oembed_cached_entries ) }
								hint={ __( 'Cached lookups for embedded content.', 'feather-performance' ) }
							/>
						</div>
					</div>
				</CardBody>
			</Card>

			<div className="feather-db-tools">
				{ TOOLS.map( ( tool ) => {
					const count = tool.count( health );
					const isPending = pending === tool.id;
					const isEmpty = count === 0;
					return (
						<Card key={ tool.id } className="feather-db-tool">
							<CardHeader>
								<strong>{ tool.label }</strong>
								<span className="feather-db-tool-count">{ count }</span>
							</CardHeader>
							<CardBody>
								<p className="feather-feature-desc">{ tool.describe( health ) }</p>
								<Button
									variant={ isEmpty ? 'secondary' : 'primary' }
									isBusy={ isPending }
									disabled={ isPending || isEmpty }
									onClick={ () => handleCleanup( tool.id ) }
								>
									{ isEmpty
										? __( 'Nothing to clean', 'feather-performance' )
										: __( 'Clean up', 'feather-performance' ) }
								</Button>
							</CardBody>
						</Card>
					);
				} ) }
			</div>

			{ health.autoload_largest.length > 0 && (
				<Card>
					<CardHeader>
						<h2>{ __( 'Largest autoloaded options', 'feather-performance' ) }</h2>
					</CardHeader>
					<CardBody>
						<table className="feather-table">
							<thead>
								<tr>
									<th>{ __( 'Option', 'feather-performance' ) }</th>
									<th style={ { textAlign: 'right' } }>
										{ __( 'Size', 'feather-performance' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ health.autoload_largest.map( ( entry ) => (
									<tr key={ entry.option_name }>
										<td>
											<code>{ entry.option_name }</code>
										</td>
										<td style={ { textAlign: 'right' } }>
											{ formatBytes( entry.bytes ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</CardBody>
				</Card>
			) }
		</div>
	);
}

function HealthScore( { score }: { score: number } ): JSX.Element {
	const pct = Math.max( 0, Math.min( 100, score ) );
	return (
		<div className="feather-health-score">
			<svg viewBox="0 0 120 120" width={ 120 } height={ 120 }>
				<circle
					cx={ 60 }
					cy={ 60 }
					r={ 52 }
					fill="none"
					stroke="var(--feather-border)"
					strokeWidth={ 8 }
				/>
				<circle
					cx={ 60 }
					cy={ 60 }
					r={ 52 }
					fill="none"
					stroke={
						pct >= 80
							? 'var(--feather-success)'
							: pct >= 50
							? 'var(--feather-warning)'
							: 'var(--feather-danger)'
					}
					strokeWidth={ 8 }
					strokeLinecap="round"
					strokeDasharray={ `${ ( pct / 100 ) * 327 } 327` }
					transform="rotate(-90 60 60)"
				/>
			</svg>
			<div className="feather-health-score-value">{ pct }</div>
		</div>
	);
}

function HealthStat( {
	label,
	value,
	hint,
}: {
	label: string;
	value: string;
	hint: string;
} ): JSX.Element {
	return (
		<div className="feather-health-stat">
			<div className="feather-health-stat-label">{ label }</div>
			<div className="feather-health-stat-value">{ value }</div>
			<div className="feather-health-stat-hint">{ hint }</div>
		</div>
	);
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
