import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'query-filter', {
	actions: {
		*navigateReset() {
			const { queryId } = getContext();
			const isInherited = queryId === null || queryId === undefined;

			state.searchValue = '';

			// Optimistic clear — chips and badge clear immediately.
			state.selections = {};

			const currentURL = new URL( window.location.href );

			[ ...currentURL.searchParams.keys() ].forEach( ( key ) => {
				if ( ! isInherited ) {
					if (
						key === `query-${ queryId }-page` ||
						key.startsWith( `query-${ queryId }-` )
					) {
						currentURL.searchParams.delete( key );
					}
				} else {
					if (
						/^query-[a-z0-9_-]+$/i.test( key ) &&
						! /^query-\d+-/.test( key )
					) {
						currentURL.searchParams.delete( key );
					}

					if ( key === 'page' || key === 'paged' || key === 's' ) {
						currentURL.searchParams.delete( key );
					}
				}
			} );

			if ( isInherited ) {
				currentURL.pathname = currentURL.pathname.replace( /\/page\/\d+\/?$/i, '/' );
			}

			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( currentURL.toString() );
		},
	},
} );
