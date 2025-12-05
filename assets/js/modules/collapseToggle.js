// assets/js/modules/collapseToggle.js
export function initCollapseToggle() {
	function resolveTarget( button ) {
		const customSelector = button.getAttribute( 'data-target-selector' );
		const region = button.getAttribute( 'data-collapse-region' ) || 'custom';

		if ( customSelector ) {
			return document.querySelector( customSelector );
		}

		if ( region === 'header' ) {
			return document.querySelector( 'header[role="banner"]' ) || document.querySelector( 'header' );
		}

		if ( region === 'footer' ) {
			return document.querySelector( 'footer' );
		}

		return null;
	}

	function setState( button, target, collapsed ) {
		const region = button.getAttribute( 'data-collapse-region' ) || 'custom';
		const labelExpanded = button.getAttribute( 'data-label-expanded' ) || 'Collapse section';
		const labelCollapsed = button.getAttribute( 'data-label-collapsed' ) || 'Expand section';
		const srSpan = button.querySelector( '.collapse-toggle-label' );

		button.setAttribute( 'data-state', collapsed ? 'collapsed' : 'expanded' );
		button.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		button.setAttribute( 'aria-label', collapsed ? labelCollapsed : labelExpanded );

		if ( srSpan ) {
			srSpan.textContent = collapsed ? labelCollapsed : labelExpanded;
		}

		if ( target ) {
			if ( collapsed ) {
				target.classList.add( 'is-collapsed' );
				target.classList.remove( 'is-open' );
			} else {
				target.classList.remove( 'is-collapsed' );
				target.classList.add( 'is-open' );
			}
		}

		if ( region === 'header' || region === 'footer' ) {
			const body = document.body;
			body.classList.toggle( `${ region }-collapsed`, collapsed );
		}
	}

	function bind() {
		const buttons = document.querySelectorAll( '.collapse-toggle' );

		if ( !buttons.length ) {
			return;
		}

		buttons.forEach( ( button ) => {
			// Initial state: expanded.
			button.setAttribute( 'data-state', 'expanded' );
			button.setAttribute( 'aria-expanded', 'true' );

			button.addEventListener( 'click', ( event ) => {
				event.preventDefault();

				const target = resolveTarget( button );
				const collapsed = button.getAttribute( 'data-state' ) === 'collapsed';

				setState( button, target, !collapsed );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
}
