import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { bulkUpdateFeatures, fetchFeatures } from '../api/client';

/**
 * Dashboard composite toggle: a single switch that disables Elementor's
 * outbound telemetry surface — phone-home requests, AI editor bundle, and
 * the "tracking active" flags. Wraps three existing feature ids:
 *
 *   f.elementor.telemetry      — tracker/beta flags
 *   f.elementor.api_fetcher    — my.elementor.com banners + what's-new + upsells
 *   f.elementor.ai_module      — AI editor bundle + is_ai_enabled short-circuit
 *
 * Toggling here bulk-flips all three feature toggles together. Individual
 * toggles remain available on the Features page for users who want finer
 * control. We display the ON state as "all three enabled" so the dashboard
 * reflects a consistent picture; a mixed state collapses to "off" until the
 * user makes a deliberate choice.
 */

const BUNDLE: ReadonlyArray< string > = [
	'f.elementor.telemetry',
	'f.elementor.api_fetcher',
	'f.elementor.ai_module',
];

export default function ElementorTelemetryCard(): JSX.Element {
	const [ enabled, setEnabled ] = useState< boolean | null >( null );
	const [ pending, setPending ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;
		fetchFeatures()
			.then( ( res ) => {
				if ( cancelled ) {
					return;
				}
				const map: Record< string, boolean > = {};
				for ( const feat of res.features ) {
					map[ feat.id ] = !! feat.enabled;
				}
				const allOn = BUNDLE.every( ( id ) => !! map[ id ] );
				setEnabled( allOn );
			} )
			.catch( ( err: Error ) => {
				if ( ! cancelled ) {
					setError( err.message );
					setEnabled( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	async function toggle( next: boolean ): Promise< void > {
		const previous = enabled;
		setEnabled( next );
		setPending( true );
		setError( null );
		try {
			const updates: Record< string, boolean > = {};
			for ( const id of BUNDLE ) {
				updates[ id ] = next;
			}
			await bulkUpdateFeatures( updates );
		} catch ( err ) {
			setEnabled( previous );
			setError(
				err instanceof Error
					? err.message
					: __(
							'Could not update Elementor telemetry settings.',
							'feather-performance'
					  )
			);
		} finally {
			setPending( false );
		}
	}

	if ( null === enabled ) {
		return (
			<Card className="feather-pause-card">
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	const label = enabled
		? __( 'Elementor telemetry blocked', 'feather-performance' )
		: __( 'Block Elementor telemetry', 'feather-performance' );

	const description = enabled
		? __(
				"Elementor's tracker flags, my.elementor.com phone-home (banners, what's-new feed, update banners, upsells), and the AI editor bundle are all blocked. Turn off to restore Elementor's defaults.",
				'feather-performance'
		  )
		: __(
				"Blocks Elementor's tracker, my.elementor.com phone-home, and the AI editor bundle. Per-feature toggles are on the Features page.",
				'feather-performance'
		  );

	return (
		<Card className="feather-pause-card">
			<CardBody>
				{ error && (
					<Notice
						status="error"
						isDismissible
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }
				<div className="feather-pause-card-row">
					<div className="feather-pause-card-text">
						<strong>{ label }</strong>
						<small>{ description }</small>
					</div>
					<ToggleControl
						label=""
						checked={ enabled }
						disabled={ pending }
						onChange={ ( checked: boolean ) => toggle( checked ) }
					/>
				</div>
			</CardBody>
		</Card>
	);
}
