/**
 * Brand mark — the F-feather PNG from packages/feather-performance/assets/img/.
 *
 * Picks the ink (dark) or cream (light) variant based on the active theme.
 * Falls back to a small inline SVG glyph if the bundled PNGs aren't present
 * (so the app still has a logo even before the brand assets ship).
 */

import { __ } from '@wordpress/i18n';

interface Props {
	/** Pixel size; height = width. Default 24. */
	size?: number;
	/** 'auto' uses the active document theme; 'ink' / 'cream' force a variant. */
	variant?: 'auto' | 'ink' | 'cream';
	className?: string;
}

export default function Mark( { size = 24, variant = 'auto', className }: Props ): JSX.Element {
	const logos = window.feather?.boot?.logos;
	const resolved = resolveVariant( variant );
	const url = resolved === 'cream' ? logos?.cream : logos?.ink;

	if ( url ) {
		return (
			<img
				src={ url }
				width={ size }
				height={ size }
				alt={ __( 'Feather', 'feather-performance' ) }
				className={ className }
				style={ { display: 'inline-block', objectFit: 'contain' } }
			/>
		);
	}

	// Inline SVG fallback — a lightweight feather-ish glyph when PNGs aren't available.
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			className={ className }
			aria-label={ __( 'Feather', 'feather-performance' ) }
		>
			<path
				d="M5 19V5h6.5a4.5 4.5 0 0 1 0 9H8m-3 5l4-4"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

function resolveVariant( v: 'auto' | 'ink' | 'cream' ): 'ink' | 'cream' {
	if ( v !== 'auto' ) {
		return v;
	}
	const docTheme = document.documentElement.getAttribute( 'data-theme' );
	if ( docTheme === 'dark' ) {
		return 'cream';
	}
	if ( docTheme === 'light' ) {
		return 'ink';
	}
	// 'system' — read prefers-color-scheme.
	if ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ) {
		return 'cream';
	}
	return 'ink';
}
