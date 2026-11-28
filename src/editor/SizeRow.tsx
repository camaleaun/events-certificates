/**
 * The Size row of an SVG: its width and height, one row in the sidebar and in the SVG dialog. The row only shows and takes what is typed;
 * what typing one does is the parent's (the sidebar changes the element at once, in the unit chosen, the dialog a draft in points).
 */
import { __experimentalHStack as HStack, __experimentalNumberControl as NumberControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { PageBox } from '@camaleaun/draw';
import { DropdownRow } from './DesignPanel';
import type { Element } from './model';
import { clearSizePatch, followed, keepEmpty, resizeSideLen, sizeEmpty, sizeIn, stretched, unitOf, unitPatch, type Unit } from './size';

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
	// What the row says when closed, if it is not the numbers the fields have (a side left empty is the placeholder's).
	shown?: string;
	// A line under the fields.
	note?: string;
};

export default function SizeRow( { w, h, units, onUnit, placeholder, disabled, onChange, note, shown }: Props ) {
	const same = units && units.w === units.h;
	const summary = shown ? shown : ! ( w && h ) ? '- × -' : units ? ( same ? `${ w } × ${ h } ${ units.w }` : `${ w }${ units.w } × ${ h }${ units.h }` ) : `${ w } × ${ h }`;
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
			<UnitControl step="any" spinFactor={ 0.1 }
				__next40pxDefaultSize
				label={ label }
				min={ 0.01 }
				units={ SIZE_UNITS }
				disabled={ disabled?.[ side ] }
				value={ value === undefined ? '' : `${ value }${ units[ side ] }` }
				placeholder={ value === undefined && placeholder ? String( placeholder[ side === 'w' ? 0 : 1 ] ) : undefined }
				onChange={ ( v ) => v && change( side, v ) }
				// Left empty: the size is the one it has with nothing said (the parent knows which: an SVG's viewBox).
				onBlur={ ( event: React.FocusEvent< HTMLInputElement > ) => {
					if ( ! event.target.value && ! disabled?.[ side ] ) {
						onChange( side, '' );
					}
				} }
			/>
		) : (
			<NumberControl __next40pxDefaultSize label={ label } min={ 1 } step="any" spinFactor={ 0.01 } disabled={ disabled?.[ side ] } placeholder={ placeholder ? String( placeholder[ side === 'w' ? 0 : 1 ] ) : undefined } value={ value ?? '' } onChange={ ( v ) => onChange( side, v ) } />
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
	// A side an SVG says nothing of is as big as its viewBox: the field is empty, the viewBox's size is its placeholder.
	const emptyW = sizeEmpty( el as never, 'w' );
	const emptyH = sizeEmpty( el as never, 'h' );
	const own: [ number, number ] | undefined = el.type === 'svg' ? [ el.vw, el.vh ] : undefined;
	const part = ( _empty: boolean, size: { value: number; unit: Unit } ) => `${ size.value }${ size.unit }`;
	return (
		<SizeRow
			w={ emptyW ? undefined : width.value }
			h={ emptyH ? undefined : height.value }
			units={ { w: width.unit, h: height.unit } }
			placeholder={ own }
			shown={ emptyW || emptyH ? `${ part( emptyW, width ) } × ${ part( emptyH, height ) }` : undefined }
			onUnit={ ( side, unit ) => onChange( keepEmpty( el as never, unitPatch( el as never, side, unit, page ), side ) as Partial< Element > ) }
			disabled={ { w: follows === 'w' || fills.w, h: follows === 'h' || fills.h } }
			onChange={ ( side, value ) => {
				// A side left empty (an SVG's) says no size: it is as big as its viewBox.
				const patch = value === '' && el.type === 'svg' ? clearSizePatch( el as never, side, page ) : keepEmpty( el as never, resizeSideLen( el as never, side, String( value ), page ), side );
				onChange( patch as Partial< Element > );
			} }
		/>
	);
}
