/**
 * The Size row of an SVG: its width and height in points, one row in the sidebar and in the SVG dialog. The row only shows and takes
 * the numbers; what typing one does is the parent's (the sidebar changes the element at once, the dialog a draft).
 */
import { __experimentalHStack as HStack, __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { PageBox } from '@camaleaun/draw';
import { DropdownRow } from './DesignPanel';
import type { Element } from './model';
import { followed, resizeSide, shown, stretched } from './size';

type Props = {
	w?: number;
	h?: number;
	// What the fields show when there is no size (a drawing's viewBox).
	placeholder?: [ number, number ];
	// A size that something else decides (the ratio that is kept, an axis that fills) shows its value and cannot be typed.
	disabled?: { w?: boolean; h?: boolean };
	onChange: ( side: 'w' | 'h', value: string | number | undefined ) => void;
	// A line under the fields.
	note?: string;
};

export default function SizeRow( { w, h, placeholder, disabled, onChange, note }: Props ) {
	return (
		<DropdownRow label={ __( 'Size', 'events-certificates' ) } value={ w && h ? `${ w } × ${ h }` : '- × -' }>
			{ () => (
				<>
					<HStack alignment="top">
						<NumberControl __next40pxDefaultSize label={ __( 'Width', 'events-certificates' ) } min={ 1 } disabled={ disabled?.w } placeholder={ placeholder ? String( placeholder[ 0 ] ) : undefined } value={ w ?? '' } onChange={ ( v ) => onChange( 'w', v ) } />
						<NumberControl __next40pxDefaultSize label={ __( 'Height', 'events-certificates' ) } min={ 1 } disabled={ disabled?.h } placeholder={ placeholder ? String( placeholder[ 1 ] ) : undefined } value={ h ?? '' } onChange={ ( v ) => onChange( 'h', v ) } />
					</HStack>
					{ note && <p className="description">{ note }</p> }
				</>
			) }
		</DropdownRow>
	);
}

/** The Size row of an element in the sidebar: its own size on the page; it changes the element at once and makes its layout again. */
export function ElementSize( { el, page, onChange }: { el: Element; page: PageBox; onChange: ( patch: Partial< Element > ) => void } ) {
	const follows = followed( el as never );
	const fills = stretched( el as never );
	return (
		<SizeRow
			w={ shown( el.w as number ) }
			h={ shown( el.h as number ) }
			disabled={ { w: follows === 'w' || fills.w, h: follows === 'h' || fills.h } }
			onChange={ ( side, value ) => onChange( resizeSide( el as never, side, Number( value ), page ) as Partial< Element > ) }
		/>
	);
}
