/**
 * Brand mark — the F-feather PNG from packages/feather-performance/assets/img/.
 *
 * Picks the ink (dark) or cream (light) variant based on the active theme.
 * Falls back to a small inline SVG glyph if the bundled PNGs aren't present
 * (so the app still has a logo even before the brand assets ship).
 */

import { __ } from '@wordpress/i18n';
import type { Theme } from '../types';

interface Props {
	/** Pixel size; height = width. Default 24. */
	size?: number;
	/**
	 * 'auto' (default) resolves the variant from the active document theme.
	 * 'ink' / 'cream' force a variant.
	 *
	 * Prefer passing `theme` instead of relying on `auto` — `auto` reads
	 * `data-theme` off `<html>`, which isn't always set yet at the moment
	 * the first render runs (the App's applyTheme effect fires after paint),
	 * so the variant can briefly land on the wrong one when the OS prefers
	 * a different scheme from the user's saved Feather theme.
	 */
	variant?: 'auto' | 'ink' | 'cream';
	/** When provided, used to resolve 'auto' without touching the DOM. */
	theme?: Theme;
	className?: string;
}

export default function Mark( {
	size = 24,
	variant = 'auto',
	theme,
	className,
}: Props ): JSX.Element {
	const logos = window.feather?.boot?.logos;
	const resolved = resolveVariant( variant, theme );
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

function resolveVariant(
	v: 'auto' | 'ink' | 'cream',
	theme: Theme | undefined
): 'ink' | 'cream' {
	if ( v !== 'auto' ) {
		return v;
	}
	// Prefer the explicit theme prop when the caller knows it. This avoids
	// the data-theme attribute timing window described in the Props doc.
	if ( theme === 'light' ) {
		return 'ink';
	}
	if ( theme === 'dark' ) {
		return 'cream';
	}
	const docTheme = document.documentElement.getAttribute( 'data-theme' );
	if ( docTheme === 'dark' ) {
		return 'cream';
	}
	if ( docTheme === 'light' ) {
		return 'ink';
	}
	// 'system' (explicit or implied) — read prefers-color-scheme.
	if (
		window.matchMedia &&
		window.matchMedia( '(prefers-color-scheme: dark)' ).matches
	) {
		return 'cream';
	}
	return 'ink';
}
