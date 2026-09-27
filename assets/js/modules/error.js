// assets/js/modules/error.js
function initGenericErrorLog( {
	                              sectionId,
	                              toggleButtonId,
	                              logElementId,
	                              endpoint
                              } ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		const body = document.body;
		const logSection = document.getElementById( sectionId );
		if ( !logSection ) return;

		const toggleBtn = document.getElementById( toggleButtonId );
		const logElement = document.getElementById( logElementId );

		if ( !toggleBtn || !logElement ) return;

		toggleBtn.addEventListener( 'click', function () {
			const isVisible = logElement.classList.contains( 'visible' );
			logElement.classList.toggle( 'visible', !isVisible );
			this.setAttribute( 'aria-expanded', !isVisible );
		} );

		if ( body.getAttribute( 'data-ajax-error-log-enabled' ) !== 'true' ) return;

		function displayLogContent( data ) {
			const isEmpty = data.trim() === '';
			const code = document.createElement( 'code' );
			if ( isEmpty ) code.className = 'muted';
			code.textContent = isEmpty ? "No errors logged. You're doing great. 🎉" : data;
			logElement.replaceChildren( code );
		}

		function fetchLog() {
			fetch( `${ window.BASE_URL }${ endpoint }`, {cache: 'no-store'} )
				.then( res => res.ok ? res.text() : Promise.reject() )
				.then( displayLogContent );
		}

		fetch( `${ window.BASE_URL }${ endpoint }`, {cache: 'no-store'} )
			.then( res => res.ok ? res.text() : Promise.reject() )
			.then( data => {
				displayLogContent( data );
				setInterval( fetchLog, 3000 );
			} )
			.catch( () => {
			} );
	} );
}

// Public wrappers — keeps main.js simple
export function initApacheErrorLog() {
	initGenericErrorLog( {
		sectionId: 'apache-error-log-section',
		toggleButtonId: 'toggle-apache-error-log',
		logElementId: 'apache-error-log',
		endpoint: 'utils/apache_error_log.php'
	} );
}

export function initPhpErrorLog() {
	initGenericErrorLog( {
		sectionId: 'php-error-log-section',
		toggleButtonId: 'toggle-php-error-log',
		logElementId: 'php-error-log',
		endpoint: 'utils/php_error_log.php'
	} );
}
