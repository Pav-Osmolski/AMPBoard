// assets/js/modules/view.js
export function initViewToggles() {
	document.addEventListener( 'DOMContentLoaded', () => {
		const views = {
			'index': document.getElementById( 'folders-view' ),
			'settings': document.getElementById( 'settings-view' ),
			'phpinfo': document.getElementById( 'phpinfo-view' ),
			'apache': document.getElementById( 'apache-view' ),
			'mysql': document.getElementById( 'mysql-view' ),
		};

		const pathMap = {
			'index': 'index',
			'settings': 'settings',
			'phpinfo': 'phpinfo',
			'apache-inspector': 'apache',
			'mysql-inspector': 'mysql',
		};

		const toggleMap = {
			'toggle-index': 'index',
			'toggle-settings': 'settings',
			'toggle-phpinfo': 'phpinfo',
			'toggle-apache-inspector': 'apache-inspector',
			'toggle-mysql-inspector': 'mysql-inspector',
		};

		function getCurrentRoute() {
			const params = new URLSearchParams( window.location.search );
			return params.get( 'view' ) || 'index';
		}

		function updatePageTitle( routeKey ) {
			const prefixEl = document.getElementById( 'page-title-prefix' );
			if ( !prefixEl ) return;

			const prefix = prefixEl.dataset.prefix || '';

			const titles = {
				'index': 'Modern Localhost and Remote Dashboard for Apache, MySQL & PHP',
				'settings': 'Settings',
				'phpinfo': 'PHP Info',
				'apache-inspector': 'Apache Inspector',
				'mysql-inspector': 'MySQL Inspector',
			};

			const subtitle = titles[routeKey] || titles.index;
			document.title = prefix + subtitle;
		}

		function updateActiveNav( routeKey ) {
			Object.entries( toggleMap ).forEach( ( [ toggleId, viewKey ] ) => {
				const toggle = document.getElementById( toggleId );
				if ( !toggle ) return;

				if ( viewKey === routeKey ) {
					toggle.classList.add( 'active-page' );
					toggle.setAttribute( 'aria-current', 'page' );
				} else {
					toggle.classList.remove( 'active-page' );
					toggle.removeAttribute( 'aria-current' );
				}
			} );
		}

		function showView( routeKey, push = false ) {
			const key = pathMap[routeKey] || 'index';

			updateActiveNav( routeKey );

			Object.entries( views ).forEach( ( [ k, el ] ) => {
				if ( el ) el.classList.toggle( 'visible', k === key );
			} );

			updatePageTitle( routeKey );

			// Lazy load for Apache and MySQL views
			if ( key === 'apache' || key === 'mysql' ) {
				const target = views[key];
				if ( target && !target.dataset.loaded ) {
					// To use a spinning emoji, add the class "emoji" to "loader" and insert your emoji within the <span>
					target.innerHTML = '<div class="loader"><span class="spinner" role="img" aria-label="Loading"></span></div>';
					const url = `utils/${ key }_inspector.php`;

					fetch( `${ window.BASE_URL || '/' }${ url }` )
						.then( res => res.text() )
						.then( html => {
							target.innerHTML = html;
							target.dataset.loaded = 'true';
						} )
						.catch( err => {
							target.innerHTML = `<p class="error">Failed to load ${ url }</p>`;
							console.error( `[view.js] Failed to fetch ${ url }`, err );
						} );
				}
			}

			if ( push ) {
				const base = window.BASE_URL || window.location.pathname;
				const param = key === 'index' ? '' : '?view=' + encodeURIComponent( routeKey );
				history.pushState( {}, '', base + param );
			}
		}

		// Attach toggle click handlers
		Object.entries( toggleMap ).forEach( ( [ toggleId, routeKey ] ) => {
			const toggle = document.getElementById( toggleId );
			if ( toggle ) {
				toggle.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					showView( routeKey, true );
				} );
			}
		} );

		// Initial load
		showView( getCurrentRoute() );

		// Back/forward navigation
		window.addEventListener( 'popstate', () => {
			showView( getCurrentRoute() );
		} );
	} );
}
