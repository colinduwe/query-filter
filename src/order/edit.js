import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

const INSIGHTS_OPTIONS = [
	{ slug: 'newest', labelKey: 'labelNewest', defaultLabel: __( 'Newest', 'query-filter' ) },
	{ slug: 'oldest', labelKey: 'labelOldest', defaultLabel: __( 'Oldest', 'query-filter' ) },
	{
		slug: 'most-popular',
		labelKey: 'labelPopular',
		defaultLabel: __( 'Most popular', 'query-filter' ),
	},
];

const CUSTOM_EXTRA_OPTIONS = [
	{ slug: 'alphabetical-a-z', labelKey: 'labelTitleAsc', defaultLabel: __( 'Alphabetical A-Z', 'query-filter' ) },
	{ slug: 'alphabetical-z-a', labelKey: 'labelTitleDesc', defaultLabel: __( 'Alphabetical Z-A', 'query-filter' ) },
	{ slug: 'random', labelKey: 'labelRandom', defaultLabel: __( 'Random', 'query-filter' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		sortPreset,
		orderOptions,
		defaultOption,
		label,
		showLabel,
		labelNewest,
		labelOldest,
		labelPopular,
		labelTitleAsc,
		labelTitleDesc,
		labelRandom,
	} = attributes;

	const isInsights = 'insights' === ( sortPreset ?? 'insights' );

	const getOptionLabel = ( slug, fallback ) => {
		const labels = {
			newest: labelNewest,
			oldest: labelOldest,
			'most-popular': labelPopular,
			'alphabetical-a-z': labelTitleAsc,
			'alphabetical-z-a': labelTitleDesc,
			random: labelRandom,
		};
		return labels[ slug ] || fallback;
	};

	const optionsForPreview = isInsights
		? INSIGHTS_OPTIONS.map( ( item ) => ( {
				slug: item.slug,
				label: getOptionLabel( item.slug, item.defaultLabel ),
		  } ) )
		: orderOptions.map( ( option ) => ( {
				slug: option.slug,
				label: getOptionLabel( option.slug, option.label ),
		  } ) );

	const orderOptionChoices = optionsForPreview.map( ( option ) => ( {
		label: option.label,
		value: option.slug,
	} ) );

	const labelFields = isInsights
		? INSIGHTS_OPTIONS
		: [ ...INSIGHTS_OPTIONS, ...CUSTOM_EXTRA_OPTIONS ];

	return (
		<div { ...useBlockProps( { className: 'wp-block-query-filter wp-block-query-filter-order' } ) }>
			<InspectorControls>
				<PanelBody title={ __( 'Sort settings', 'query-filter' ) }>
					<SelectControl
						label={ __( 'Sort options', 'query-filter' ) }
						value={ sortPreset ?? 'insights' }
						options={ [
							{
								label: __( 'Insights (newest, oldest, most popular)', 'query-filter' ),
								value: 'insights',
							},
							{
								label: __( 'Custom (use order options below)', 'query-filter' ),
								value: 'custom',
							},
						] }
						onChange={ ( value ) => setAttributes( { sortPreset: value } ) }
						help={
							isInsights
								? __(
										'Most popular requires the WordPress Popular Posts plugin.',
										'query-filter'
								  )
								: undefined
						}
					/>

					<SelectControl
						label={ __( 'Default sort', 'query-filter' ) }
						value={ defaultOption }
						options={ orderOptionChoices }
						onChange={ ( value ) => setAttributes( { defaultOption: value } ) }
					/>

					<TextControl
						label={ __( 'Label', 'query-filter' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
						placeholder={ __( 'Sort by', 'query-filter' ) }
					/>

					<ToggleControl
						label={ __( 'Show label', 'query-filter' ) }
						checked={ showLabel }
						onChange={ ( value ) => setAttributes( { showLabel: value } ) }
					/>

					{ labelFields.map( ( item ) => (
						<TextControl
							key={ item.slug }
							label={ item.defaultLabel }
							value={ attributes[ item.labelKey ] }
							onChange={ ( value ) =>
								setAttributes( { [ item.labelKey ]: value } )
							}
						/>
					) ) }
				</PanelBody>
			</InspectorControls>

			<>
				{ showLabel && (
					<label className="wp-block-query-filter-order__label wp-block-query-filter__label">
						{ label || __( 'Sort by', 'query-filter' ) }
					</label>
				) }
				<select className="query-filter__select" defaultValue={ defaultOption }>
					{ optionsForPreview.map( ( option ) => (
						<option key={ option.slug } value={ option.slug }>
							{ option.label }
						</option>
					) ) }
				</select>
			</>
		</div>
	);
}
