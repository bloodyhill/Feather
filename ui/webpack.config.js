/**
 * Custom webpack config that extends @wordpress/scripts' defaults.
 *
 * Why this file exists:
 *   When wp-scripts is invoked positionally (e.g. `wp-scripts build src/index.tsx`),
 *   the entry chunk key includes the source filename, producing artefacts like
 *   `index.tsx.css` and `index.tsx-rtl.css`. Overriding the entry here gives us
 *   the conventional `index.js` + `index.css` + `index-rtl.css` triplet that
 *   AssetEnqueue.php expects.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src/index.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, '../assets/admin' ),
	},
};
