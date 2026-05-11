/**
 * Compact 3-state theme cycler for the topbar.
 *
 * Click order: system → light → dark → system. The icon shown reflects the
 * CURRENT mode so the user can see which mode is active at a glance:
 *   - monitor when 'system'
 *   - sun     when 'light'
 *   - moon    when 'dark'
 *
 * Persists via REST and bubbles the new value back to App.tsx for live
 * application via the data-theme attribute.
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { updateSettings } from '../api/client';
import type { Theme } from '../types';

interface Props {
	value: Theme;
	onChange: ( theme: Theme ) => void;
}

const ORDER: Theme[] = [ 'system', 'light', 'dark' ];

const LABELS: Record< Theme, string > = {
	system: __( 'System', 'feather-performance' ),
	light: __( 'Light', 'feather-performance' ),
	dark: __( 'Dark', 'feather-performance' ),
};

export default function ThemeToggle( { value, onChange }: Props ): JSX.Element {
	const [ pending, setPending ] = useState( false );

	async function cycle(): Promise< void > {
		const idx = ORDER.indexOf( value );
		const next = ORDER[ ( idx + 1 ) % ORDER.length ];

		// Optimistic — apply immediately, persist in background.
		onChange( next );
		setPending( true );
		try {
			await updateSettings( { theme: next } );
		} catch ( _ ) {
			// Revert on failure.
			onChange( value );
		} finally {
			setPending( false );
		}
	}

	const nextLabel = LABELS[ ORDER[ ( ORDER.indexOf( value ) + 1 ) % ORDER.length ] ];

	return (
		<button
			type="button"
			className="feather-theme-toggle"
			onClick={ cycle }
			disabled={ pending }
			title={ sprintf(
				/* translators: 1: current theme, 2: next theme on click */
				__( 'Theme: %1$s. Click to switch to %2$s.', 'feather-performance' ),
				LABELS[ value ],
				nextLabel
			) }
			aria-label={ sprintf(
				/* translators: 1: current theme, 2: next theme on click */
				__( 'Theme: %1$s. Click to switch to %2$s.', 'feather-performance' ),
				LABELS[ value ],
				nextLabel
			) }
		>
			<ThemeIcon mode={ value } />
		</button>
	);
}

function ThemeIcon( { mode }: { mode: Theme } ): JSX.Element {
	const common = {
		width: 18,
		height: 18,
		viewBox: '0 0 24 24',
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: 2,
		strokeLinecap: 'round' as const,
		strokeLinejoin: 'round' as const,
	};

	if ( mode === 'system' ) {
		return (
			<svg { ...common } aria-hidden="true">
				<rect x="2" y="3" width="20" height="14" rx="2" />
				<line x1="8" y1="21" x2="16" y2="21" />
				<line x1="12" y1="17" x2="12" y2="21" />
			</svg>
		);
	}

	if ( mode === 'light' ) {
		return (
			<svg { ...common } aria-hidden="true">
				<circle cx="12" cy="12" r="4" />
				<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
			</svg>
		);
	}

	// dark
	return (
		<svg { ...common } aria-hidden="true">
			<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
		</svg>
	);
}
