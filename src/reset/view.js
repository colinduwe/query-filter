import { store, withSyncEvent, getContext, getElement } from '@wordpress/interactivity';
import { stripAppendedFromRegion } from '../utils/strip-appended.js';

// Register the store
const { state } = store( 'query-filter', {
	actions: {
		// `event.preventDefault()` requires synchronous event access.
		preventNavigation: withSyncEvent( ( event ) => {
			console.log( event );
			//event.preventDefault();
		} ),
		*navigateReset( e ) {
			stripAppendedFromRegion( getElement(), getContext() );
			const { queryId } = getContext();
			const isInherited = queryId === null || queryId === undefined;

			// Set search value to empty string
			state.searchValue = '';

			// Get the current URL
			const currentURL = new URL( window.location.href );

    		// Remove only relevant params.
			[ ...currentURL.searchParams.keys() ].forEach( ( key ) => {
				if ( ! isInherited ) {
					// Scoped (non-inherited) query: query-{id}-{taxonomy} and its page param.
					if (
						key === `query-${ queryId }-page` ||
						key.startsWith( `query-${ queryId }-` )
					) {
						currentURL.searchParams.delete( key );
					}
				} else {
					// Inherited (global) query: query-{taxonomy} (no numeric id), plus global pagination.
					if (
						/^query-[a-z0-9_-]+$/i.test( key ) &&
						!/^query-\d+-/.test( key )
					) {
						currentURL.searchParams.delete( key );
					}

					if ( key === 'page' || key === 'paged' || key === 's' ) {
						currentURL.searchParams.delete( key );
					}
				}
			} );

			// For inherited queries also strip /page/{n} from pretty permalinks.
			if ( isInherited ) {
				currentURL.pathname = currentURL.pathname.replace( /\/page\/\d+\/?$/i, '/' );
			}

			// Navigate to the updated URL
			const { actions } = yield import(
				'@wordpress/interactivity-router'
			);

			yield actions.navigate( currentURL.toString() );
		},
	},
} );
