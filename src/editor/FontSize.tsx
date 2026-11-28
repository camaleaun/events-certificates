/**
 * The font size of a text: a size of the list (8 to 42 pt) or a custom one in pt, px, mm, cm or in, with any decimals (WordPress's own FontSizePicker rounds a custom
 * size to the step of its unit: 12.5 pt becomes 13). The arrows and the slider go by 0.1; what is typed is kept (up to three decimals) and shown with no zeros at
 * the end. The element keeps points; the unit chosen is only how the field shows and takes it (px is pt: 1/72 in).
 */
import { RangeControl, SelectControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { FONT_SIZE_UNITS, fontSizePt, fontSizeText } from './size';

/** The sizes of the list, in points. */
export const FONT_SIZES = [ 8, 10, 12, 14, 16, 18, 24, 32, 42 ];

export default function FontSize( { size, onChange }: { size: number; onChange: ( size: number ) => void } ) {
	const [ unit, setUnit ] = useState< string >( 'pt' );
	// The field is made again when it loses focus, so what was typed ("12.50") is shown as it was understood ("12.5").
	const [ synced, setSynced ] = useState( 0 );
	return (
		<div className="evcert-font-size">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Size', 'events-certificates' ) }
				value={ FONT_SIZES.includes( size ) ? String( size ) : 'custom' }
				options={ [ ...FONT_SIZES.map( ( s ) => ( { label: `${ s } pt`, value: String( s ) } ) ), { label: __( 'Custom', 'events-certificates' ), value: 'custom' } ] }
				onChange={ ( v ) => v !== 'custom' && onChange( Number( v ) ) }
			/>
			<UnitControl
				key={ `${ unit }-${ synced }` }
				__next40pxDefaultSize
				label={ __( 'Custom size', 'events-certificates' ) }
				step="any"
				spinFactor={ 0.1 }
				units={ FONT_SIZE_UNITS.map( ( value ) => ( { value, label: value } ) ) }
				unit={ unit }
				value={ fontSizeText( size, unit ) }
				onUnitChange={ ( next ) => next && setUnit( next ) }
				onBlur={ () => setSynced( ( n ) => n + 1 ) }
				onChange={ ( next ) => {
					const pt = next ? fontSizePt( next, unit ) : undefined;
					if ( pt !== undefined && pt !== size ) {
						onChange( pt );
					}
				} }
			/>
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Size', 'events-certificates' ) }
				hideLabelFromVision
				value={ Math.min( 100, size ) }
				min={ 1 }
				max={ 100 }
				step={ 0.1 }
				withInputField={ false }
				onChange={ ( v ) => v !== undefined && v > 0 && onChange( Math.round( v * 1000 ) / 1000 ) }
			/>
		</div>
	);
}
