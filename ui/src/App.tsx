/**
 * Top-level layout: persistent sidebar + main panel.
 * Routing: page-level navigation between WP admin submenu URLs, plus an
 * internal state cache so toggle interactions feel instant.
 */

import { useEffect, useState } from '@wordpress/element';
import Sidebar from './components/Sidebar';
import Mark from './components/Mark';
import ThemeToggle from './components/ThemeToggle';
import Footer from './components/Footer';
import Dashboard from './routes/Dashboard';
import Database from './routes/Database';
import Features from './routes/Features';
import Scan from './routes/Scan';
import SettingsPanel from './routes/Settings';
import About from './routes/About';
import type { Theme } from './types';

export type RouteId =
	| 'dashboard'
	| 'features'
	| 'scan'
	| 'database'
	| 'settings'
	| 'about';

const ROUTE_IDS: ReadonlyArray< RouteId > = [
	'dashboard',
	'features',
	'scan',
	'database',
	'settings',
	'about',
];

function isRouteId( value: unknown ): value is RouteId {
	return (
		typeof value === 'string' &&
		( ROUTE_IDS as readonly string[] ).includes( value )
	);
}

function applyTheme( theme: Theme ): void {
	document.documentElement.setAttribute( 'data-theme', theme );
	// Also tag #feather-admin-wrap so we can scope styles when the WP admin
	// wraps us in its own theme container.
	const wrap = document.getElementById( 'feather-admin-wrap' );
	if ( wrap ) {
		wrap.setAttribute( 'data-theme', theme );
	}
}

export default function App(): JSX.Element {
	const boot = window.feather?.boot;

	const initialRoute: RouteId = isRouteId( boot?.currentRoute )
		? boot!.currentRoute
		: 'dashboard';

	const [ route, setRoute ] = useState< RouteId >( initialRoute );
	const [ theme, setTheme ] = useState< Theme >( boot?.theme ?? 'system' );

	// Apply data-theme synchronously *before* the first render commits so the
	// brand mark and any CSS variables that key off [data-theme] see the
	// correct value on the initial paint. Without this the Mark briefly reads
	// 'system' and shows the wrong variant when the OS preference disagrees
	// with the saved Feather theme.
	applyTheme( theme );

	// Re-apply on every theme change (handles toggle clicks).
	useEffect( () => {
		applyTheme( theme );
	}, [ theme ] );

	// React to OS-level theme changes when in 'system' mode.
	useEffect( () => {
		if ( theme !== 'system' || ! window.matchMedia ) {
			return;
		}
		const mql = window.matchMedia( '(prefers-color-scheme: dark)' );
		const handler = (): void => applyTheme( 'system' );
		mql.addEventListener( 'change', handler );
		return () => mql.removeEventListener( 'change', handler );
	}, [ theme ] );

	return (
		<div className="feather-app">
			<header className="feather-topbar">
				<div className="feather-wordmark">
					<Mark size={ 22 } theme={ theme } />
					<span className="feather-wordmark-text">feather</span>
				</div>
				<div className="feather-topbar-actions">
					<ThemeToggle value={ theme } onChange={ setTheme } />
				</div>
			</header>
			<Sidebar current={ route } onNavigate={ setRoute } />
			<main className="feather-main" aria-live="polite">
				{ route === 'dashboard' && <Dashboard /> }
				{ route === 'features' && <Features /> }
				{ route === 'scan' && <Scan /> }
				{ route === 'database' && <Database /> }
				{ route === 'settings' && <SettingsPanel /> }
				{ route === 'about' && <About /> }
				<Footer />
			</main>
		</div>
	);
}
