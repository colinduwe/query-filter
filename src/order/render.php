<?php
/**
 * Render the Order Filter block.
 *
 * @package query-filter
 */

use function HM\Query_Loop_Filter\get_order_options_for_block;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wp-block-query-filter wp-block-query-filter-order',
	)
);

$order_options  = get_order_options_for_block( $attributes );
$default_option = $attributes['defaultOption'] ?? 'newest';
$label          = $attributes['label'] ?? '';
$show_label     = $attributes['showLabel'] ?? true;

if ( empty( $order_options ) ) {
	return '';
}

// Generate a unique ID for this filter.
$filter_id = 'query-order-filter-' . uniqid();

// Create parameter name based on query ID.
if ( $block->context['query']['inherit'] ) {
	$param_name       = 'query-orderby';
	$order_param_name = 'query-order';
} else {
	$query_id         = $block->context['queryId'] ?? 0;
	$param_name       = sprintf( 'query-%d-orderby', $query_id );
	$order_param_name = sprintf( 'query-%d-order', $query_id );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_orderby = isset( $_GET[ $param_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_order = isset( $_GET[ $order_param_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $order_param_name ] ) ) : '';

// If we have orderby/order in URL, find the matching option.
$active_option = '';
if ( $current_orderby ) {
	foreach ( $order_options as $option ) {
		if ( ( $option['value']['orderby'] ?? '' ) === $current_orderby ) {
			if ( isset( $option['value']['order'] ) && '' !== $option['value']['order'] && $current_order ) {
				if ( $option['value']['order'] === $current_order ) {
					$active_option = $option['slug'];
					break;
				}
			} elseif ( empty( $option['value']['order'] ) && empty( $current_order ) ) {
				$active_option = $option['slug'];
				break;
			}
		}
	}
}

if ( empty( $active_option ) ) {
	$active_option = $default_option;
}

$query_id = empty( $block->context['query']['inherit'] )
	? ( $block->context['queryId'] ?? 0 )
	: null;

?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="query-filter"
	data-wp-context='<?php echo esc_attr( wp_json_encode( [ 'queryId' => $query_id ] ) ); ?>'>
	<label class="query-filter__label<?php echo $show_label ? '' : ' screen-reader-text'; ?>" for="<?php echo esc_attr( $filter_id ); ?>">
		<?php echo esc_html( $label ? $label : __( 'Sort by', 'query-filter' ) ); ?>
	</label>

	<select
		id="<?php echo esc_attr( $filter_id ); ?>"
		class="query-filter__select"
		name="<?php echo esc_attr( $param_name ); ?>"
		data-wp-on--change="actions.navigateOrder"
	>
		<?php foreach ( $order_options as $option ) : ?>
			<?php
			$value       = $option['slug'];
			$opt_orderby = $option['value']['orderby'] ?? '';
			$opt_order   = $option['value']['order'] ?? '';

			$display_label = $option['label'];
			switch ( $value ) {
				case 'newest':
					if ( ! empty( $attributes['labelNewest'] ) ) {
						$display_label = $attributes['labelNewest'];
					}
					break;
				case 'oldest':
					if ( ! empty( $attributes['labelOldest'] ) ) {
						$display_label = $attributes['labelOldest'];
					}
					break;
				case 'most-popular':
					if ( ! empty( $attributes['labelPopular'] ) ) {
						$display_label = $attributes['labelPopular'];
					}
					break;
				case 'alphabetical-a-z':
					if ( ! empty( $attributes['labelTitleAsc'] ) ) {
						$display_label = $attributes['labelTitleAsc'];
					}
					break;
				case 'alphabetical-z-a':
					if ( ! empty( $attributes['labelTitleDesc'] ) ) {
						$display_label = $attributes['labelTitleDesc'];
					}
					break;
				case 'random':
					if ( ! empty( $attributes['labelRandom'] ) ) {
						$display_label = $attributes['labelRandom'];
					}
					break;
			}

			$is_selected = false;
			if ( $current_orderby === $opt_orderby ) {
				if ( ! empty( $opt_order ) ) {
					$is_selected = ( $current_order === $opt_order );
				} else {
					$is_selected = empty( $current_order );
				}
			}
			if ( empty( $current_orderby ) && $value === $active_option ) {
				$is_selected = true;
			}
			?>
			<option value="<?php echo esc_attr( $value ); ?>" data-orderby="<?php echo esc_attr( $opt_orderby ); ?>" data-order="<?php echo esc_attr( $opt_order ); ?>" <?php selected( $is_selected ); ?>><?php echo esc_html( $display_label ); ?></option>
		<?php endforeach; ?>
	</select>
</div>
