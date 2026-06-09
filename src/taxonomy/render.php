<?php
if ( empty( $attributes['taxonomy'] ) ) {
	return;
}

$id = 'query-filter-' . wp_generate_uuid4();

$taxonomy = get_taxonomy( $attributes['taxonomy'] );

if ( empty( $block->context['query']['inherit'] ) ) {
	$query_id = $block->context['queryId'] ?? 0;
	$query_var = sprintf( 'query-%d-%s', $query_id, $attributes['taxonomy'] );
	$page_var = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
	$base_url = remove_query_arg( [ $query_var, $page_var ] );
} else {
	$query_var = sprintf( 'query-%s', $attributes['taxonomy'] );
	$page_var = 'page';
	$base_url = str_replace( '/page/' . get_query_var( 'paged' ), '', remove_query_arg( [ $query_var, $page_var ] ) );
}

$context = array(
    'queryId' => $query_id ?? null,
);

$terms = get_terms( [
	'hide_empty' => true,
	'taxonomy' => $attributes['taxonomy'],
	'number' => 100,
] );

if ( is_wp_error( $terms ) || empty( $terms ) ) {
	return;
}

// Seed the shared selections map for this filter's param from the request so
// data-wp-bind--checked="state.isChecked" reflects active filters on initial load.
// Self-contained: this no longer relies on a wrapping dropdown block to seed state.
$active_slugs = array_values(
	array_filter(
		array_map( 'sanitize_title', explode( ',', wp_unslash( $_GET[ $query_var ] ?? '' ) ) )
	)
);
if ( ! empty( $active_slugs ) ) {
	wp_interactivity_state(
		'query-filter',
		array(
			'selections' => array( $query_var => $active_slugs ),
		)
	);
}
?>

<div
	<?php echo get_block_wrapper_attributes( [ 'class' => 'wp-block-query-filter' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="query-filter"
	<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	>
	<?php if ( $attributes['useCheckboxes'] ) : ?>
		<fieldset class="wp-block-query-filter__checkboxes">
			<legend class="wp-block-query-filter__legend<?php echo $attributes['showLabel'] ? '' : ' screen-reader-text' ?>">
				<?php echo esc_html( $attributes['label'] ?? $taxonomy->label ); ?>
			</legend>
			<?php foreach ( $terms as $term ) :
				$checked = in_array( $term->slug, explode( ',', wp_unslash( $_GET[ $query_var ] ?? '' ) ), true );
				$checkbox_id = $id . '-' . $term->slug;
				$term_context = array(
					'param' => $query_var,
					'slug'  => $term->slug,
				);
				?>
				<span class="wp-block-query-filter__checkboxes-wrapper"
					<?php echo wp_interactivity_data_wp_context( $term_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<input
						type="checkbox"
						name="<?php echo esc_attr( $query_var ); ?>"
						value="<?php echo esc_attr( $term->slug ); ?>"
						id="<?php echo esc_attr( $checkbox_id ); ?>"
						<?php checked( $checked ); ?>
						data-wp-bind--checked="state.isChecked"
						data-wp-on--change="actions.navigateCheckboxes"
					/>
					<label for="<?php echo esc_attr( $checkbox_id ); ?>"><?php echo esc_html( $term->name ); ?></label>
				</span>
			<?php endforeach; ?>
		</fieldset>
	<?php else : ?>
		<label class="wp-block-query-filter-post-type__label wp-block-query-filter__label<?php echo $attributes['showLabel'] ? '' : ' screen-reader-text' ?>" for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $attributes['label'] ?? $taxonomy->label ); ?>
		</label>
		<select class="wp-block-query-filter-post-type__select wp-block-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.navigate">
			<option value="<?php echo esc_attr( $base_url ) ?>"><?php echo esc_html( $attributes['emptyLabel'] ?: __( 'All', 'query-filter' ) ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( add_query_arg( [ $query_var => $term->slug, $page_var => false ], $base_url ) ) ?>" <?php selected( $term->slug, wp_unslash( $_GET[ $query_var ] ?? '' ) ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php endif; ?>
</div>
