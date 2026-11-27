/**
 * The larger SVG editor, in a modal: the body is SvgFields (a big preview and the same rows as the sidebar, with more room).
 * Changes are a draft until Apply.
 */
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import SvgSource from './SvgSource';
import type { SvgDraft } from './svg-draft';
import { svgPaths, type Design, type Mode, type SvgEl } from './model';
import { shown, sizePatch } from './size';

type Props = {
	el: SvgEl;
	design: Design;
	mode: Mode;
	onApply: ( patch: Partial< SvgEl > ) => void;
	onClose: () => void;
	// A stored drawing (a logo, a signature) has a size to show it at beside the viewBox: this gives the drawing back whole, with that
	// size. An element of a template has its own width and height on the page: its Size row is that size, and Apply changes it.
	preferred?: { w?: number; h?: number };
	onApplyDrawing?: ( draft: SvgDraft ) => void;
};

export default function SvgEditor( { el, design, mode, onApply, onClose, preferred, onApplyDrawing }: Props ) {
	// What there is to edit at first; with no path, the dialog starts as the SVG code box (a drawing that has none yet, the place of a signature).
	const [ base ] = useState< SvgDraft >( () => ( { title: el.title || '', vw: el.vw, vh: el.vh, ...( onApplyDrawing ? ( preferred?.w && preferred?.h ? preferred : {} ) : { w: shown( el.w ), h: shown( el.h ) } ), paths: svgPaths( el ) } ) );
	const [ draft, setDraft ] = useState< SvgDraft | null >( base.paths.some( ( p ) => p.d ) ? base : null );
	const usable = ( draft?.paths || [] ).filter( ( p ) => p.d );
	const title = draft?.title || '';

	return (
		<Modal title={ title.trim() ? sprintf( /* translators: %s: title of the SVG. */ __( 'Edit SVG: %s', 'events-certificates' ), title.trim() ) : __( 'Edit SVG', 'events-certificates' ) } size="large" onRequestClose={ onClose } className="evcert-svg-editor" overlayClassName="evcert-svg-editor-overlay">
			<SvgSource draft={ draft } onChange={ setDraft } design={ design } mode={ mode } current={ el.color || '$text' } element={ ! onApplyDrawing } base={ base } dropFiles />
			<div className="evcert-actions evcert-svg-editor__footer">
				<Button variant="tertiary" __next40pxDefaultSize onClick={ onClose }>
					{ __( 'Cancel', 'events-certificates' ) }
				</Button>
				<Button
					variant="primary"
					__next40pxDefaultSize
					disabled={ ! usable.length }
					accessibleWhenDisabled
					onClick={ () => {
						if ( ! draft ) {
							return;
						}
						if ( onApplyDrawing ) {
							onApplyDrawing( { ...draft, paths: usable } );
						} else {
							onApply( { title: draft.title.trim() || undefined, vw: draft.vw, vh: draft.vh, paths: usable, markup: undefined, fill: undefined, ...( sizePatch( el, draft.w, draft.h, design.page ) as Partial< SvgEl > ) } );
						}
						onClose();
					} }
				>
					{ __( 'Apply', 'events-certificates' ) }
				</Button>
			</div>
		</Modal>
	);
}
