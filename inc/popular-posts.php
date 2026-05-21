<?php
/**
 * WordPress Popular Posts integration for query order.
 *
 * @package query-filter
 */

namespace HM\Query_Loop_Filter;

use WP_Query;

/**
 * Orderby value used in URL/query vars for popular sorting.
 */
const ORDERBY_POPULAR = 'wpp';

/**
 * Whether WordPress Popular Posts is available.
 *
 * @return bool
 */
function is_wpp_available() : bool {
	return class_exists( '\WordPressPopularPosts\Query' );
}

/**
 * Default insights sort options (newest, oldest, most popular).
 *
 * @return array<int, array<string, mixed>>
 */
function get_insights_order_options() : array {
	$options = [
		[
			'label' => __( 'Newest', 'query-filter' ),
			'slug'  => 'newest',
			'value' => [
				'orderby' => 'date',
				'order'   => 'desc',
			],
		],
		[
			'label' => __( 'Oldest', 'query-filter' ),
			'slug'  => 'oldest',
			'value' => [
				'orderby' => 'date',
				'order'   => 'asc',
			],
		],
	];

	if ( is_wpp_available() ) {
		$options[] = [
			'label' => __( 'Most popular', 'query-filter' ),
			'slug'  => 'most-popular',
			'value' => [
				'orderby' => ORDERBY_POPULAR,
				'order'   => '',
			],
		];
	}

	return $options;
}

/**
 * Resolve sort options for the order block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return array<int, array<string, mixed>>
 */
function get_order_options_for_block( array $attributes ) : array {
	$preset = $attributes['sortPreset'] ?? 'insights';

	if ( 'insights' === $preset ) {
		return get_insights_order_options();
	}

	$options = $attributes['orderOptions'] ?? get_insights_order_options();

	if ( ! is_wpp_available() ) {
		$options = array_values(
			array_filter(
				$options,
				static function ( array $option ) : bool {
					return ORDERBY_POPULAR !== ( $option['value']['orderby'] ?? '' );
				}
			)
		);
	}

	return $options;
}

/**
 * Apply popular-post ordering to a query when orderby is wpp.
 *
 * @param WP_Query $query Query instance.
 * @return void
 */
function apply_popular_posts_order( WP_Query $query ) : void {
	if ( ORDERBY_POPULAR !== $query->get( 'orderby' ) ) {
		return;
	}

	if ( ! is_wpp_available() ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
		return;
	}

	$post_type = $query->get( 'post_type', 'post' );
	if ( is_array( $post_type ) ) {
		$post_type = implode( ',', $post_type );
	}

	$limit = (int) apply_filters( 'query_filter_wpp_limit', 500, $query );

	$wpp_args = apply_filters(
		'query_filter_wpp_query_args',
		[
			'range'     => 'all',
			'order_by'  => 'views',
			'limit'     => $limit,
			'post_type' => $post_type,
		],
		$query
	);

	$wpp_tax = build_wpp_taxonomy_args_from_tax_query( $query->get( 'tax_query', [] ) );
	if ( ! empty( $wpp_tax ) ) {
		$wpp_args = array_merge( $wpp_args, $wpp_tax );
	}

	$wpp_query = new \WordPressPopularPosts\Query( $wpp_args );
	$popular   = $wpp_query->get_posts();

	$ids = array_map(
		static function ( $post ) : int {
			return (int) $post->id;
		},
		$popular
	);

	$search = $query->get( 's', '' );
	if ( '' !== $search && ! empty( $ids ) ) {
		$ids = filter_post_ids_by_search( $ids, $search, $post_type );
	}

	if ( empty( $ids ) ) {
		$ids = [ 0 ];
	}

	$query->set( 'post__in', $ids );
	$query->set( 'orderby', 'post__in' );
	$query->set( 'order', 'ASC' );
}

/**
 * Map WP_Query tax_query to WPP taxonomy / term_id arguments.
 *
 * @param array<int, mixed> $tax_query Tax query clauses.
 * @return array<string, string>
 */
function build_wpp_taxonomy_args_from_tax_query( array $tax_query ) : array {
	$taxonomies = [];
	$term_ids   = [];

	foreach ( $tax_query as $clause ) {
		if ( ! is_array( $clause ) || empty( $clause['taxonomy'] ) ) {
			continue;
		}

		$taxonomy = $clause['taxonomy'];
		$terms    = $clause['terms'] ?? [];
		$field    = $clause['field'] ?? 'term_id';

		if ( ! is_array( $terms ) ) {
			$terms = [ $terms ];
		}

		$resolved_ids = [];

		foreach ( $terms as $term ) {
			if ( 'slug' === $field ) {
				$term_obj = get_term_by( 'slug', $term, $taxonomy );
				if ( $term_obj && ! is_wp_error( $term_obj ) ) {
					$resolved_ids[] = (int) $term_obj->term_id;
				}
			} else {
				$resolved_ids[] = (int) $term;
			}
		}

		if ( empty( $resolved_ids ) ) {
			continue;
		}

		$taxonomies[] = $taxonomy;
		$term_ids[]   = implode( ',', $resolved_ids );
	}

	if ( empty( $taxonomies ) ) {
		return [];
	}

	return [
		'taxonomy' => implode( ';', $taxonomies ),
		'term_id'  => implode( ';', $term_ids ),
	];
}

/**
 * Restrict post IDs to those matching a search string.
 *
 * @param array<int, int> $ids       Post IDs in popularity order.
 * @param string          $search    Search string.
 * @param string          $post_type Post type(s), comma-separated.
 * @return array<int, int>
 */
function filter_post_ids_by_search( array $ids, string $search, string $post_type ) : array {
	$matched = get_posts(
		[
			'post__in'       => $ids,
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => $search,
			'orderby'        => 'post__in',
		]
	);

	if ( empty( $matched ) ) {
		return [];
	}

	$matched = array_map( 'intval', $matched );

	return array_values( array_intersect( $ids, $matched ) );
}
