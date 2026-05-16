import { __ } from '@wordpress/i18n';

const REVIEW_URL =
	'https://wordpress.org/support/plugin/feather-performance/reviews/#new-post';

export default function Footer(): JSX.Element {
	return (
		<footer className="feather-footer">
			<span className="feather-footer-prompt">
				{ __( 'Loving Feather?', 'feather-performance' ) }
			</span>
			<a
				className="feather-footer-link"
				href={ REVIEW_URL }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Leave a review on WordPress.org', 'feather-performance' ) }
				<span aria-hidden="true" className="feather-footer-arrow">
					↗
				</span>
			</a>
		</footer>
	);
}
