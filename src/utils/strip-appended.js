/**
 * Remove Load More–appended nodes from the router region before navigate().
 * Keeps the region DOM in sync with the router’s VDOM so reconciliation doesn’t
 * leave those nodes behind. Works with any region structure.
 *
 * @param {{ ref?: Element }} element - From getElement(); ref used to find region.
 * @param {{ queryId?: number | null }} context - From getContext(); fallback to find region by id.
 */
export function stripAppendedFromRegion( element, context ) {
	const region =
		element?.ref?.closest?.( '[data-wp-router-region]' ) ||
		( context != null &&
			document.querySelector(
				`[data-wp-router-region="query-${ context.queryId ?? 0 }"]`
			) );
	if ( region ) {
		region.querySelectorAll( '[data-qllm-appended]' ).forEach( ( el ) => el.remove() );
	}
}
