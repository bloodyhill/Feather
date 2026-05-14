import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

export default function About(): JSX.Element {
	const version = window.feather?.boot?.version ?? '0.0.0';
	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'About Feather', 'feather-performance' ) }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ sprintf(
						/* translators: %s: plugin version */
						__( 'Version %s', 'feather-performance' ),
						version
					) }
				</p>
				<p>
					{ __(
						'Feather is free, open-source, and GPL-licensed.',
						'feather-performance'
					) }
				</p>
			</CardBody>
		</Card>
	);
}
