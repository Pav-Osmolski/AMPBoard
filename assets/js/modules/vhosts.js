// assets/js/modules/vhosts.js
export function setupVhostCertButtons() {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			bindButtons();
			setupFiltering();
			setupOpenFolder();
		} );
	} else {
		bindButtons();
		setupFiltering();
		setupOpenFolder();
	}
}

function bindButtons() {
	const buttons = document.querySelectorAll( '[data-generate-cert]' );

	buttons.forEach( button => {
		button.addEventListener( 'click', () => {
			const name = button.dataset.generateCert;
			if ( confirm( 'Generate a new SSL certificate for ' + name + '?' ) ) {
				fetch( `${ window.BASE_URL }utils/generate_cert.php?name=${ encodeURIComponent( name ) }` )
					.then( async res => ( { ok: res.ok, msg: await res.text() } ) )
					.then( ( { ok, msg } ) => {
						alert( msg );
						if ( ok && msg.includes( 'successfully' ) ) {
							const restartBtn = document.getElementById( 'restart-apache' );
							if ( restartBtn ) restartBtn.click();
						}
					} )
					.catch( () => alert( 'Failed to run cert script.' ) );
			}
		} );
	} );
}

function setupFiltering() {
	const filter = document.getElementById( 'vhost-filter' );
	const rows = document.querySelectorAll( '#vhosts-table tbody tr' );

	if ( !filter || !rows.length ) return;

	filter.addEventListener( 'change', () => {
		const value = filter.value;
		let visibleCount = 0;

		rows.forEach( row => {
			const isSSL = row.classList.contains( 'vhost-ssl' );
			const hasCert = row.classList.contains( 'cert-valid' );
			const hasHost = row.classList.contains( 'host-valid' );

			let show = true;
			if ( value === 'missing-cert' ) show = !hasCert;
			else if ( value === 'missing-host' ) show = !hasHost;
			else if ( value === 'ssl-only' ) show = isSSL;
			else if ( value === 'non-ssl' ) show = !isSSL;

			row.style.display = show ? '' : 'none';
			if ( show ) visibleCount++;
		} );

		const emptyMsg = document.getElementById( 'vhost-empty-msg' );
		if ( emptyMsg ) {
			emptyMsg.style.display = visibleCount === 0 ? '' : 'none';
		}
	} );
}

function setupOpenFolder() {
	document.addEventListener( 'click', e => {
		if ( e.target.matches( '.open-folder' ) ) {
			const path = e.target.dataset.path.replace( /^"(.*)"$/, '$1' );
			if ( path ) {
				fetch( `${ window.BASE_URL }utils/open_folder.php`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify( {path} )
				} );
			}
		}
	} );
}
