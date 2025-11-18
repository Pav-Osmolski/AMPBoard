// assets/js/modules/search.js
export function initSearch() {
	document.addEventListener( 'DOMContentLoaded', () => {
		const searchBar = document.querySelector( '.search-bar' );
		if ( searchBar ) {
			searchBar.addEventListener( 'input', searchProjects );
		}
	} );
}

export function searchProjects() {
	const input = document.querySelector( '.search-bar' )?.value.toLowerCase() || '';
	const items = document.querySelectorAll( '#folders-view .columns li' );

	items.forEach( item => {
		const text = item.textContent.toLowerCase();
		item.style.display = text.includes( input ) ? '' : 'none';
	} );
}
