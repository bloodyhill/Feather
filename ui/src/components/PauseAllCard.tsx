import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { fetchSettings, updateSettings } from '../api/client';

/**
 * Top-of-Dashboard card exposing the master "Pause all optimizations" toggle.
 *
 * When OFF (default): a neutral card explaining the toggle.
 * When ON: a yellow warning state with a Resume button.
 *
 * Reads `optimizers_paused` on mount via GET /settings, persists via POST /settings.
 * Optimistically updates local state on toggle; rolls back on error.
 */
export default function PauseAllCard(): JSX.Element {
	const [ paused, setPaused ] = useState< boolean | null >( null );
	const [ pending, setPending ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;
		fetchSettings()
			.then( ( settings ) => {
				if ( ! cancelled ) {
					setPaused( !! settings.optimizers_paused );
				}
			} )
			.catch( ( err: Error ) => {
				if ( ! cancelled ) {
					setError( err.message );
					setPaused( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	async function toggle( next: boolean ): Promise< void > {
		const previous = paused;
		setPaused( next );
		setPending( true );
		setError( null );
		try {
			const updated = await updateSettings( { optimizers_paused: next } );
			setPaused( !! updated.optimizers_paused );
		} catch ( err ) {
			// Roll back optimistic update.
			setPaused( previous );
			setError(
				err instanceof Error
					? err.message
					: __(
							'Could not update pause setting.',
							'feather-performance'
					  )
			);
		} finally {
			setPending( false );
		}
	}

	if ( null === paused ) {
		return (
			<Card className="feather-pause-card">
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	if ( paused ) {
		return (
			<Card className="feather-pause-card feather-pause-card--paused">
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
							<strong>
								{ __(
									'Editing mode active — Feather optimizations paused',
									'feather-performance'
								) }
							</strong>
							<small>
								{ __(
									"Every Feather optimization is temporarily off while you edit the site in Elementor or another builder. Click Resume optimizations when you're done so visitors get the optimized version again.",
									'feather-performance'
								) }
							</small>
						</div>
						<Button
							variant="primary"
							isBusy={ pending }
							disabled={ pending }
							onClick={ () => toggle( false ) }
						>
							{ __(
								'Resume optimizations',
								'feather-performance'
							) }
						</Button>
					</div>
				</CardBody>
			</Card>
		);
	}

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
						<strong>
							{ __(
								'Pause all optimizations for editing',
								'feather-performance'
							) }
						</strong>
						<small>
							{ __(
								'Turn on while editing in Elementor. All Feather optimizations stay off until you flip it back.',
								'feather-performance'
							) }
						</small>
					</div>
					<ToggleControl
						label=""
						checked={ paused }
						disabled={ pending }
						onChange={ ( checked: boolean ) => toggle( checked ) }
					/>
				</div>
			</CardBody>
		</Card>
	);
}
