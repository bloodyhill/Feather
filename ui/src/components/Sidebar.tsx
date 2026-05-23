import { __ } from '@wordpress/i18n';
import type { RouteId } from '../App';

interface NavItem {
	id: RouteId;
	label: string;
	pageSlug: string;
}

interface Props {
	current: RouteId;
	onNavigate: ( id: RouteId ) => void;
}

const items: NavItem[] = [
	{
		id: 'dashboard',
		label: __( 'Dashboard', 'feather-performance' ),
		pageSlug: 'feather-performance',
	},
	{
		id: 'features',
		label: __( 'Features', 'feather-performance' ),
		pageSlug: 'feather-features',
	},
	{
		id: 'scan',
		label: __( 'Site Scan', 'feather-performance' ),
		pageSlug: 'feather-scan',
	},
	{
		id: 'database',
		label: __( 'Database', 'feather-performance' ),
		pageSlug: 'feather-database',
	},
	{
		id: 'settings',
		label: __( 'Settings', 'feather-performance' ),
		pageSlug: 'feather-settings',
	},
	{
		id: 'about',
		label: __( 'About', 'feather-performance' ),
		pageSlug: 'feather-about',
	},
];

export default function Sidebar( { current, onNavigate }: Props ): JSX.Element {
	const adminUrl = window.feather?.boot?.adminUrl ?? '';

	return (
		<nav
			className="feather-sidebar"
			aria-label={ __( 'Feather navigation', 'feather-performance' ) }
		>
			<ul className="feather-nav">
				{ items.map( ( item ) => {
					const href = `${ adminUrl }admin.php?page=${ item.pageSlug }`;
					const isCurrent = current === item.id;
					return (
						<li key={ item.id }>
							<a
								href={ href }
								className={ `feather-nav-item${
									isCurrent ? ' is-current' : ''
								}` }
								aria-current={ isCurrent ? 'page' : undefined }
								onClick={ ( e ) => {
									// Plain left-click without modifier keys → instant client-side
									// switch (no full reload). The href still matters for
									// middle-click, cmd-click, and bookmarking.
									if (
										e.button === 0 &&
										! e.metaKey &&
										! e.ctrlKey &&
										! e.shiftKey &&
										! e.altKey
									) {
										e.preventDefault();
										onNavigate( item.id );
										// Update the URL so deep links + browser back work.
										if ( window.history?.pushState ) {
											window.history.pushState(
												{},
												'',
												href
											);
										}
									}
								} }
							>
								{ item.label }
							</a>
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
}
