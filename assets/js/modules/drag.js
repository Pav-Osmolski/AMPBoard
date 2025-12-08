// assets/js/modules/drag.js
const _initialisedLists = new WeakSet();

export function enableDragSort( listSelector, opts = {} ) {
	const list = document.querySelector( listSelector );
	if ( !list ) return;

	if ( _initialisedLists.has( list ) ) return;
	_initialisedLists.add( list );

	const itemsSelector = opts.items || 'li';
	const handleSelector = opts.handle || null;

	// Configurable "interactive" rules
	const interactiveSelector = opts.interactiveSelector || 'input, select, textarea, button, a, [contenteditable], .no-drag';
	const allowInsideHandle = opts.allowInsideHandle || '[data-drag-allow]'; // whitelist inside handle
	const allowHandleInteractive = opts.allowHandleInteractive ?? true; // allow whitelisted interactives inside handle

	let dragSrcEl = null;
	let dragging = false;

	// Pointer drag state (mobile / touch)
	let pointerDrag = {
		active: false,
		startX: 0,
		startY: 0,
		id: null,
		started: false, // becomes true once threshold passed
		didReorder: false,
	};

	function isInteractive( el ) {
		return !!el.closest( interactiveSelector );
	}

	function resetDragState() {
		Array.from( list.querySelectorAll( itemsSelector ) ).forEach( el => {
			el.classList.remove( 'dragElem' );
			el.classList.remove( 'over' );
			el.draggable = false;
		} );
		dragSrcEl = null;
		dragging = false;

		if ( pointerDrag.id !== null ) {
			// Best-effort release pointer capture on the dragged element
			if ( dragSrcEl && dragSrcEl.hasPointerCapture && dragSrcEl.hasPointerCapture( pointerDrag.id ) ) {
				try {
					dragSrcEl.releasePointerCapture( pointerDrag.id );
				} catch ( _ ) {
				}
			}
		}

		pointerDrag = {
			active: false,
			startX: 0,
			startY: 0,
			id: null,
			started: false,
			didReorder: false,
		};
	}

	function reorderItems( target ) {
		if ( !dragSrcEl || !target || target === dragSrcEl ) return;

		const children = Array.from( list.querySelectorAll( `:scope > ${ itemsSelector }` ) );
		const from = children.indexOf( dragSrcEl );
		const to = children.indexOf( target );

		if ( from > -1 && to > -1 && from !== to ) {
			if ( from < to ) {
				target.after( dragSrcEl );
			} else {
				target.before( dragSrcEl );
			}
			pointerDrag.didReorder = true;
		}
	}

	// Shared pointerdown: decide mouse (HTML5 DnD) vs touch (pointer-based)
	list.addEventListener( 'pointerdown', ( e ) => {
		const item = e.target.closest( itemsSelector );
		if ( !item || !list.contains( item ) ) return;

		const isHandleHit = handleSelector ? !!e.target.closest( handleSelector ) : true;
		if ( handleSelector && !isHandleHit ) return;

		const interactiveHit = isInteractive( e.target );

		// If we hit an interactive element…
		if ( interactiveHit ) {
			// …but it's inside the handle and on the allowlist, let it start drag
			const allowed = allowHandleInteractive && isHandleHit && e.target.closest( allowInsideHandle );
			if ( !allowed ) return;
		}

		// Mouse / trackpad: use HTML5 DnD path
		if ( e.pointerType === 'mouse' ) {
			item.draggable = true;
			return;
		}

		// Touch / pen: use custom pointer drag
		dragSrcEl = item;
		pointerDrag.active = true;
		pointerDrag.id = e.pointerId;
		pointerDrag.startX = e.clientX;
		pointerDrag.startY = e.clientY;
		pointerDrag.started = false;
		pointerDrag.didReorder = false;

		try {
			item.setPointerCapture( e.pointerId );
		} catch ( _ ) {
		}
	}, {capture: true} );

	// HTML5 drag events (desktop mouse)
	list.addEventListener( 'dragstart', ( e ) => {
		const item = e.target.closest( itemsSelector );
		if ( !item || !list.contains( item ) ) return;

		dragSrcEl = item;
		dragging = true;

		if ( e.dataTransfer ) {
			e.dataTransfer.effectAllowed = 'move';
			try {
				e.dataTransfer.setData( 'text/plain', '' );
			} catch ( _ ) {
			}
		}

		item.classList.add( 'dragElem' );
	} );

	list.addEventListener( 'dragenter', ( e ) => {
		const item = e.target.closest( itemsSelector );
		if ( !item || !list.contains( item ) ) return;
		item.classList.add( 'over' );
	} );

	list.addEventListener( 'dragover', ( e ) => {
		if ( dragSrcEl ) e.preventDefault();
	} );

	list.addEventListener( 'dragleave', ( e ) => {
		const item = e.target.closest( itemsSelector );
		if ( item && list.contains( item ) ) {
			item.classList.remove( 'over' );
		}
	} );

	list.addEventListener( 'drop', ( e ) => {
		e.preventDefault();
		const target = e.target.closest( itemsSelector );
		if ( !target || !dragSrcEl || target === dragSrcEl ) return;

		reorderItems( target );

		if ( pointerDrag.didReorder || dragging ) {
			list.dispatchEvent( new CustomEvent( 'sorted', {bubbles: true} ) );
		}
		target.classList.remove( 'over' );
	} );

	list.addEventListener( 'dragend', ( e ) => {
		const item = e.target.closest( itemsSelector );
		if ( item && list.contains( item ) ) {
			item.classList.remove( 'dragElem' );
			item.draggable = false;
		}
		Array.from( list.querySelectorAll( itemsSelector ) ).forEach( el => el.classList.remove( 'over' ) );
		dragSrcEl = null;
		dragging = false;
	} );

	// Pointer-based drag for touch / pen
	list.addEventListener( 'pointermove', ( e ) => {
		if ( !pointerDrag.active || e.pointerId !== pointerDrag.id || !dragSrcEl ) return;

		const dx = Math.abs( e.clientX - pointerDrag.startX );
		const dy = Math.abs( e.clientY - pointerDrag.startY );

		// Small movement threshold so simple taps do not start a drag
		if ( !pointerDrag.started ) {
			if ( dx + dy < 6 ) return; // ~3px in each direction
			pointerDrag.started = true;
			dragging = true;
			dragSrcEl.classList.add( 'dragElem' );
		}

		// When dragging, prevent scroll
		e.preventDefault();

		const targetEl = document.elementFromPoint( e.clientX, e.clientY );
		if ( !targetEl ) return;

		const overItem = targetEl.closest( itemsSelector );
		if ( !overItem || !list.contains( overItem ) || overItem === dragSrcEl ) return;

		// Update "over" classes
		Array.from( list.querySelectorAll( itemsSelector ) ).forEach( el => el.classList.remove( 'over' ) );
		overItem.classList.add( 'over' );

		reorderItems( overItem );
	} );

	function endPointerDrag( e ) {
		if ( !pointerDrag.active || e.pointerId !== pointerDrag.id ) return;

		// If we actually performed a drag reorder, emit sorted
		if ( pointerDrag.started && pointerDrag.didReorder ) {
			list.dispatchEvent( new CustomEvent( 'sorted', {bubbles: true} ) );
		}

		resetDragState();
	}

	list.addEventListener( 'pointerup', endPointerDrag );
	list.addEventListener( 'pointercancel', endPointerDrag );

	// Suppress accidental click activation after a drag
	list.addEventListener( 'click', ( e ) => {
		if ( !dragging ) return;
		if ( handleSelector && e.target.closest( handleSelector ) ) {
			e.preventDefault();
		}
	}, true );
}
