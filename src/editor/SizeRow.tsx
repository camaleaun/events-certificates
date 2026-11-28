/**
 * The Size row of an SVG: its width and height, one row in the sidebar and in the SVG dialog. The row only shows and takes what is typed;
 * what typing one does is the parent's (the sidebar changes the element at once, in the unit chosen, the dialog a draft in points).
 */
import { __experimentalHStack as HStack, __experimentalNumberControl as NumberControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { PageBox } from '@camaleaun/draw';
import { DropdownRow } from './DesignPanel';
import type { Element } from './model';
import { followed, resizeSideLen, sizeIn, stretched, unitOf, unitPatch, type Unit } from './size';

/** The units a size is typed in: decimals are kept (0.01, three in inches). */
export const SIZE_UNITS = [
	{ value: 'pt', label: 'pt', step: 0.01 },
	{ value: 'px', label: 'px', step: 0.01 },
	{ value: 'mm', label: 'mm', step: 0.01 },
	{ value: 'cm', label: 'cm', step: 0.01 },
	{ value: 'in', label: 'in', step: 0.001 },
];

type Props = {
	w?: number;
	h?: number;
	// The unit each side is shown and typed in: then a field takes a length ("42.5mm") and `onChange` gets that text; without it the sides are numbers.
	units?: { w: Unit; h: Unit };
	// Another unit was chosen for a side: the size stays, only the unit it is written in changes (the parent knows the exact size in points).
	onUnit?: ( side: 'w' | 'h', unit: Unit ) => void;
	// What the fields show when there is no size (a drawing's viewBox).
	placeholder?: [ number, number ];
	// A size that something else decides (the ratio that is kept, an axis that fills) shows its value and cannot be typed.
	disabled?: { w?: boolean; h?: boolean };
	onChange: ( side: 'w' | 'h', value: string | number | undefined ) => void;
	// A line under the fields.
	note?: string;
};

export default function SizeRow( { w, h, units, onUnit, placeholder, disabled, onChange, note }: Props ) {
	const same = units && units.w === units.h;
	const summary = ! ( w && h ) ? '- × -' : units ? ( same ? `${ w } × ${ h } ${ units.w }` : `${ w }${ units.w } × ${ h }${ units.h }` ) : `${ w } × ${ h }`;
	// Choosing another unit keeps the size and says it in that unit (96pt is 33.87mm), as the core's fields do not: the control itself keeps the number.
	const change = ( side: 'w' | 'h', text: string ) => {
		const unit = unitOf( text );
		if ( units && onUnit && unit !== units[ side ] ) {
			onUnit( side, unit );
			return;
		}
		onChange( side, text );
	};
	const field = ( side: 'w' | 'h', label: string, value?: number ) =>
		units ? (
			<UnitControl
				__next40pxDefaultSize
				label={ label }
				min={ 0.01 }
				units={ SIZE_UNITS }
				disabled={ disabled?.[ side ] }
				value={ value === undefined ? '' : `${ value }${ units[ side ] }` }
				onChange={ ( v ) => v && change( side, v ) }
			/>
		) : (
			<NumberControl __next40pxDefaultSize label={ label } min={ 1 } step={ 0.01 } disabled={ disabled?.[ side ] } placeholder={ placeholder ? String( placeholder[ side === 'w' ? 0 : 1 ] ) : undefined } value={ value ?? '' } onChange={ ( v ) => onChange( side, v ) } />
		);
	return (
		<DropdownRow label={ __( 'Size', 'events-certificates' ) } value={ summary }>
			{ () => (
				<>
					<HStack alignment="top">
						{ field( 'w', __( 'Width', 'events-certificates' ), w ) }
						{ field( 'h', __( 'Height', 'events-certificates' ), h ) }
					</HStack>
					{ note && <p className="description">{ note }</p> }
				</>
			) }
		</DropdownRow>
	);
}

/** The Size row of an element in the sidebar: its own size on the page, in the unit of its layout; it changes the element at once and makes its layout again. */
export function ElementSize( { el, page, onChange }: { el: Element; page: PageBox; onChange: ( patch: Partial< Element > ) => void } ) {
	const follows = followed( el as never );
	const fills = stretched( el as never );
	const width = sizeIn( el as never, 'w' );
	const height = sizeIn( el as never, 'h' );
	return (
		<SizeRow
			w={ width.value }
			h={ height.value }
			units={ { w: width.unit, h: height.unit } }
			onUnit={ ( side, unit ) => onChange( unitPatch( el as never, side, unit, page ) as Partial< Element > ) }
			disabled={ { w: follows === 'w' || fills.w, h: follows === 'h' || fills.h } }
			onChange={ ( side, value ) => onChange( resizeSideLen( el as never, side, String( value ), page ) as Partial< Element > ) }
		/>
	);
}
