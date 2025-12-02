// assets/js/modules/folders.js
import {enableDragSort} from './drag.js';

export function initFoldersConfig() {
	document.addEventListener( 'DOMContentLoaded', () => {
		const list = document.getElementById( 'folders-config-list' );
		const addBtn = document.getElementById( 'add-folder-column' );
		const jsonInput = document.getElementById( 'folders_json_input' );
		if ( !list || !addBtn || !jsonInput ) return;

		let linkTemplates = [];
		let templateOptionsHtml = `<option value="">(none)</option>`;
		let dirty = false;
		let debounceTimer = null;

		// helpers for specialCases (object map <-> rows)

		function mapToRows( scMap ) {
			if ( scMap && typeof scMap === 'object' && !Array.isArray( scMap ) ) {
				return Object.entries( scMap ).map( ( [ match, replace ] ) => ({
					match: String( match ?? '' ),
					replace: String( replace ?? '' )
				}) );
			}
			return []; // default if missing/invalid
		}

		function rowsToMap( rows ) {
			const map = {};
			rows.forEach( ( {match = '', replace = ''} ) => {
				const m = String( match ).trim();
				const r = String( replace ).trim();
				if ( m.length || r.length ) {
					map[m] = r; // later duplicates overwrite earlier — deterministic
				}
			} );
			return map;
		}

		// templates

		function fetchLinkTemplates() {
			if ( Array.isArray( window.LINK_TEMPLATES ) ) {
				linkTemplates = window.LINK_TEMPLATES
					.map( t => t && typeof t.name === 'string' ? t.name.trim() : '' )
					.filter( Boolean );
				linkTemplates = Array.from( new Set( linkTemplates ) );
				templateOptionsHtml = `<option value="">(none)</option>` + linkTemplates.map( n => `<option value="${ n }">${ n }</option>` ).join( '' );
				return Promise.resolve( linkTemplates );
			}

			return fetch( `${ window.BASE_URL }utils/read_config.php?file=link_templates`, {cache: 'no-store'} )
				.then( res => res.json() )
				.then( data => {
					linkTemplates = (Array.isArray( data ) ? data : [])
						.map( t => t && typeof t.name === 'string' ? t.name.trim() : '' )
						.filter( Boolean );
					linkTemplates = Array.from( new Set( linkTemplates ) );
					templateOptionsHtml = `<option value="">(none)</option>` + linkTemplates.map( n => `<option value="${ n }">${ n }</option>` ).join( '' );
					return linkTemplates;
				} )
				.catch( () => {
					linkTemplates = [];
					templateOptionsHtml = `<option value="">(none)</option>`;
					return linkTemplates;
				} );
		}

		// rendering

		function specialCaseRowHtml( sc = {} ) {
			const matchVal = sc.match || '';
			const replaceVal = sc.replace || '';

			return `
				<div class="special-case">
					<input type="text"
					       class="sc-match"
					       placeholder="Match Regex"
					       aria-label="Special case match regex"
					       value="${ matchVal }">

					<input type="text"
					       class="sc-replace"
					       placeholder="Replace Regex"
					       aria-label="Special case replace regex"
					       value="${ replaceVal }">

					<button type="button"
					        class="remove-special"
					        aria-label="Remove this special case">
						✖
					</button>
				</div>
			`;
		}

		function folderItemHtml( item = {} ) {
			const casesHtml = mapToRows( item.specialCases ).map( specialCaseRowHtml ).join( '' );
			const selected = item.linkTemplate || '';
			const uid = `${ Math.random().toString( 36 ).slice( 2 ) }`;
			const labelText = typeof index === 'number'
				? `Folder ${ index }`
				: 'Folder';

			const headingId = `folder-label-${ uid }`;

			return `
				<li class="folder-config-item" role="group" aria-labelledby="${ headingId }">
					<h4 id="${ headingId }" class="folder-label">
						${ labelText }
					</h4>
					<div class="uid-container">
						<label for="title-${ uid }">Title:</label>
						<input id="title-${ uid }"
							   type="text"
							   data-key="title"
							   placeholder="Title"
							   value="${ item.title || '' }">
					</div>

					<div class="uid-container">
						<label for="href-${ uid }">Href (optional):</label>
						<input id="href-${ uid }"
							   type="text"
							   data-key="href"
							   placeholder="Href (optional)"
							   value="${ item.href || '' }">
					</div>

					<div class="uid-container">
						<label for="dir-${ uid }">Dir (relative to HTDOCS_PATH):</label>
						<input id="dir-${ uid }"
							   type="text"
							   data-key="dir"
							   placeholder="Dir (relative to HTDOCS_PATH)"
							   value="${ item.dir || '' }">
					</div>

					<div class="uid-container">
						<label for="excludeList-${ uid }">Exclude list (comma-separated):</label>
						<input id="excludeList-${ uid }"
							   type="text"
							   data-key="excludeList"
							   placeholder="Exclude list (comma-separated)"
							   value="${ (item.excludeList || []).join( ',' ) }">
					</div>

					<div class="uid-container grouped-labels">
						<label for="match-${ uid }">Match Regex:</label>
						<input id="match-${ uid }"
							   type="text"
							   data-key="match"
							   placeholder="Match Regex"
							   value="${ item.urlRules?.match ?? '' }">
					</div>

					<div class="uid-container grouped-labels">
						<label for="replace-${ uid }">Replace Regex:</label>
						<input id="replace-${ uid }"
							   type="text"
							   data-key="replace"
							   placeholder="Replace Regex"
							   value="${ item.urlRules?.replace ?? '' }">
					</div>

					<div class="uid-container grouped-labels">
						<label for="linkTemplate-${ uid }">Link Template:</label>
						<select id="linkTemplate-${ uid }"
							    class="link-template-select"
							    name="linkTemplate">
							${ templateOptionsHtml }
						</select>
					</div>

					<div class="uid-container grouped-container grouped-labels" role="group" aria-labelledby="folder-behaviour-${ uid }">
						<label id="folder-behaviour-${ uid }">Folder Behaviour:</label>

						<label for="disable-links-${ uid }">
							<input id="disable-links-${ uid }"
							       type="checkbox"
							       class="disable-links"${ item.disableLinks ? ' checked' : '' }>
							Disable links
						</label>

						<label for="require-vhost-${ uid }">
							<input id="require-vhost-${ uid }"
							       type="checkbox"
							       class="require-vhost"${ item.requireVhost ? ' checked' : '' }>
							Valid vHost only
						</label>
					</div>

					<div class="uid-container special-cases-wrapper" aria-labelledby="special-cases-${ uid }">
						<span id="special-cases-${ uid }">Special Cases:</span>
						<div class="special-cases">${ casesHtml }</div>
						<button type="button" class="add-special" aria-label="Add special case rule">➕ Add Rule</button>
					</div>

					<button type="button" class="remove-folder-column" aria-label="Remove this folder column">❌</button>
				</li>
			`;
		}

		function renumberFolders() {
			list.querySelectorAll( '.folder-config-item' ).forEach( ( li, index ) => {
				const labelEl = li.querySelector( '.folder-label' );
				if ( labelEl ) {
					labelEl.textContent = `Folder ${ index + 1 }`;
				}
			} );
		}

		function appendFolderItems( items ) {
			const frag = document.createDocumentFragment();
			const tmp = document.createElement( 'div' );
			const existingCount = list.querySelectorAll( '.folder-config-item' ).length;

			tmp.innerHTML = items
				.map( ( item, index ) => folderItemHtml( item, existingCount + index + 1 ) )
				.join( '' );

			Array.from( tmp.children ).forEach( child => frag.appendChild( child ) );
			list.appendChild( frag );

			// Apply select value + disable state
			list.querySelectorAll( '.folder-config-item' ).forEach( li => {
				const select = li.querySelector( '.link-template-select' );
				if ( select ) {
					// Find the corresponding item by matching the title currently in the li
					const titleInLi = li.querySelector( 'input[data-key="title"]' )?.value || '';
					const found = items.find( it => (it.title || '') === titleInLi );
					const val = found?.linkTemplate || '';
					if ( val ) select.value = val;
					select.disabled = linkTemplates.length === 0;
				}
			} );

			renumberFolders();
		}

		function addFolderItem( item = {} ) {
			const tmp = document.createElement( 'div' );
			const nextIndex = list.querySelectorAll( '.folder-config-item' ).length + 1;
			tmp.innerHTML = folderItemHtml( item, nextIndex );
			const li = tmp.firstElementChild;
			list.appendChild( li );
			const select = li.querySelector( '.link-template-select' );
			if ( select ) {
				select.disabled = linkTemplates.length === 0;
				if ( item.linkTemplate ) select.value = item.linkTemplate;
			}
			renumberFolders();
			markDirty();
		}

		// updates

		function markDirty() {
			dirty = true;
			debounceUpdate();
		}

		function debounceUpdate() {
			if ( debounceTimer ) clearTimeout( debounceTimer );
			debounceTimer = setTimeout( updateInput, 120 );
		}

		function updateInput() {
			debounceTimer = null;
			if ( !dirty ) return;
			jsonInput.value = JSON.stringify( serializeList(), null, 2 );
			dirty = false;
		}

		function serializeList() {
			const items = [];
			list.querySelectorAll( '.folder-config-item' ).forEach( li => {
				const get = ( sel ) => li.querySelector( sel );

				const title = get( 'input[data-key="title"]' )?.value.trim() || '';
				const href = get( 'input[data-key="href"]' )?.value.trim() || '';
				const dir = get( 'input[data-key="dir"]' )?.value.trim() || '';
				const excludeStr = get( 'input[data-key="excludeList"]' )?.value || '';
				const match = get( 'input[data-key="match"]' )?.value || '';
				const replace = get( 'input[data-key="replace"]' )?.value || '';
				const linkTemplate = li.querySelector( '.link-template-select' )?.value || '';
				const disableLinks = !!li.querySelector( '.disable-links' )?.checked;
				const requireVhost = !!li.querySelector( '.require-vhost' )?.checked;

				// Collect specialCases from rows and convert back to an object map
				const scRows = [];
				li.querySelectorAll( '.special-case' ).forEach( row => {
					const m = row.querySelector( '.sc-match' )?.value || '';
					const r = row.querySelector( '.sc-replace' )?.value || '';
					if ( m.length || r.length ) scRows.push( {match: m, replace: r} );
				} );
				const specialCases = rowsToMap( scRows );

				const excludeList = excludeStr.split( ',' ).map( s => s.trim() ).filter( Boolean );

				const record = {
					title,
					href,
					dir,
					excludeList,
					urlRules: {match, replace},
					linkTemplate,
					disableLinks,
					requireVhost,
					specialCases
				};

				// Skip fully empty rows
				const hasValue =
					title ||
					href ||
					dir ||
					excludeList.length ||
					match ||
					replace ||
					linkTemplate ||
					disableLinks ||
					requireVhost ||
					Object.keys( specialCases ).length;
				if ( hasValue ) items.push( record );
			} );
			return items;
		}

		// delegated events

		list.addEventListener( 'input', ( e ) => {
			if ( e.target.matches( 'input[type="text"], select' ) ) {
				markDirty();
			}
		} );
		list.addEventListener( 'change', ( e ) => {
			if ( e.target.matches( 'select, input[type="checkbox"]' ) ) {
				markDirty();
			}
		} );

		list.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '.add-special' ) ) {
				const wrap = e.target.closest( '.folder-config-item' )?.querySelector( '.special-cases' );
				if ( wrap ) {
					wrap.insertAdjacentHTML( 'beforeend', specialCaseRowHtml() );
					markDirty();
				}
			}
			if ( e.target.closest( '.remove-special' ) ) {
				const row = e.target.closest( '.special-case' );
				if ( row ) {
					row.remove();
					markDirty();
				}
			}
			if ( e.target.closest( '.remove-folder-column' ) ) {
				const li = e.target.closest( '.folder-config-item' );
				if ( li ) {
					li.remove();
					renumberFolders();
					markDirty();
				}
			}
		} );

		list.addEventListener( 'sorted', () => {
			renumberFolders();
			dirty = true;
			updateInput(); // immediate update after reorder
		} );

		// init

		Promise.all( [
			fetch( `${ window.BASE_URL }utils/read_config.php?file=folders`, {cache: 'no-store'} ).then( r => r.json() ).catch( () => [] ),
			fetchLinkTemplates()
		] ).then( ( [ data ] ) => {
			enableDragSort( '#folders-config-list' );
			if ( Array.isArray( data ) && data.length ) {
				appendFolderItems( data );
			}
			if ( list.children.length === 0 ) addFolderItem( {} );
			dirty = true;
			updateInput();
		} );

		addBtn.addEventListener( 'click', () => {
			addFolderItem( {} );
		} );
	} );
}
