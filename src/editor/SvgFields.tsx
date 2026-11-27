/**
 * The body of the SVG editor: a big preview on the left (the path under the pointer or being edited highlighted) and on the
 * right the viewBox and one row per path. Used by the editor's Edit SVG dialog and by the signature chooser's Edit SVG tab;
 * the draft is the parent's, so each decides what Apply does.
 */
import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { color, type Design, type Mode } from './model';
import SizeRow from './SizeRow';
import SvgGroups, { ViewBoxRow } from './SvgGroups';
import { PathList } from './SvgPanel';
import { refilled, type SvgDraft } from './svg-draft';
export type { SvgDraft };

type Props = {
	draft: SvgDraft;
	onChange: ( next: SvgDraft ) => void;
	design: Design;
	mode: Mode;
	// The color of paths without their own ('$text' by default).
	current?: string;
	// The Size row is the element's own width and height on the page, in points (an element of a template), not a drawing's preferred size.
	element?: boolean;
	// What goes at the top of the column of fields, above the groups (the button to paste another SVG).
	before?: ReactNode;
};

// A width or a height typed: the other follows the viewBox's proportion when it is empty; emptying one empties both (an element
// always has a size, so for it an empty field changes nothing).
function sized( draft: SvgDraft, side: 'w' | 'h', raw: string | number | undefined, element?: boolean ): SvgDraft {
	const value = Number( raw );
	if ( ! ( value > 0 ) ) {
		return element ? draft : { ...draft, w: undefined, h: undefined };
	}
	const ratio = draft.vw / draft.vh;
	const other = 'w' === side ? Math.round( ( value / ratio ) * 100 ) / 100 : Math.round( value * ratio * 100 ) / 100;
	const next = { ...draft, [ side ]: value } as SvgDraft;
	const opposite = 'w' === side ? 'h' : 'w';
	return next[ opposite ] ? next : { ...next, [ opposite ]: other };
}

export default function SvgFields( { draft, onChange, design, mode, current = '$text', element, before }: Props ) {
	const [ focus, setFocus ] = useState< number | null >( null );
	const { title, vw, vh, paths } = draft;
	return (
		<div className="evcert-svg-editor__body">
			<div className="evcert-svg-editor__preview">
				{ /* The checkerboard is the drawing's own area: its viewBox's aspect ratio, centred. */ }
				<svg viewBox={ `0 0 ${ vw } ${ vh }` } role="img" aria-label={ title.trim() || __( 'Preview', 'events-certificates' ) } style={ { '--evcert-ratio': vw / vh } as React.CSSProperties }>
					{ paths.map( ( p, i ) =>
						p.d ? <path key={ i } d={ p.d } fill={ color( design, p.fill || current, mode ) } fillRule={ p.rule } opacity={ focus === null || focus === i ? 1 : 0.25 } /> : null
					) }
				</svg>
			</div>
			<div className="evcert-svg-editor__fields">
				{ before }
				<SvgGroups
					viewBox={ <ViewBoxRow vw={ vw } vh={ vh } onChange={ ( patch ) => onChange( { ...draft, ...patch } ) } /> }
					size={
						<SizeRow
							w={ draft.w }
							h={ draft.h }
							placeholder={ [ vw, vh ] }
							onChange={ ( side, value ) => onChange( sized( draft, side, value, element ) ) }
							note={ element ? undefined : __( 'The size to show it at, e.g. an icon of 24 × 24 shown at 512 × 512. Empty, it is the viewBox’s; the certificate fits it in its place with this proportion.', 'events-certificates' ) }
						/>
					}
					paths={
						<PathList paths={ paths } current={ current } design={ design } mode={ mode } onPaths={ ( next ) => onChange( { ...draft, paths: next } ) } onFocus={ setFocus } onEmpty={ () => { setFocus( null ); onChange( refilled( draft, element ) ); } } onImport={ ( svg ) => onChange( { ...draft, vw: svg.vw, vh: svg.vh, w: element ? draft.w : svg.w, h: element ? draft.h : svg.h, paths: svg.paths } ) } />
					}
				/>
			</div>
		</div>
	);
}
