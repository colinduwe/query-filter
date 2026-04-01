import { store, getElement, getContext } from '@wordpress/interactivity';

const { state } = store( 'query-filter', {
	state: {
		isLoading: false,
	},
	actions: {
		*navigate( e ) {
			e.preventDefault();

			state.isLoading = true;
			try {
				const { actions } = yield import(
					'@wordpress/interactivity-router'
				);
				yield actions.navigate( e.target.value );
			} finally {
				state.isLoading = false;
			}
		},
		*navigateCheckboxes( e ) {
			e.preventDefault();
			const { ref } = getElement();
			let name, values = [];
			name = ref.name;

			const { queryId } = getContext();
			const isInherited = queryId === null || queryId === undefined;

			// Get the current URL and preserve existing query parameters
			const currentURL = new URL( window.location.href );

			// Scoped (non-inherited) query: query-{id}-{taxonomy} and its page param.
			if ( ! isInherited ) {
				currentURL.searchParams.delete( `query-${ queryId }-page` );
			} else {
				// For inherited queries also strip /page/{n} from pretty permalinks.
				currentURL.pathname = currentURL.pathname.replace( /\/page\/\d+\/?$/i, '/' );
			}

			// Handle checkboxes directly
			const container = ref.closest( '.wp-block-query-filter__checkboxes' );
			const checkboxes = container.querySelectorAll(
				`input[name="${name}"]:checked`
			);

			// Collect all selected values
			checkboxes.forEach( ( checkbox ) => {
				values.push( checkbox.value );
			} );

			// Create a comma-separated string of values
			const value = values.join( ',' );

			// Update the URL with the new value for checkboxes
			if ( value ) {
				currentURL.searchParams.set( name, value );
			} else {
				currentURL.searchParams.delete( name );
			}

			state.isLoading = true;
			try {
				const { actions } = yield import(
					'@wordpress/interactivity-router'
				);
				yield actions.navigate( currentURL.toString() );
			} finally {
				state.isLoading = false;
			}
		},
		*search( e ) {
			e.preventDefault();
			const { ref } = getElement();
			let action, name, value;
			if ( ref.tagName === 'FORM' ) {
				const input = ref.querySelector( 'input[type="search"]' );
				action = ref.action;
				name = input.name;
				value = input.value;
			} else {
				action = ref.closest( 'form' ).action;
				name = ref.name;
				value = ref.value;
			}

			// Don't navigate if the search didn't really change.
			if ( value === state.searchValue ) return;

			state.searchValue = value;

			const { queryId } = getContext();

			yield updateURL( action, value, name, queryId );
		},
	},
} );

/**
 * Client-side navigation for search / URL updates (shared with search action).
 */
async function updateURL( action, value, name, queryId ) {
	const url = new URL( action );
	if ( value || name === 's' ) {
		url.searchParams.set( name, value );
	} else {
		url.searchParams.delete( name );
	}

	const isInherited = queryId === null || queryId === undefined;

	// Remove only this query's pagination.
	if ( isInherited ) {
		// Global search: remove global paged/page and pretty permalink segment.
		url.searchParams.delete( 'paged' );
		url.searchParams.delete( 'page' );
		url.pathname = url.pathname.replace( /\/page\/\d+\/?$/i, '/' );
	} else {
		url.searchParams.delete( `query-${ queryId }-page` );
	}

	state.isLoading = true;
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		await actions.navigate( url.toString() );

		// `scroll-pin.js` dispatches `query-filter-restore-focus` after GSAP refresh when parallax runs.
		// Without that stack, dispatch here so DOM-backed focus restore still runs.
		if (
			typeof document !== 'undefined' &&
			! document.querySelector( '.parallax-scroll-container' )
		) {
			window.dispatchEvent( new Event( 'query-filter-restore-focus' ) );
		}
	} finally {
		state.isLoading = false;
	}
}
