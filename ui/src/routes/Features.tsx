import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { fetchFeatures, toggleFeature } from '../api/client';
import type { Feature, FeatureCategory } from '../types';

const CATEGORY_LABELS: Record< FeatureCategory, string > = {
	elementor: __( 'Elementor', 'feather-performance' ),
	wp: __( 'WordPress Hygiene', 'feather-performance' ),
	media: __( 'Lazy Loading & Media', 'feather-performance' ),
	hints: __( 'Resource Hints', 'feather-performance' ),
	db: __( 'Database', 'feather-performance' ),
	conditional: __( 'Per-page / Conditional', 'feather-performance' ),
	compat: __( 'Compatibility', 'feather-performance' ),
	reporting: __( 'Reporting & Insights', 'feather-performance' ),
	advanced: __( 'Advanced', 'feather-performance' ),
};

export default function Features(): JSX.Element {
	const [ features, setFeatures ] = useState< Feature[] | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ pendingId, setPendingId ] = useState< string | null >( null );

	useEffect( () => {
		fetchFeatures()
			.then( ( res ) => setFeatures( res.features ) )
			.catch( ( err: Error ) => setError( err.message ) );
	}, [] );

	async function handleToggle( id: string, next: boolean ): Promise< void > {
		setPendingId( id );
		try {
			const updated = await toggleFeature( id, next );
			setFeatures( ( prev ) =>
				prev
					? prev.map( ( f ) => ( f.id === id ? { ...f, ...updated } : f ) )
					: prev
			);
		} catch ( err ) {
			const msg = err instanceof Error ? err.message : __( 'Toggle failed.', 'feather-performance' );
			setError( msg );
		} finally {
			setPendingId( null );
		}
	}

	if ( error ) {
		return (
			<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
				{ error }
			</Notice>
		);
	}

	if ( features === null ) {
		return (
			<div className="feather-loading">
				<Spinner />
			</div>
		);
	}

	const grouped = groupBy( features, 'category' );
	const categories = Object.keys( grouped ).sort() as FeatureCategory[];

	return (
		<div className="feather-features">
			{ categories.map( ( cat ) => (
				<section key={ cat } className="feather-feature-group">
					<h2>{ CATEGORY_LABELS[ cat ] ?? cat }</h2>
					<div className="feather-feature-grid">
						{ grouped[ cat ].map( ( feat ) => (
							<Card key={ feat.id } className="feather-feature-card">
								<CardHeader>
									<strong>{ feat.label }</strong>
									<RiskBadge feature={ feat } />
								</CardHeader>
								<CardBody>
									<p className="feather-feature-desc">{ feat.description }</p>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ feat.enabled ? __( 'Enabled', 'feather-performance' ) : __( 'Disabled', 'feather-performance' ) }
										checked={ feat.enabled }
										disabled={ ! feat.unlocked || pendingId === feat.id || ! feat.has_handler }
										onChange={ ( next: boolean ) => handleToggle( feat.id, next ) }
									/>
									{ ! feat.has_handler && (
										<small className="feather-feature-note">
											{ __( 'Coming in a future release.', 'feather-performance' ) }
										</small>
									) }
								</CardBody>
							</Card>
						) ) }
					</div>
				</section>
			) ) }
		</div>
	);
}

function RiskBadge( { feature }: { feature: Feature } ): JSX.Element {
	const labels: Record< Feature[ 'recommendation' ], string > = {
		safe: __( 'Safe', 'feather-performance' ),
		scan_recommended: __( 'Scan recommended', 'feather-performance' ),
		risky: __( 'Risky', 'feather-performance' ),
		dangerous: __( 'Will break — scan found usages', 'feather-performance' ),
	};
	const r = feature.recommendation;
	const klass =
		r === 'scan_recommended'
			? 'gated'
			: r === 'dangerous'
			? 'risky'
			: r;
	return (
		<span className={ `feather-badge feather-badge--${ klass }` }>{ labels[ r ] }</span>
	);
}

function groupBy< T, K extends keyof T >(
	items: T[],
	key: K
): Record< string, T[] > {
	return items.reduce< Record< string, T[] > >( ( acc, item ) => {
		const k = String( item[ key ] );
		( acc[ k ] = acc[ k ] || [] ).push( item );
		return acc;
	}, {} );
}
