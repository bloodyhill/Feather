/**
 * Feather admin entry point. Mounted into <div id="feather-admin"> by AdminMenu.
 */

import { createRoot } from '@wordpress/element';
import { initApiClient } from './api/client';
import App from './App';
import './styles/index.css';

function bootstrap(): void {
	const mount = document.getElementById( 'feather-admin' );
	if ( ! mount ) {
		return;
	}
	initApiClient();
	const root = createRoot( mount );
	root.render( <App /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
