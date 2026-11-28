/**
 * Where the SVG of a dialog comes from, in the two SVG dialogs (the editor's modal and the media modal's SVG tab): the SVG code box (the
 * example in it is the info icon) while there is no drawing, and once there is one, the same body as the editor's (SvgFields) with a
 * "Paste another SVG" button that goes back to the box. The draft is the parent's; what Apply does is too.
 */
import { Button, DropZone, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { parseSvgText, problemText, skippedText, SVG_EXAMPLE } from '../media-chooser/svg-code';
import { PICTURE_PATH_DATA } from './model';
import type { Design, Mode } from './model';
import SvgFields from './SvgFields';
import { taken, type SvgDraft } from './svg-draft';

type Props = {
	// The drawing; null while there is none (the SVG code box).
	draft: SvgDraft | null;
	onChange: ( next: SvgDraft | null ) => void;
	design: Design;
	mode: Mode;
	current?: string;
	// An element of a template: a pasted SVG keeps the title and the size on the page of `base`.
	element?: boolean;
	base: SvgDraft;
	// An .svg file dropped on the box is read (the media modal's has its own, on the whole window).
	dropFiles?: boolean;
};

const isSvgFile = ( file: File ) => /^image\/svg/i.test( file.type ) || /\.svg$/i.test( file.name );

export default function SvgSource( { draft, onChange, design, mode, current, element, base, dropFiles }: Props ) {
	const [ text, setText ] = useState( '' );
	const [ problem, setProblem ] = useState( '' );
	// How many paths the drawing taken lost (too long for the place, or not path data): it says so, not to look whole.
	const [ skipped, setSkipped ] = useState( 0 );
	const has = !! draft;
	useEffect( () => {
		if ( has ) {
			setText( '' );
			setProblem( '' );
		} else {
			setSkipped( 0 );
		}
	}, [ has ] );

	/** Takes a text that is a usable SVG; says so and returns false when it is not. */
	const take = ( value: string, say: boolean, title = '' ): boolean => {
		// An element of a template takes long paths; a picture (a signature, a logo) takes what the server keeps of those.
		const parsed = parseSvgText( value, title, element ? undefined : PICTURE_PATH_DATA );
		if ( 'draft' in parsed ) {
			setSkipped( parsed.skipped || 0 );
			onChange( taken( parsed.draft, base, element ) );
			return true;
		}
		setProblem( say ? problemText( parsed.problem ) : '' );
		return false;
	};

	if ( draft ) {
		return (
			<SvgFields
				draft={ draft }
				onChange={ onChange }
				design={ design }
				mode={ mode }
				current={ current }
				element={ element }
				before={
					<div className="evcert-svg-tab__again">
						<Button variant="tertiary" size="compact" onClick={ () => onChange( null ) }>
							{ __( 'Paste another SVG', 'events-certificates' ) }
						</Button>
						{ skipped > 0 && (
							<p className="evcert-svg-code__problem" role="status">
								{ skippedText( skipped ) }
							</p>
						) }
					</div>
				}
			/>
		);
	}
	return (
		<div className="evcert-svg-editor__body">
			<div className="evcert-svg-editor__preview">
				<svg viewBox="0 0 120 44" role="img" aria-label={ __( 'Preview', 'events-certificates' ) } style={ { '--evcert-ratio': 120 / 44 } as React.CSSProperties } />
			</div>
			<div className="evcert-svg-editor__fields evcert-svg-code">
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'SVG code', 'events-certificates' ) }
					help={ __( 'Paste the code of an SVG here, or drop an .svg file on this window.', 'events-certificates' ) }
					placeholder={ SVG_EXAMPLE }
					rows={ 12 }
					value={ text }
					spellCheck={ false }
					onChange={ ( value ) => {
						setText( value );
						take( value, false );
					} }
					onPaste={ ( event: React.ClipboardEvent ) => {
						// What was pasted goes straight in when it is usable; else it lands in the box, with what is wrong.
						if ( take( event.clipboardData.getData( 'text' ), false ) ) {
							event.preventDefault();
						} else {
							setProblem( problemText( ( parseSvgText( event.clipboardData.getData( 'text' ) ) as { problem: 'empty' } ).problem ) );
						}
					} }
					onBlur={ () => text.trim() && take( text, true ) }
				/>
				{ problem && (
					<p className="evcert-svg-code__problem" role="alert">
						{ problem }
					</p>
				) }
			</div>
			{ dropFiles && (
				<DropZone
					label={ __( 'Drop an .svg file to read its paths', 'events-certificates' ) }
					onFilesDrop={ ( files: File[] ) => {
						const file = files.find( isSvgFile );
						if ( file ) {
							file.text().then( ( value ) => take( value, true, file.name.replace( /\.svg$/i, '' ) ) );
						}
					} }
				/>
			) }
		</div>
	);
}
