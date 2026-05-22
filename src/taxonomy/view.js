import { store, getElement, getContext } from '@wordpress/interactivity';

const updateURL = async ( action, value, name, queryId ) => {
	const url = new URL( action );
	if ( value || name === 's' ) {
		url.searchParams.set( name, value );
	} else {
		url.searchParams.delete( name );
	}

	const isInherited = queryId === null || queryId === undefined;

	if ( isInherited ) {
		url.searchParams.delete( 'paged' );
		url.searchParams.delete( 'page' );
		url.pathname = url.pathname.replace( /\/page\/\d+\/?$/i, '/' );
	} else {
		url.searchParams.delete( `query-${ queryId }-page` );
	}

	const { actions } = await import( '@wordpress/interactivity-router' );
	await actions.navigate( url.toString() );
};

const { state } = store( 'query-filter', {
	state: {
		/** Shared selections map: param → string[]. Seeded by PHP, updated optimistically. */
		selections: {},
		/** True when at least one taxonomy term is selected. Drives clear-filters visibility. */
		get hasSelections() {
			return Object.values( state.selections || {} ).some(
				( slugs ) => ( slugs || [] ).length > 0
			);
		},
		/** Reactive checked state for each checkbox, bound via data-wp-bind--checked. */
		get isChecked() {
			const { param, slug } = getContext();
			return ( state.selections[ param ] || [] ).includes( slug );
		},
	},
	actions: {
		*navigate( e ) {
			e.preventDefault();

			const { actions } = yield import(
				'@wordpress/interactivity-router'
			);
			yield actions.navigate( e.target.value );
		},
		*navigateCheckboxes( e ) {
			e.preventDefault();
			const { ref } = getElement();
			const name = ref.name;

			const { queryId } = getContext();
			const isInherited = queryId === null || queryId === undefined;

			const currentURL = new URL( window.location.href );

			if ( ! isInherited ) {
				currentURL.searchParams.delete( `query-${ queryId }-page` );
			} else {
				currentURL.pathname = currentURL.pathname.replace( /\/page\/\d+\/?$/i, '/' );
			}

			const container = ref.closest( '.wp-block-query-filter__checkboxes' );
			const checked = container.querySelectorAll( `input[name="${ name }"]:checked` );

			const values = [];
			checked.forEach( ( checkbox ) => values.push( checkbox.value ) );

			// Optimistic update — reactive consumers (badge, chips) respond immediately.
			const next = { ...state.selections };
			if ( values.length ) {
				next[ name ] = values;
			} else {
				delete next[ name ];
			}
			state.selections = next;

			const value = values.join( ',' );
			if ( value ) {
				currentURL.searchParams.set( name, value );
			} else {
				currentURL.searchParams.delete( name );
			}

			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( currentURL.toString() );
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

			if ( value === state.searchValue ) return;

			state.searchValue = value;

			const { queryId } = getContext();
			yield updateURL( action, value, name, queryId );
		},
	},
} );
