import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import {
	clearMetricsData,
	clearScanData,
	exportSettings,
	fetchSettings,
	fetchSystemInfo,
	importSettings,
	resetSettings,
	updateSettings,
} from '../api/client';
import type {
	DetectedPlugin,
	SettingsExport,
	SettingsResponse,
	SystemInfo,
} from '../types';

export default function SettingsPanel(): JSX.Element {
	const [ settings, setSettings ] = useState< SettingsResponse | null >(
		null
	);
	const [ system, setSystem ] = useState< SystemInfo | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ flash, setFlash ] = useState< string | null >( null );
	const [ pending, setPending ] = useState< string | null >( null );

	useEffect( () => {
		Promise.all( [ fetchSettings(), fetchSystemInfo() ] )
			.then( ( [ s, sys ] ) => {
				setSettings( s );
				setSystem( sys );
			} )
			.catch( ( err: Error ) => setError( err.message ) );
	}, [] );

	async function patchUsageOptIn( next: boolean ): Promise< void > {
		try {
			const updated = await updateSettings( { usage_opt_in: next } );
			setSettings( updated );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __( 'Save failed.', 'feather-performance' )
			);
		}
	}

	async function runDanger(
		key: string,
		confirmMessage: string,
		successMessage: string,
		fn: () => Promise< unknown >
	): Promise< void > {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}
		setPending( key );
		setError( null );
		setFlash( null );
		try {
			await fn();
			setFlash( successMessage );
			if ( key === 'reset' ) {
				const fresh = await fetchSettings();
				setSettings( fresh );
			}
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __( 'Action failed.', 'feather-performance' )
			);
		} finally {
			setPending( null );
		}
	}

	if ( error && ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! settings || ! system ) {
		return (
			<div className="feather-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="feather-settings">
			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					{ error }
				</Notice>
			) }
			{ flash && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setFlash( null ) }
				>
					{ flash }
				</Notice>
			) }

			<PluginInfoCard system={ system } />

			<CompatibilityCard system={ system } />

			<Card>
				<CardHeader>
					<h2>{ __( 'Privacy', 'feather-performance' ) }</h2>
				</CardHeader>
				<CardBody>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Send anonymous usage data',
							'feather-performance'
						) }
						help={ __(
							'Helps us prioritize features. Off by default. No URLs, page content, or visitor data are sent.',
							'feather-performance'
						) }
						checked={ !! settings.usage_opt_in }
						onChange={ patchUsageOptIn }
					/>
				</CardBody>
			</Card>

			<PortabilityCard
				onError={ ( msg ) => setError( msg ) }
				onFlash={ ( msg ) => setFlash( msg ) }
				onReload={ async () => {
					const fresh = await fetchSettings();
					setSettings( fresh );
				} }
			/>

			<Card>
				<CardHeader>
					<h2>{ __( 'Data management', 'feather-performance' ) }</h2>
				</CardHeader>
				<CardBody>
					<DangerRow
						label={ __(
							'Reset settings to defaults',
							'feather-performance'
						) }
						hint={ __(
							"Restores Feather's default feature toggles. Your scan history and metrics history are kept.",
							'feather-performance'
						) }
						buttonLabel={ __(
							'Reset settings',
							'feather-performance'
						) }
						isPending={ pending === 'reset' }
						onClick={ () =>
							runDanger(
								'reset',
								__(
									'Reset all Feather settings to defaults? Toggle states will go back to recommended defaults; scan history and metrics are kept.',
									'feather-performance'
								),
								__(
									'Settings reset to defaults.',
									'feather-performance'
								),
								resetSettings
							)
						}
					/>
					<DangerRow
						label={ __(
							'Clear scan history',
							'feather-performance'
						) }
						hint={ __(
							'Empties the site-scan results table. The next scan you run will rebuild it.',
							'feather-performance'
						) }
						buttonLabel={ __(
							'Clear scan history',
							'feather-performance'
						) }
						isPending={ pending === 'scan' }
						onClick={ () =>
							runDanger(
								'scan',
								__(
									'Clear all scan results? This stops any running scan and empties the scan results table.',
									'feather-performance'
								),
								__(
									'Scan history cleared.',
									'feather-performance'
								),
								clearScanData
							)
						}
					/>
					<DangerRow
						label={ __(
							'Clear metrics history',
							'feather-performance'
						) }
						hint={ __(
							'Empties the page-weight measurement history. The Dashboard sparkline will be empty until you take new measurements.',
							'feather-performance'
						) }
						buttonLabel={ __(
							'Clear metrics',
							'feather-performance'
						) }
						isPending={ pending === 'metrics' }
						onClick={ () =>
							runDanger(
								'metrics',
								__(
									'Clear all stored page-weight metrics?',
									'feather-performance'
								),
								__(
									'Metrics history cleared.',
									'feather-performance'
								),
								clearMetricsData
							)
						}
					/>
				</CardBody>
			</Card>
		</div>
	);
}

function PluginInfoCard( { system }: { system: SystemInfo } ): JSX.Element {
	const installed = formatDate( system.plugin.install_date );
	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Plugin info', 'feather-performance' ) }</h2>
			</CardHeader>
			<CardBody>
				<dl className="feather-info-list">
					<InfoRow
						label={ __( 'Feather version', 'feather-performance' ) }
						value={ system.plugin.version }
					/>
					<InfoRow
						label={ __( 'Installed', 'feather-performance' ) }
						value={ installed }
					/>
					<InfoRow
						label={ __( 'WordPress', 'feather-performance' ) }
						value={ system.env.wp_version }
					/>
					<InfoRow
						label={ __( 'PHP', 'feather-performance' ) }
						value={ system.env.php_version }
					/>
					<InfoRow
						label={ __( 'Locale', 'feather-performance' ) }
						value={ system.env.locale }
					/>
					{ system.env.multisite && (
						<InfoRow
							label={ __( 'Multisite', 'feather-performance' ) }
							value={ __(
								'Yes (compatible mode)',
								'feather-performance'
							) }
						/>
					) }
				</dl>
			</CardBody>
		</Card>
	);
}

function CompatibilityCard( { system }: { system: SystemInfo } ): JSX.Element {
	const allEmpty =
		system.detected.cache.length === 0 &&
		system.detected.image_optimizer.length === 0 &&
		system.detected.builders.length === 0;

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Detected plugins', 'feather-performance' ) }</h2>
				<span className="feather-text-muted-mono">
					{ __(
						'plugins Feather coexists with',
						'feather-performance'
					) }
				</span>
			</CardHeader>
			<CardBody>
				{ allEmpty && (
					<p className="feather-feature-desc">
						{ __(
							'No cache, image optimizer, or page builder plugins detected besides Elementor itself. Feather will run all of its optimizations without coexistence concerns.',
							'feather-performance'
						) }
					</p>
				) }
				{ system.detected.cache.length > 0 && (
					<DetectedGroup
						title={ __( 'Cache plugins', 'feather-performance' ) }
						hint={ __(
							'Feather will not duplicate optimizations these plugins already provide (defer JS, minify, etc.).',
							'feather-performance'
						) }
						items={ system.detected.cache }
					/>
				) }
				{ system.detected.image_optimizer.length > 0 && (
					<DetectedGroup
						title={ __(
							'Image optimizers',
							'feather-performance'
						) }
						hint={ __(
							'Feather will defer to these for image lazy loading and dimension auto-fix.',
							'feather-performance'
						) }
						items={ system.detected.image_optimizer }
					/>
				) }
				{ system.detected.builders.length > 0 && (
					<DetectedGroup
						title={ __( 'Page builders', 'feather-performance' ) }
						hint={ __(
							'Feather is Elementor-aware. Other builders are listed for context only.',
							'feather-performance'
						) }
						items={ system.detected.builders }
					/>
				) }
			</CardBody>
		</Card>
	);
}

function DetectedGroup( {
	title,
	hint,
	items,
}: {
	title: string;
	hint: string;
	items: DetectedPlugin[];
} ): JSX.Element {
	return (
		<div className="feather-detected-group">
			<h3>{ title }</h3>
			<p className="feather-feature-desc">{ hint }</p>
			<ul className="feather-detected-list">
				{ items.map( ( item ) => (
					<li key={ item.slug }>
						<strong>{ item.name }</strong>
						<code>{ item.slug }</code>
					</li>
				) ) }
			</ul>
		</div>
	);
}

function InfoRow( {
	label,
	value,
}: {
	label: string;
	value: string;
} ): JSX.Element {
	return (
		<div className="feather-info-row">
			<dt>{ label }</dt>
			<dd>{ value }</dd>
		</div>
	);
}

function DangerRow( {
	label,
	hint,
	buttonLabel,
	isPending,
	onClick,
}: {
	label: string;
	hint: string;
	buttonLabel: string;
	isPending: boolean;
	onClick: () => void;
} ): JSX.Element {
	return (
		<div className="feather-danger-row">
			<div className="feather-danger-text">
				<strong>{ label }</strong>
				<small>{ hint }</small>
			</div>
			<Button
				variant="secondary"
				isDestructive
				isBusy={ isPending }
				disabled={ isPending }
				onClick={ onClick }
			>
				{ buttonLabel }
			</Button>
		</div>
	);
}

function PortabilityCard( {
	onError,
	onFlash,
	onReload,
}: {
	onError: ( message: string ) => void;
	onFlash: ( message: string ) => void;
	onReload: () => Promise< void >;
} ): JSX.Element {
	const [ pending, setPending ] = useState< 'export' | 'import' | null >(
		null
	);

	async function handleExport(): Promise< void > {
		setPending( 'export' );
		try {
			const payload = await exportSettings();
			const json = JSON.stringify( payload, null, 2 );
			const blob = new Blob( [ json ], { type: 'application/json' } );
			const url = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = `feather-settings-${ payload.generated_at
				.replace( /[^0-9]/g, '' )
				.slice( 0, 14 ) }.json`;
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
			URL.revokeObjectURL( url );
			onFlash( __( 'Settings exported.', 'feather-performance' ) );
		} catch ( err ) {
			onError(
				err instanceof Error
					? err.message
					: __( 'Export failed.', 'feather-performance' )
			);
		} finally {
			setPending( null );
		}
	}

	function handleImportFile( evt: { target: HTMLInputElement } ): void {
		const file = evt.target.files?.[ 0 ];
		evt.target.value = '';
		if ( ! file ) {
			return;
		}
		setPending( 'import' );
		const reader = new FileReader();
		reader.onload = async () => {
			try {
				const text = String( reader.result ?? '' );
				const payload = JSON.parse( text ) as SettingsExport;
				const summary = await importSettings( payload );
				if ( summary.applied ) {
					await onReload();
					const skipped = summary.unknown_features.length;
					onFlash(
						skipped > 0
							? sprintf(
									/* translators: 1: feature count, 2: unknown count */
									__(
										'Imported %1$d feature toggles. Skipped %2$d unknown features.',
										'feather-performance'
									),
									summary.feature_count,
									skipped
							  )
							: sprintf(
									/* translators: %d feature count */
									__(
										'Imported %d feature toggles.',
										'feather-performance'
									),
									summary.feature_count
							  )
					);
				} else {
					onError(
						__( 'Import was rejected.', 'feather-performance' )
					);
				}
			} catch ( err ) {
				onError(
					err instanceof Error
						? err.message
						: __( 'Import failed.', 'feather-performance' )
				);
			} finally {
				setPending( null );
			}
		};
		reader.onerror = () => {
			onError( __( 'Could not read the file.', 'feather-performance' ) );
			setPending( null );
		};
		reader.readAsText( file );
	}

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Portability', 'feather-performance' ) }</h2>
				<span className="feather-text-muted-mono">
					{ __(
						'move settings across sites',
						'feather-performance'
					) }
				</span>
			</CardHeader>
			<CardBody>
				<p className="feather-feature-desc">
					{ __(
						'Export your Feather configuration as a JSON file. Import the same file on another site to copy your feature toggles, preset, and advanced settings. Scan results and metrics history are not included.',
						'feather-performance'
					) }
				</p>
				<div className="feather-danger-row">
					<div className="feather-danger-text">
						<strong>
							{ __( 'Export settings', 'feather-performance' ) }
						</strong>
						<small>
							{ __(
								'Downloads a JSON snapshot you can save or import elsewhere.',
								'feather-performance'
							) }
						</small>
					</div>
					<Button
						variant="secondary"
						isBusy={ pending === 'export' }
						disabled={ pending !== null }
						onClick={ handleExport }
					>
						{ __( 'Export', 'feather-performance' ) }
					</Button>
				</div>
				<div className="feather-danger-row">
					<div className="feather-danger-text">
						<strong>
							{ __( 'Import settings', 'feather-performance' ) }
						</strong>
						<small>
							{ __(
								'Replaces feature toggles, preset, and advanced settings with the contents of an export file.',
								'feather-performance'
							) }
						</small>
					</div>
					<label
						htmlFor="feather-import-input"
						className="components-button is-secondary"
						style={ { cursor: 'pointer' } }
					>
						{ pending === 'import'
							? __( 'Importing…', 'feather-performance' )
							: __( 'Import file', 'feather-performance' ) }
					</label>
					<input
						id="feather-import-input"
						type="file"
						accept="application/json,.json"
						style={ { display: 'none' } }
						onChange={ handleImportFile }
						disabled={ pending !== null }
					/>
				</div>
			</CardBody>
		</Card>
	);
}

function formatDate( iso: string ): string {
	if ( ! iso ) {
		return __( 'Unknown', 'feather-performance' );
	}
	const ts = Date.parse( iso );
	if ( Number.isNaN( ts ) ) {
		return iso;
	}
	const d = new Date( ts );
	const formatter = new Intl.DateTimeFormat( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
	const days = Math.floor( ( Date.now() - ts ) / 86400000 );
	const ago =
		days < 1
			? ''
			: ' ' +
			  sprintf(
					/* translators: %d days */
					__( '(%d days ago)', 'feather-performance' ),
					days
			  );
	return formatter.format( d ) + ago;
}
