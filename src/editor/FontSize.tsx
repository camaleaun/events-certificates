/**
 * The font size of a text, built as WordPress's own FontSizePicker (a label and a small button that switches between the list of sizes and an open field with a slider),
 * because the native one cannot do what a certificate needs: its custom field has no step, so 12.5 pt is rounded to 13, and it sends whole numbers back.
 *
 *   - The list is in points (8 to 42), as if no theme.json made presets.
 *   - The open field takes pt, px, mm, cm or in, keeps what is typed up to eight decimals (as CSS does), steps by 0.1 and shows it with no zeros at the end.
 *   - The size is a number of points or a length with its unit ("4.4mm"), as a line height's is; the field shows the unit it is written in.
 *
 * It opens in the open field by itself when the size is not one of the list (or its unit is not points).
 */
import { BaseControl, Button, CustomSelectControl, Flex, FlexItem, __experimentalHStack as HStack, RangeControl, __experimentalSpacer as Spacer, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { settings } from '@wordpress/icons';
import { FONT_SIZES, FONT_SIZE_UNITS, fontSizeChange, fontSizeShown, fontSizeSlider, fontSizeText, fontSizeUnit, isCustomSize, localizedNumbers, sizePt } from './size';

type Props = {
	/** Points (a number), or a length with its unit ("4.4mm"): the unit the field shows is the one the size is written in. */
	size: number | string;
	onChange: ( size: number | string ) => void;
};

export default function FontSize( { size, onChange }: Props ) {
	const id = useInstanceId( FontSize, 'evcert-font-size' );
	const [ custom, setCustom ] = useState( () => isCustomSize( size ) );
	// The field is made again when it loses focus, so what was typed ("12.50") is shown as it was understood ("12.5").
	const [ synced, setSynced ] = useState( 0 );
	const unit = fontSizeUnit( size );
	const pt = sizePt( size );
	const shown = fontSizeShown( size );
	const slider = fontSizeSlider( unit );

	const options = [
		// What stands in for the "Default" of the native picker when the size is not one of the list.
		...( ! isCustomSize( size ) ? [] : [ { key: 'custom', name: __( 'Custom', 'events-certificates' ), hint: localizedNumbers( shown, document.documentElement.lang || 'en' ) } ] ),
		...FONT_SIZES.map( ( s ) => ( { key: String( s ), name: `${ s } pt`, hint: String( s ) } ) ),
	];
	const selected = ( isCustomSize( size ) ? options[ 0 ] : options.find( ( o ) => o.key === String( pt ) ) ) || options[ 0 ];

	return (
		<fieldset className="components-font-size-picker evcert-font-size" aria-labelledby={ id }>
			<HStack className="components-font-size-picker__header evcert-font-size__header">
				<BaseControl.VisualLabel id={ id } className="evcert-font-size__label">
					{ __( 'Size', 'events-certificates' ) }
				</BaseControl.VisualLabel>
				<Button
					className="evcert-font-size__toggle"
					label={ custom ? __( 'Use size preset', 'events-certificates' ) : __( 'Set custom size', 'events-certificates' ) }
					icon={ settings }
					isPressed={ custom }
					size="small"
					onClick={ () => setCustom( ! custom ) }
				/>
			</HStack>
			<div>
				{ ! custom && (
					<CustomSelectControl
						__next40pxDefaultSize
						className="components-font-size-picker__select"
						label={ __( 'Size', 'events-certificates' ) }
						hideLabelFromVision
						showSelectedHint
						options={ options }
						value={ selected }
						onChange={ ( { selectedItem } ) => {
							// A size of the list is in points; "Custom" is the size it already is.
							if ( selectedItem && selectedItem.key !== 'custom' ) {
								onChange( Number( selectedItem.key ) );
							}
						} }
					/>
				) }
				{ custom && (
					<Flex className="components-font-size-picker__custom-size-control">
						<FlexItem isBlock>
							<UnitControl
								key={ `${ unit }-${ synced }` }
								__next40pxDefaultSize
								label={ __( 'Size', 'events-certificates' ) }
								labelPosition="top"
								hideLabelFromVision
								step="any"
								spinFactor={ 0.1 }
								min={ 0 }
								units={ FONT_SIZE_UNITS.map( ( value ) => ( { value, label: value } ) ) }
								unit={ unit }
								value={ shown }
								onBlur={ () => setSynced( ( n ) => n + 1 ) }
								onChange={ ( next ) => {
									// The control says the same number in the new unit first when the unit is changed: that is only the unit (the size is kept).
									const value = next ? fontSizeChange( next, size, unit ) : undefined;
									if ( value !== undefined ) {
										onChange( value );
									}
								} }
							/>
						</FlexItem>
						<FlexItem isBlock>
							<Spacer marginX={ 2 } marginBottom={ 0 }>
								<RangeControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									className="components-font-size-picker__custom-input"
									label={ __( 'Size', 'events-certificates' ) }
									hideLabelFromVision
									withInputField={ false }
									{ ...slider }
									value={ Math.min( slider.max, Number.parseFloat( fontSizeText( pt, unit ) ) ) }
									onChange={ ( v ) => {
										if ( v !== undefined && v > 0 ) {
											const value = fontSizeChange( `${ v }${ unit }`, size, unit );
											if ( value !== undefined ) {
												onChange( value );
											}
										}
									} }
								/>
							</Spacer>
						</FlexItem>
					</Flex>
				) }
			</div>
		</fieldset>
	);
}
