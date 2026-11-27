/**
 * An SVG element as fields, laid out like the Certificate tab: the viewBox as
 * a row whose value opens a dropdown (like Size), and the paths as a list like
 * the palette's colors — a swatch for the fill and the path data, added with +
 * and removed with ×. A path without a fill uses the current color (the
 * element's Current color, by default the text color).
 */
import {
	Button,
	ColorIndicator,
	Dropdown,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	TextareaControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalItem as Item,
	__experimentalItemGroup as ItemGroup,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { chevronDown, chevronUp, copy, moreVertical, plus, trash } from '@wordpress/icons';
import { ElementSize } from './SizeRow';
import SvgGroups, { ViewBoxRow } from './SvgGroups';
import { emptiedElement } from './svg-draft';
import { ColorChoices, colorLabel } from './ColorChoices';
import { color, isPathData, sanitizeSvg, svgPaths, type Design, type Mode, type SvgEl, type SvgPath } from './model';

type Props = {
	el: SvgEl;
	design: Design;
	mode: Mode;
	onChange: ( patch: Partial< SvgEl > ) => void;
};

/** The fill swatch of a path: no fill (the current color: an empty circle), a color of the Theme or the Elements, or a custom one. */
export function FillSwatch( { value, design, mode, onChange }: { value?: string; design: Design; mode: Mode; onChange: ( fill?: string ) => void } ) {
	const shown = value ? color( design, value, mode ) : 'none';
	return (
		<Dropdown
			popoverProps={ { placement: 'left-start', offset: 36, shift: true, className: 'evcert-colors__popover-wrap' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					className="evcert-svg-path__swatch"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					label={ sprintf( /* translators: %s: color name. */ __( 'Fill: %s', 'events-certificates' ), value ? colorLabel( design, value ) : __( 'Current color', 'events-certificates' ) ) }
					showTooltip
				>
					{ /* No fill is the current color: a plain empty circle, not that color. */ }
					<ColorIndicator colorValue={ shown === 'none' ? undefined : shown } />
				</Button>
			) }
			renderContent={ () => <ColorChoices theme value={ value } clearTo="" design={ design } mode={ mode } onChange={ ( fill ) => onChange( fill || undefined ) } /> }
		/>
	);
}

/** The path data field: kept while typing, saved when it is valid. Pasting SVG code imports it. */
export function PathData( { index, value, multiline, onChange, onImport, onFocus }: { index: number; value: string; multiline?: boolean; onChange: ( d: string ) => void; onImport: ( svg: { vw: number; vh: number; w?: number; h?: number; paths: SvgPath[] } ) => void; onFocus?: () => void } ) {
	const [ text, setText ] = useState( value );
	const valid = ! text || isPathData( text );
	const props = {
		__nextHasNoMarginBottom: true,
		className: `evcert-svg-path__d${ valid ? '' : ' is-invalid' }`,
		label: sprintf( /* translators: %d: path number. */ __( 'Path %d data (d)', 'events-certificates' ), index + 1 ),
		hideLabelFromVision: ! multiline,
		placeholder: 'M0 0h24v24H0z',
		value: text,
		onFocus,
		onPaste: ( event: { clipboardData: DataTransfer; preventDefault: () => void } ) => {
			const pasted = event.clipboardData.getData( 'text/plain' );
			const svg = /^\s*</.test( pasted ) ? sanitizeSvg( pasted ) : null;
			if ( svg ) {
				event.preventDefault();
				onImport( svg );
			}
		},
		onChange: ( d: string ) => {
			setText( d );
			if ( isPathData( d ) ) {
				onChange( d.trim() );
			}
		},
	};
	return multiline ? (
		<TextareaControl { ...props } rows={ 3 } help={ valid ? undefined : __( 'Only path commands and numbers, starting with M.', 'events-certificates' ) } />
	) : (
		<TextControl __next40pxDefaultSize { ...props } />
	);
}

type PathListProps = {
	paths: SvgPath[];
	current: string;
	design: Design;
	mode: Mode;
	onPaths: ( next: SvgPath[] ) => void;
	onImport: ( svg: { vw: number; vh: number; w?: number; h?: number; paths: SvgPath[] } ) => void;
	onFocus?: ( index: number | null ) => void;
	// With it, the last path can be removed too: this is called (and onPaths is not) when no path with data is left, to give the drawing back
	// its info icon. Without it, the last path cannot be removed.
	onEmpty?: () => void;
	// Where the path data popover opens: beside the sidebar, or below the row in the modal.
	placement?: 'left-start' | 'bottom-start';
};

/** A short, readable start of the path data for the row's link. */
const short = ( d: string ) => ( d.length > 28 ? `${ d.slice( 0, 28 ).trim() }…` : d );

/**
 * The paths, one row each (as compact as the palette's colors): the fill
 * swatch, the path data as a link that opens a popover with the whole value,
 * the block mover and an Options menu. The + at the end of the Paths heading
 * adds a path; it looks like the block appender.
 */
export function PathList( { paths, current, design, mode, onPaths, onImport, onFocus, onEmpty, placement = 'left-start' }: PathListProps ) {
	const [ version, setVersion ] = useState( 0 );
	const replace = ( next: SvgPath[] ) => {
		onPaths( next );
		setVersion( version + 1 );
	};
	const setPath = ( i: number, patch: Partial< SvgPath > ) => onPaths( paths.map( ( p, j ) => ( j === i ? { ...p, ...patch } : p ) ) );
	const move = ( i: number, to: number ) => {
		const next = [ ...paths ];
		const [ p ] = next.splice( i, 1 );
		next.splice( to, 0, p );
		replace( next );
	};
	const remove = ( i: number ) => {
		const next = paths.filter( ( _, j ) => j !== i );
		if ( onEmpty && ! next.some( ( p ) => p.d ) ) {
			onEmpty();
			setVersion( version + 1 );
			return;
		}
		replace( next );
	};
	return (
		<>
			<HStack className="evcert-svg-panel__header">
				<h2 className="evcert-section-heading">{ __( 'Paths', 'events-certificates' ) }</h2>
				<Button className="evcert-appender" icon={ plus } label={ __( 'Add path', 'events-certificates' ) } onClick={ () => replace( [ ...paths, { d: '' } ] ) } />
			</HStack>
			<ItemGroup isBordered isSeparated className="evcert-svg-paths">
				{ paths.map( ( p, i ) => (
					<Item key={ `${ version }-${ i }` } className="evcert-svg-path" onMouseEnter={ () => onFocus?.( i ) } onMouseLeave={ () => onFocus?.( null ) }>
						<HStack spacing={ 2 }>
							<FillSwatch value={ p.fill } design={ design } mode={ mode } onChange={ ( fill ) => setPath( i, { fill } ) } />
							<Dropdown
								className="evcert-svg-path__link"
								popoverProps={ { placement, offset: placement === 'left-start' ? 36 : 8, shift: true } }
								onToggle={ ( open ) => onFocus?.( open ? i : null ) }
								renderToggle={ ( { isOpen, onToggle } ) => (
									<Button variant="link" className="evcert-svg-path__d-link" onClick={ onToggle } aria-expanded={ isOpen } title={ p.d }>
										{ p.d ? short( p.d ) : __( 'Add path data', 'events-certificates' ) }
									</Button>
								) }
								renderContent={ () => (
									<div className="evcert-svg-path__popover">
										<PathData multiline index={ i } value={ p.d } onChange={ ( d ) => setPath( i, { d } ) } onImport={ onImport } />
									</div>
								) }
							/>
							<div className="evcert-mover evcert-mover--inline">
								<Button size="small" icon={ chevronUp } label={ __( 'Move up', 'events-certificates' ) } disabled={ i === 0 } accessibleWhenDisabled onClick={ () => move( i, i - 1 ) } />
								<Button size="small" icon={ chevronDown } label={ __( 'Move down', 'events-certificates' ) } disabled={ i === paths.length - 1 } accessibleWhenDisabled onClick={ () => move( i, i + 1 ) } />
							</div>
							<DropdownMenu icon={ moreVertical } label={ __( 'Path options', 'events-certificates' ) } toggleProps={ { size: 'small' } as any } popoverProps={ { placement: 'bottom-end' } }>
								{ ( { onClose } ) => (
									<>
										<MenuGroup>
											<MenuItem onClick={ () => { move( i, 0 ); onClose(); } } disabled={ i === 0 }>{ __( 'Move to bottom (behind)', 'events-certificates' ) }</MenuItem>
											<MenuItem onClick={ () => { move( i, paths.length - 1 ); onClose(); } } disabled={ i === paths.length - 1 }>{ __( 'Move to top (in front)', 'events-certificates' ) }</MenuItem>
											<MenuItem icon={ copy } onClick={ () => { replace( [ ...paths.slice( 0, i + 1 ), { ...p }, ...paths.slice( i + 1 ) ] ); onClose(); } }>{ __( 'Duplicate', 'events-certificates' ) }</MenuItem>
										</MenuGroup>
										<MenuGroup>
											<MenuItem icon={ trash } disabled={ paths.length === 1 && ! onEmpty } onClick={ () => { remove( i ); onClose(); } }>{ __( 'Delete', 'events-certificates' ) }</MenuItem>
										</MenuGroup>
									</>
								) }
							</DropdownMenu>
						</HStack>
					</Item>
				) ) }
			</ItemGroup>
		</>
	);
}

export default function SvgPanel( { el, design, mode, onChange }: Props ) {
	const paths = svgPaths( el );
	const current = el.color || '$text';
	const setPaths = ( next: SvgPath[] ) => onChange( { paths: next, markup: undefined, fill: undefined } );

	return (
		<SvgGroups
			viewBox={ <ViewBoxRow vw={ el.vw } vh={ el.vh } onChange={ ( patch ) => onChange( patch ) } /> }
			size={ <ElementSize el={ el } page={ design.page } onChange={ ( patch ) => onChange( patch as Partial< SvgEl > ) } /> }
			paths={
				<PathList paths={ paths } current={ current } design={ design } mode={ mode } onPaths={ setPaths } onEmpty={ () => onChange( emptiedElement() ) } onImport={ ( { vw, vh, paths: imported } ) => onChange( { vw, vh, paths: imported, markup: undefined, fill: undefined } ) } />
			}
		/>
	);
}
