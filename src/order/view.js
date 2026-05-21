import { store, getElement, getContext } from '@wordpress/interactivity';

const POPULAR_ORDERBY = 'wpp';

/**
 * Remove pagination params when sort changes (matches taxonomy filter behavior).
 *
 * @param {URL}    url
 * @param {number|null} queryId
 */
function clearPaginationParams( url, queryId ) {
	const isInherited = queryId === null || queryId === undefined;

	if ( isInherited ) {
		url.searchParams.delete( 'paged' );
		url.searchParams.delete( 'page' );
		url.pathname = url.pathname.replace( /\/page\/\d+\/?$/i, '/' );
	} else {
		url.searchParams.delete( `query-${ queryId }-page` );
	}
}

store( 'query-filter', {
	actions: {
		*navigateOrder( e ) {
			e.preventDefault();
			const { ref } = getElement();
			const { queryId } = getContext();

			const currentURL = new URL( window.location.href );
			const name = ref.name;

			if ( name ) {
				const optionEl = ref.options[ ref.selectedIndex ];
				const orderby = optionEl?.dataset?.orderby;
				const order = optionEl?.dataset?.order;

				if ( orderby ) {
					currentURL.searchParams.set( name, orderby );
					const orderParam = name.replace( 'orderby', 'order' );

					if ( order && POPULAR_ORDERBY !== orderby ) {
						currentURL.searchParams.set( orderParam, order );
					} else {
						currentURL.searchParams.delete( orderParam );
					}
				} else {
					currentURL.searchParams.delete( name );
					const orderParam = name.replace( 'orderby', 'order' );
					currentURL.searchParams.delete( orderParam );
				}
			}

			clearPaginationParams( currentURL, queryId );

			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( currentURL.toString() );
		},
	},
} );
