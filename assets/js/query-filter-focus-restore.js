/**
 * Preserve focus on query-loop search inputs across Interactivity API navigations
 * and after GSAP ScrollTrigger refresh (theme dispatches `query-filter-restore-focus`).
 *
 * Intent lives on document.documentElement so it survives router region replacement.
 * The active field is scoped with `data-wp-router-region` so we never match another
 * control with the same `name` (e.g. header search).
 *
 * @package query-filter
 */

( function () {
  'use strict';

  const RESTORE_FLAG = 'data-plative-query-search-restore';
  const NAME_ATTR = 'data-plative-query-search-input-name';
  const REGION_ATTR = 'data-plative-query-search-router-region';

  function clearRestoreIntent() {
    document.documentElement.removeAttribute( RESTORE_FLAG );
    document.documentElement.removeAttribute( NAME_ATTR );
    document.documentElement.removeAttribute( REGION_ATTR );
  }

  function shouldRestore() {
    return document.documentElement.getAttribute( RESTORE_FLAG ) === '1';
  }

  function isQueryFilterSearchInput( el ) {
    return (
      el &&
      el.nodeName === 'INPUT' &&
      el.classList &&
      el.classList.contains( 'wp-block-search__input' ) &&
      el.closest( '[data-wp-interactive="query-filter"]' )
    );
  }

  /**
   * When the router removes the focused search field, focus often lands on body/html.
   * Do not treat that as the user leaving the field.
   */
  function isIntentionalFocusElsewhere( el ) {
    if ( ! el || el === document.body || el === document.documentElement ) {
      return false;
    }
    if ( isQueryFilterSearchInput( el ) ) {
      return false;
    }
    if ( typeof el.matches !== 'function' ) {
      return false;
    }
    return el.matches(
      'a[href], button:not( [disabled] ), input, select, textarea, [tabindex]:not( [tabindex="-1"] )'
    );
  }

  /**
   * Resolve the archive search field. `region` + `name` use CSS.escape for safe attribute selectors.
   */
  function getSearchInput() {
    const name = document.documentElement.getAttribute( NAME_ATTR );
    const region = document.documentElement.getAttribute( REGION_ATTR );

    try {
      if ( name ) {
        if ( region ) {
          const scoped = document.querySelector(
            '[data-wp-router-region="' +
              CSS.escape( region ) +
              '"] input.wp-block-search__input[name="' +
              CSS.escape( name ) +
              '"]'
          );
          if ( scoped ) {
            return scoped;
          }
        }
        return document.querySelector(
          '[data-wp-interactive="query-filter"] input.wp-block-search__input[name="' +
            CSS.escape( name ) +
            '"]'
        );
      }
      if ( region ) {
        return document.querySelector(
          '[data-wp-router-region="' +
            CSS.escape( region ) +
            '"] input.wp-block-search__input'
        );
      }
      return document.querySelector(
        '[data-wp-interactive="query-filter"] input.wp-block-search__input'
      );
    } catch ( e ) {
      return null;
    }
  }

  function restoreFocusIfNeeded() {
    if ( ! shouldRestore() ) {
      return;
    }
    const input = getSearchInput();
    if ( ! input || ! input.isConnected ) {
      return;
    }
    input.focus( { preventScroll: true } );
    const len = input.value.length;
    if ( typeof input.setSelectionRange === 'function' ) {
      input.setSelectionRange( len, len );
    }
  }

  /**
   * Defer until after layout so the router’s new nodes exist and IAPI can finish patching.
   */
  function scheduleRestore() {
    requestAnimationFrame( function () {
      requestAnimationFrame( function () {
        queueMicrotask( restoreFocusIfNeeded );
      } );
    } );
  }

  document.addEventListener(
    'focusin',
    function ( e ) {
      const el = e.target;
      if ( isQueryFilterSearchInput( el ) ) {
        document.documentElement.setAttribute( RESTORE_FLAG, '1' );
        if ( el.name ) {
          document.documentElement.setAttribute( NAME_ATTR, el.name );
        } else {
          document.documentElement.removeAttribute( NAME_ATTR );
        }
        const regionEl = el.closest( '[data-wp-router-region]' );
        const region = regionEl
          ? regionEl.getAttribute( 'data-wp-router-region' )
          : null;
        if ( region ) {
          document.documentElement.setAttribute( REGION_ATTR, region );
        } else {
          document.documentElement.removeAttribute( REGION_ATTR );
        }
        return;
      }
      if ( shouldRestore() && isIntentionalFocusElsewhere( el ) ) {
        clearRestoreIntent();
      }
    },
    true
  );

  window.addEventListener( 'query-filter-restore-focus', scheduleRestore );
} )();
