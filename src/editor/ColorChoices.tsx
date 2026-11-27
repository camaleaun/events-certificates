/**
 * The color pickers of an element: the indicator and the label of a color (ColorItem, as the core's color row), and what its popover offers
 * (ColorChoices): the Theme and the Elements of the design, a custom color and Clear. The page's background uses ColorChoices too.
 */
import { Button, ColorIndicator, ColorPalette, Dropdown, __experimentalHStack as HStack } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { stockName } from './base';
import { color, colorGroups, colorIn, swatchName, type Design, type Mode } from './model';

/**
 * The colors to choose from, in a popover: the colors of the design are one group, Elements; for what is not an element (the page's
 * background) `theme` adds the Theme before them: a name is in one or the other, so a reference ("$base", "$background") says which. Then a
 * custom color, and Clear (back to `clearTo`).
 */
export function ColorChoices( { value, design, mode, onChange, clearTo = '', theme = false }: { value?: string; design: Design; mode: Mode; onChange: ( v: string ) => void; clearTo?: string; theme?: boolean } ) {
	const key = value?.startsWith( '$' ) ? value.slice( 1 ) : '';
	const hex = ( c: string ) => ( c === 'transparent' ? '#ffffff00' : c );
	type Item = { slug: string; name: string; color: string };
	const groups: Array< { name: 'theme' | 'elements'; items: Item[] } > = [
		...( theme && design.palette?.length ? [ { name: 'theme' as const, items: design.palette.map( ( s ) => ( { slug: s.slug, name: stockName( swatchName( s ) ), color: s.color } ) ) } ] : [] ),
		...colorGroups( design ).map( ( group ) => ( { name: group.name, items: group.keys.map( ( k ) => ( { slug: k, name: stockName( design.colors[ k ].name ), color: hex( colorIn( design, design.colors[ k ], mode ) ) } ) ) } ) ),
	];
	// The page's background (not an element): the same popover as a color of the Colors section (the preview with its name, the groups at the base
	// and Clear), with the Theme and the Elements as its two groups. A slug is in one or the other, so it says which was picked.
	if ( theme ) {
		const current = color( design, value, mode );
		return (
			<div className="evcert-colors__plain">
				<ColorPalette
					colors={ groups.map( ( group ) => ( { name: group.name === 'theme' ? __( 'Theme', 'events-certificates' ) : __( 'Elements', 'events-certificates' ), colors: group.items } ) ) }
					value={ current === 'none' ? undefined : current }
					enableAlpha
					clearable={ !! value && value !== clearTo }
					onChange={ ( picked, _group, slug ) => onChange( slug ? `$${ slug }` : picked || clearTo ) }
				/>
			</div>
		);
	}
	return (
		<div className="evcert-color-popover">
			{ groups.map( ( group ) => (
				<div key={ group.name }>
					<p className="evcert-color-popover__label">{ group.name === 'theme' ? __( 'Theme', 'events-certificates' ) : __( 'Elements', 'events-certificates' ) }</p>
					<ColorPalette
						colors={ group.items }
						value={ key && group.items.some( ( i ) => i.slug === key ) ? group.items.find( ( i ) => i.slug === key )?.color : undefined }
						disableCustomColors
						clearable={ false }
						// By position, not by value: two entries can share one (Background and On primary are both white).
						onChange={ ( picked, index ) => {
							const item = index === undefined ? undefined : group.items[ index ];
							onChange( item ? `$${ item.slug }` : picked || '' );
						} }
					/>
				</div>
			) ) }
			<p className="evcert-color-popover__label">{ __( 'Custom', 'events-certificates' ) }</p>
			<ColorPalette value={ value?.startsWith( '$' ) ? undefined : value } onChange={ ( c ) => onChange( c || clearTo ) } clearable={ false } />
			{ /* One Clear for any color, theme or custom: back to the default (none, or the text color). */ }
			<div className="evcert-color-popover__footer">
				<Button variant="tertiary" size="small" disabled={ ( value || '' ) === clearTo } accessibleWhenDisabled onClick={ () => onChange( clearTo ) }>
					{ __( 'Clear', 'events-certificates' ) }
				</Button>
			</div>
		</div>
	);
}

/** What a color value is called: the name of the color of the design or of the theme, "Default" for a built-in one, else the value. */
export function colorLabel( design: Design, value?: string ): string {
	const key = value?.startsWith( '$' ) ? value.slice( 1 ) : '';
	const variable = key ? design.colors[ key ] : null;
	const swatch = key && ! variable ? ( design.palette || [] ).find( ( s ) => s.slug === key ) : null;
	return variable ? stockName( variable.name ) : swatch ? stockName( swatchName( swatch ) ) : key === 'background' ? __( 'Transparent', 'events-certificates' ) : key ? __( 'Default', 'events-certificates' ) : value || __( 'None', 'events-certificates' );
}

/** A color item like the block editor's ColorGradientDropdown (indicator + name, the choices in a dropdown). */
export function ColorItem( { label, value, design, mode, onChange, clearTo = '', theme = true }: { label: string; value?: string; design: Design; mode: Mode; onChange: ( v: string ) => void; clearTo?: string; theme?: boolean } ) {
	const shown = color( design, value, mode );
	return (
		<Dropdown
			className="evcert-color-item"
			popoverProps={ { placement: 'left-start', offset: 36, shift: true, className: 'evcert-colors__popover-wrap' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				// As the core's color row: the indicator and the label, nothing at the end; the color's name is the tooltip and the accessible name.
				<Button
					__next40pxDefaultSize
					className={ `evcert-color-item__toggle${ isOpen ? ' is-open' : '' }` }
					onClick={ onToggle }
					aria-expanded={ isOpen }
					label={ sprintf( /* translators: 1: what the color is for (Text, Fill…), 2: the color's name. */ __( '%1$s: %2$s', 'events-certificates' ), label, colorLabel( design, value ) ) }
					showTooltip
				>
					<HStack justify="flex-start">
						<ColorIndicator colorValue={ shown === 'none' ? undefined : shown } />
						<span className="evcert-color-item__name">{ label }</span>
					</HStack>
				</Button>
			) }
			renderContent={ () => <ColorChoices value={ value } design={ design } mode={ mode } onChange={ onChange } clearTo={ clearTo } theme={ theme } /> }
		/>
	);
}
