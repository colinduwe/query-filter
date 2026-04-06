<?php
/**
 * Query filter main file.
 *
 * @package query-filter
 */

namespace HM\Query_Loop_Filter;

use WP_HTML_Tag_Processor;
use WP_Query;

/**
 * Connect namespace methods to hooks and filters.
 *
 * @return void
 */
function bootstrap() : void {
	// General hooks.
	add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\\filter_query_loop_block_query_vars', 10, 3 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\\pre_get_posts_transpose_query_vars' );
	add_filter( 'block_type_metadata', __NAMESPACE__ . '\\filter_block_type_metadata', 10 );
	add_action( 'init', __NAMESPACE__ . '\\register_blocks' );
	add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\action_wp_enqueue_scripts' );

	// Search.
	add_filter( 'render_block_core/search', __NAMESPACE__ . '\\render_block_search', 10, 3 );

	// Query.
	add_filter( 'render_block_core/query', __NAMESPACE__ . '\\render_block_query', 10, 3 );
}

/**
 * Fires when scripts and styles are enqueued.
 *
 * @TODO work out why this doesn't work but building interactivity via the blocks does.
 */
function action_wp_enqueue_scripts() : void {
	$asset = include ROOT_DIR . '/build/taxonomy/index.asset.php';
	wp_register_style(
		'query-filter-view',
		plugins_url( '/build/taxonomy/index.css', PLUGIN_FILE ),
		[],
		$asset['version']
	);
}

/**
 * Fires after WordPress has finished loading but before any headers are sent.
 *
 */
function register_blocks() : void {
	register_block_type( ROOT_DIR . '/build/taxonomy' );
	register_block_type( ROOT_DIR . '/build/post-type' );
	register_block_type( ROOT_DIR . '/build/reset' );
	register_block_type( ROOT_DIR . '/build/order' );
}

/**
 * Filters the arguments which will be passed to `WP_Query` for the Query Loop Block.
 *
 * @param array     $query Array containing parameters for <code>WP_Query</code> as parsed by the block context.
 * @param \WP_Block $block Block instance.
 * @param int       $page  Current query's page.
 * @return array Array containing parameters for <code>WP_Query</code> as parsed by the block context.
 */
function filter_query_loop_block_query_vars( array $query, \WP_Block $block, int $page ) : array {
	if ( isset( $block->context['queryId'] ) ) {
		$query['query_id'] = $block->context['queryId'];
	}

	return $query;
}

/**
 * Fires after the query variable object is created, but before the actual query is run.
 *
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 */
function pre_get_posts_transpose_query_vars( WP_Query $query ) : void {
	$query_id = $query->get( 'query_id', null );

	if ( ! $query->is_main_query() && is_null( $query_id ) ) {
		return;
	}

	$prefix = $query->is_main_query() ? 'query-' : "query-{$query_id}-";
	$tax_query = [];
	$valid_keys = [
		'post_type' => $query->is_search() ? 'any' : 'post',
		's'         => '',
		'orderby'   => 'date',
		'order'     => 'desc',
	];

	// Preserve valid params for later retrieval.
	foreach ( $valid_keys as $key => $default ) {
		$query->set(
			"query-filter-$key",
			$query->get( $key, $default )
		);
	}

	// Map get params to this query.
	foreach ( $_GET as $key => $value ) {
		if ( strpos( $key, $prefix ) === 0 ) {
			$key = str_replace( $prefix, '', $key );
			$value = sanitize_text_field( urldecode( wp_unslash( $value ) ) );

			// Handle taxonomies specifically.
			if ( get_taxonomy( $key ) ) {
				// Allow multiple values by using array format for taxonomies.
				$value = array_map(
					'sanitize_text_field',
					array_map(
						'urldecode',
						explode( ',', wp_unslash( $value ) ) // Split by commas
					)
				);
				$tax_query['relation'] = 'AND';
				$tax_query[] = [
					'taxonomy' => $key,
					'terms' => $value,
					'field' => 'slug',
				];
			} else {
				// Other options should map directly to query vars.
				$key = sanitize_key( $key );

				if ( ! in_array( $key, array_keys( $valid_keys ), true ) ) {
					continue;
				}

				$query->set(
					$key,
					$value
				);
			}
		}
	}

	if ( ! empty( $tax_query ) ) {
		$existing_query = $query->get( 'tax_query', [] );

		if ( ! empty( $existing_query ) ) {
			$tax_query = [
				'relation' => 'AND',
				[ $existing_query ],
				$tax_query,
			];
		}

		$query->set( 'tax_query', $tax_query );
	}
}

/**
 * Filters the settings determined from the block type metadata.
 *
 * @param array $metadata Metadata provided for registering a block type.
 * @return array Array of metadata for registering a block type.
 */
function filter_block_type_metadata( array $metadata ) : array {
	// Add query context to search block.
	if ( $metadata['name'] === 'core/search' ) {
		$metadata['usesContext'] = array_merge(
			$metadata['usesContext'] ?? [],
			[ 'queryId', 'query', 'enhancedPagination' ]
		);
	}

	return $metadata;
}

/**
 * Whether the Search block (inside a Query) should use Interactivity API navigation.
 *
 * When false, the form relies on a normal GET submit (no search-on-input, no router navigate).
 * If the parent Query provides `enhancedPagination` in block context, that value is used;
 * otherwise defaults to true for backward compatibility with older WordPress versions.
 *
 * @param \WP_Block $instance Search block instance.
 * @param array     $block    Parsed search block (for filters).
 * @return bool
 */
function should_use_client_navigation_for_query_search( \WP_Block $instance, array $block = [] ) : bool {
	$default = true;
	if ( array_key_exists( 'enhancedPagination', $instance->context ) ) {
		$default = ! empty( $instance->context['enhancedPagination'] );
	}

	/**
	 * Filters whether query-scoped Search uses client navigation (Interactivity API + router).
	 *
	 * @param bool      $use_client_nav Whether to use client navigation.
	 * @param \WP_Block $instance       Search block instance.
	 * @param array     $block          Parsed block array.
	 */
	return (bool) apply_filters( 'query_filter_search_client_navigation', $default, $instance, $block );
}

/**
 * Remove data-wp-* directives from Search markup so the form can submit natively.
 *
 * @param string $html Block HTML.
 * @return string
 */
function strip_interactivity_directives_from_search_markup( string $html ) : string {
	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag() ) {
		$tag = $processor->get_tag();
		if ( 'FORM' === $tag ) {
			foreach (
				[
					'data-wp-interactive',
					'data-wp-context',
					'data-wp-on--submit',
					'data-wp-on--keydown',
					'data-wp-on--focusout',
					'data-wp-class--wp-block-search__searchfield-hidden',
				] as $attr
			) {
				$processor->remove_attribute( $attr );
			}
		} elseif ( 'INPUT' === $tag ) {
			$class = (string) $processor->get_attribute( 'class' );
			if ( str_contains( $class, 'wp-block-search__input' ) ) {
				foreach (
					[
						'data-wp-bind--value',
						'data-wp-on--input',
						'data-wp-bind--aria-hidden',
						'data-wp-bind--tabindex',
					] as $attr
				) {
					$processor->remove_attribute( $attr );
				}
			}
		} elseif ( 'BUTTON' === $tag ) {
			foreach (
				[
					'data-wp-bind--aria-label',
					'data-wp-bind--aria-controls',
					'data-wp-bind--aria-expanded',
					'data-wp-bind--type',
					'data-wp-on--click',
				] as $attr
			) {
				$processor->remove_attribute( $attr );
			}
		}
	}

	return $processor->get_updated_html();
}

/**
 * Filters the content of a single block.
 *
 * @param string    $block_content The block content.
 * @param array     $block         The full block, including name and attributes.
 * @param \WP_Block $instance      The block instance.
 * @return string The block content.
 */
function render_block_search( string $block_content, array $block, \WP_Block $instance ) : string {
	if ( empty( $instance->context['query'] ) ) {
		return $block_content;
	}

	wp_enqueue_script_module( 'query-filter-taxonomy-view-script-module' );

	// Inherited queries use the main WP_Query. Use `query-s` (not raw `s`) so WordPress does not
	// treat the request as a global search (`is_search()` → search.php). pre_get_posts maps
	// `query-s` → query var `s` via the `query-` prefix rule for the main query.
	$query_var = empty( $instance->context['query']['inherit'] )
		? sprintf( 'query-%d-s', $instance->context['queryId'] ?? 0 )
		: 'query-s';

	// Add queryId to the context (null when inherited, numeric when scoped).
	$query_id = empty( $instance->context['query']['inherit'] )
		? ( $instance->context['queryId'] ?? 0 )
		: null;

	$action = str_replace( '/page/'. get_query_var( 'paged', 1 ), '', add_query_arg( [ $query_var => '' ] ) );

	// Note sanitize_text_field trims whitespace from start/end of string causing unexpected behaviour.
	$value = wp_unslash( $_GET[ $query_var ] ?? '' );
	$value = urldecode( $value );
	$value = wp_check_invalid_utf8( $value );
	$value = wp_pre_kses_less_than( $value );
	$value = strip_tags( $value );

	$use_client_navigation = should_use_client_navigation_for_query_search( $instance, $block );

	if ( $use_client_navigation ) {
		wp_interactivity_state(
			'query-filter',
			[
				'searchValue' => $value,
			]
		);
	}

	$block_content = $use_client_navigation
		? $block_content
		: strip_interactivity_directives_from_search_markup( $block_content );

	$processor = new WP_HTML_Tag_Processor( $block_content );
	$processor->next_tag( [ 'tag_name' => 'form' ] );
	$processor->set_attribute( 'action', $action );
	if ( ! $use_client_navigation ) {
		$processor->set_attribute( 'method', 'get' );
	}

	if ( $use_client_navigation ) {
		$processor->set_attribute( 'data-wp-interactive', 'query-filter' );
		$processor->set_attribute( 'data-wp-on--submit', 'actions.search' );
		$processor->set_attribute(
			'data-wp-context',
			wp_json_encode(
				[
					'queryId'            => $query_id,
					'searchValue'        => '',
					'clientNavigation'   => true,
				]
			)
		);
	}

	$processor->next_tag( [ 'tag_name' => 'input', 'class_name' => 'wp-block-search__input' ] );
	$processor->set_attribute( 'name', $query_var );
	$processor->set_attribute( 'inputmode', 'search' );
	$processor->set_attribute( 'value', $value );

	if ( $use_client_navigation ) {
		$processor->set_attribute( 'data-wp-bind--value', 'state.searchValue' );
		$processor->set_attribute( 'data-wp-on--input', 'actions.search' );
	}

	return (string) $processor->get_updated_html();
}

/**
 * Add data attributes to the query block to describe the block query.
 *
 * @param string    $block_content Default query content.
 * @param array     $block         Parsed block.
 * @return string
 */
function render_block_query( $block_content, $block ) {
	$block_content = new WP_HTML_Tag_Processor( $block_content );
	$block_content->next_tag();

	// Always allow region updates on interactivity, use standard core region naming.
	$block_content->set_attribute( 'data-wp-interactive', 'query-filter' );
	$block_content->set_attribute( 'data-wp-router-region', 'query-' . ( $block['attrs']['queryId'] ?? 0 ) );

	return (string) $block_content;
}
