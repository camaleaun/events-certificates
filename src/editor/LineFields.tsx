/**
 * The place and size of a line: its box, as the other elements have theirs (X, Y, width, height), in the unit the person picks. A line is kept by its two ends,
 * so a change here moves or stretches them and keeps which way it goes (from left to right or the other way, up or down).
 */
import { __experimentalHStack as HStack, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { UNITS, type Element, type LineEl } from './model';
import { lengthIn, lineBoxPatch, unitOf } from './size';
import { SIZE_UNITS } from './SizeRow';

type Unit = 'pt' | 'px' | 'mm' | 'cm' | 'in';
const UNITS_OF_LINE = SIZE_UNITS.filter( ( u ) => u.value !== '%' );

export default function LineFields( { el, onChange }: { el: LineEl; onChange: ( patch: Partial< Element > ) => void } ) {
	const [ unit, setUnit ] = useState< Unit >( 'pt' );
	const box = { x: Math.min( el.x1, el.x2 ), y: Math.min( el.y1, el.y2 ), w: Math.abs( el.x2 - el.x1 ), h: Math.abs( el.y2 - el.y1 ) };
	// Another unit keeps the length and says it in that unit; typing changes it in the unit it is in.
	const field = ( key: 'x' | 'y' | 'w' | 'h', label: string ) => (
		<UnitControl step="any" spinFactor={ 0.1 }
			key={ `${ key }-${ unit }` }
			__next40pxDefaultSize
			label={ label }
			min={ 0 }
			units={ UNITS_OF_LINE }
			value={ lengthIn( box[ key ], unit ) }
			onChange={ ( v ) => {
				if ( v === undefined || v === '' ) {
					return;
				}
				const next = unitOf( v ) as Unit;
				if ( next !== unit ) {
					setUnit( next );
					return;
				}
				onChange( lineBoxPatch( el, key, ( Number.parseFloat( v ) || 0 ) * ( UNITS[ unit ] || 1 ) ) as Partial< Element > );
			} }
		/>
	);
	return (
		<div className="evcert-line-fields">
			<HStack alignment="top">
				{ field( 'x', __( 'X', 'events-certificates' ) ) }
				{ field( 'y', __( 'Y', 'events-certificates' ) ) }
			</HStack>
			<HStack alignment="top">
				{ field( 'w', __( 'Width', 'events-certificates' ) ) }
				{ field( 'h', __( 'Height', 'events-certificates' ) ) }
			</HStack>
		</div>
	);
}
