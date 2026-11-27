/**
 * The Colors section of the sidebar, like the Site Editor's Styles > Colors, as the block editor's own collapsing panel (closed when the
 * sidebar opens): the Theme, with the dark-mode toggle in its header, (the swatches to pick from: Base and Contrast always there), and the colors the
 * certificate uses in two groups, Document (Text and Background) and Elements (yours). Each color has a Light and an optional Dark
 * value, shown as a pair of circles; its popover is the block editor's: a Light and a Dark tab, the color picker, the Theme to pick
 * from and Clear. A color keeps its own values: picking a swatch copies the value, and changing the theme later changes no color.
 */
import { darkEnabled } from './darkMode';
import {
	Button,
	ColorIndicator,
	ColorPalette,
	ColorPicker,
	Dropdown,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Notice,
	PanelBody,
	TabPanel,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalItem as Item,
	__experimentalItemGroup as ItemGroup,
	__experimentalVStack as VStack,
	__experimentalZStack as ZStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { lineSolid, moreVertical, plus, styles } from '@wordpress/icons';
import { stockName } from './base';
import { color as colorValue, colorOf, nextColor, nextSwatch, renameColor, renameSwatch, replaceRefs, setRoleValue, swatchName, themeOptions, usesColor, type ColorVar, type Design, type Mode, type Swatch } from './model';

type Props = {
	design: Design;
	mode: Mode;
	onMode: ( mode: Mode ) => void;
	onChange: ( design: Design ) => void;
};

/** A swatch's label: Base and Contrast, and the names the plugin ships (Brand, Lines…), in the admin's language. */
const labelOf = ( s: Swatch ): string => stockName( swatchName( s ) );

/** A color's name: the one it has, the ones the plugin ships (Background, Text…) in the admin's language. */
const nameOf = ( v: ColorVar ): string => stockName( v.name );

/** "Color N". */
const colorName = ( n: number ): string => sprintf( /* translators: %d: a number. */ __( 'Color %d', 'events-certificates' ), n );

/** What a circle shows: nothing for none/transparent. */
const shown = ( value?: string ): string | undefined => ( value && value !== 'transparent' && value !== 'none' ? value : undefined );

/** The pair of circles of a color: Light, then Dark (the core's empty circle, with its slash, when it has none of its own). */
function ColorPair( { v, design }: { v: ColorVar; design: Design } ) {
	// A value may point at a color of the Theme ("$base"): the circle shows that color.
	const seen = ( value: string | undefined, mode: Mode ) => shown( value ? colorValue( design, value, mode ) : undefined );
	return (
		<ZStack isLayered={ false } offset={ -8 } className="evcert-colors__pair">
			<ColorIndicator colorValue={ seen( v.light, 'light' ) } />
			{ darkEnabled() && <ColorIndicator colorValue={ seen( v.dark, 'dark' ) } /> }
		</ZStack>
	);
}

/** The theme's colors as the picker lists them, with the names the plugin ships in the admin's language. */
const optionsOf = ( palette: Swatch[] ) => themeOptions( palette ).map( ( group ) => ( { ...group, colors: group.colors.map( ( option ) => ( { ...option, name: stockName( option.name ) } ) ) } ) );

/**
 * A color's popover, as the block editor's color dropdown: Light and Dark tabs with the color picker, the Theme and Clear.
 * The tabs only choose which value is edited (they do not change the preview); in the Dark tab, "no color" is said as what it does.
 */
function ColorPopover( { v, design, mode, onChange }: { v: ColorVar; design: Design; mode: Mode; onChange: ( v: ColorVar ) => void } ) {
	const palette = design.palette || [];
	const body = ( tab: { name: string } ) => {
		const which = tab.name as Mode;
		const own = which === 'dark' ? v.dark : v.light;
		const dark = which === 'dark';
		const seen = own ? shown( colorValue( design, own, which ) ) : undefined;
		return (
			<>
				<ColorPalette
					colors={ optionsOf( palette ) }
					value={ seen }
					selectedSlug={ own?.startsWith( '$' ) ? own.slice( 1 ) : undefined }
					enableAlpha
					clearable={ ! dark && !! own }
					// A color picked from the Theme is kept as a pointer to it ("$base"), a custom one by its value.
					onChange={ ( color, _index, slug ) => onChange( setRoleValue( v, which, slug ? `$${ slug }` : color ) ) }
				/>
				{ dark && (
					<div className="evcert-colors__dark-note">
						{ own ? (
							<Button variant="tertiary" onClick={ () => onChange( setRoleValue( v, 'dark' ) ) }>
								{ __( 'Use the Light color', 'events-certificates' ) }
							</Button>
						) : (
							<p className="evcert-meta">{ __( 'No color of its own in the dark mode: it uses the Light one.', 'events-certificates' ) }</p>
						) }
					</div>
				) }
			</>
		);
	};
	// Light only (the default): the one color picker, with no tabs.
	if ( ! darkEnabled() ) {
		return <div className="evcert-colors__plain">{ body( { name: 'light' } ) }</div>;
	}
	return (
		<TabPanel
			className="evcert-colors__tabs"
			initialTabName={ mode }
			tabs={ [
				{ name: 'light', title: __( 'Light', 'events-certificates' ) },
				{ name: 'dark', title: __( 'Dark', 'events-certificates' ) },
			] }
		>
			{ body }
		</TabPanel>
	);
}

/** A color's toggle: the pair of circles and the name, opening its popover. */
function ColorToggle( { v, design, mode, onChange }: { v: ColorVar; design: Design; mode: Mode; onChange: ( v: ColorVar ) => void } ) {
	return (
		<Dropdown
			className="evcert-colors__dropdown"
			popoverProps={ { placement: 'left-start', offset: 36, shift: true, className: 'evcert-colors__popover-wrap' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button className={ `evcert-colors__toggle${ isOpen ? ' is-open' : '' }` } onClick={ onToggle } aria-expanded={ isOpen }>
					<HStack justify="flex-start" spacing={ 2 }>
						<ColorPair v={ v } design={ design } />
						<span className="evcert-colors__name">{ nameOf( v ) }</span>
					</HStack>
				</Button>
			) }
			renderContent={ () => (
				<div className="evcert-colors__popover">
					<ColorPopover v={ v } design={ design } mode={ mode } onChange={ onChange } />
				</div>
			) }
		/>
	);
}

/** A group's header: its title on the left and, right after it, something extra (the dark-mode toggle); on the right (optionally) Done, + and a ⋮ menu. */
function GroupHeader( { title, details, onDone, onAdd, addLabel, afterTitle, menu }: { title: string; details?: boolean; onDone?: () => void; onAdd?: () => void; addLabel?: string; afterTitle?: React.ReactNode; menu?: ( close: () => void ) => React.ReactNode } ) {
	return (
		<HStack>
			<HStack justify="flex-start" spacing={ 1 } expanded={ false }>
				<h3 className="evcert-colors__subtitle">{ title }</h3>
				{ afterTitle }
			</HStack>
			<HStack justify="flex-end" spacing={ 0 } expanded={ false }>
				{ details && onDone && (
					<Button size="small" className="evcert-colors__done" onClick={ onDone }>
							{ __( 'Done', 'events-certificates' ) }
						</Button>
				) }
				{ onAdd && <Button size="small" icon={ plus } label={ addLabel } onClick={ onAdd } /> }
				{ menu && (
					<DropdownMenu icon={ moreVertical } toggleProps={ { size: 'small' } } label={ sprintf( /* translators: %s: the name of a group of colors. */ __( '%s options', 'events-certificates' ), title ) } popoverProps={ { placement: 'bottom-end' } }>
						{ ( { onClose } ) => menu( onClose ) }
					</DropdownMenu>
				) }
			</HStack>
		</HStack>
	);
}

/** The theme's swatches: the circles, or (in details) their names as editable text and a remove button. */
function ThemeGroup( { palette, colors, onPalette, onRename, extra }: { palette: Swatch[]; colors: Record< string, ColorVar >; onPalette: ( palette: Swatch[] ) => void; onRename: ( slug: string, name: string ) => void; extra?: React.ReactNode } ) {
	const [ details, setDetails ] = useState( false );
	const set = ( slug: string, patch: Partial< Swatch > ) => onPalette( palette.map( ( s ) => ( s.slug === slug ? { ...s, ...patch } : s ) ) );
	const picker = ( s: Swatch ) => (
		<Dropdown
			popoverProps={ { placement: 'left-start', offset: 36, shift: true } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button className="evcert-colors__swatch" onClick={ onToggle } aria-expanded={ isOpen } label={ sprintf( /* translators: %s: color name. */ __( 'Edit %s', 'events-certificates' ), labelOf( s ) ) } showTooltip>
					<ColorIndicator colorValue={ s.color } />
				</Button>
			) }
			renderContent={ () => (
				<div className="evcert-colors__popover">
					<ColorPicker enableAlpha color={ s.color } onChange={ ( color: string ) => set( s.slug, { color } ) } />
				</div>
			) }
		/>
	);
	// The circles, as the block editor's circular option picker draws them (28 px, 8 px apart), each opening its color picker.
	const circle = ( s: Swatch ) => (
		<Dropdown
			popoverProps={ { placement: 'left-start', offset: 36, shift: true } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					className="components-circular-option-picker__option evcert-colors__option"
					style={ { backgroundColor: s.color, color: s.color } }
					onClick={ onToggle }
					aria-expanded={ isOpen }
					label={ sprintf( /* translators: %s: color name. */ __( 'Edit %s', 'events-certificates' ), labelOf( s ) ) }
					showTooltip
				/>
			) }
			renderContent={ () => (
				<div className="evcert-colors__popover">
					<ColorPicker enableAlpha color={ s.color } onChange={ ( color: string ) => set( s.slug, { color } ) } />
				</div>
			) }
		/>
	);
	return (
		<VStack spacing={ 2 }>
			<GroupHeader
				title={ __( 'Theme', 'events-certificates' ) }
				details={ details }
				onDone={ () => setDetails( false ) }
				onAdd={ () => {
						onPalette( [ ...palette, nextSwatch( palette, colorName, colors ) ] );
						setDetails( true );
					} }
				addLabel={ __( 'Add color', 'events-certificates' ) }
				afterTitle={ extra }
				menu={
					palette.length
						? ( close ) => (
								<MenuGroup>
									{ ! details && (
										<MenuItem
											onClick={ () => {
												setDetails( true );
												close();
											} }
										>
											{ __( 'Show details', 'events-certificates' ) }
										</MenuItem>
									) }
									<MenuItem
										className="evcert-colors__remove"
										onClick={ () => {
											onPalette( [] );
											close();
										} }
									>
										{ __( 'Remove all colors', 'events-certificates' ) }
									</MenuItem>
								</MenuGroup>
						  )
						: undefined
				}
			/>
			{ details ? (
				<ItemGroup isRounded isBordered isSeparated>
					{ palette.map( ( s, i ) => (
						<Item key={ i } size="small" className="evcert-colors__swatch-row">
							<HStack justify="flex-start" spacing={ 2 }>
								{ picker( s ) }
								<div className="evcert-colors__swatch-name">
									<TextControl __nextHasNoMarginBottom hideLabelFromVision label={ sprintf( /* translators: %s: color name. */ __( 'Name of %s', 'events-certificates' ), labelOf( s ) ) } value={ labelOf( s ) } onChange={ ( name ) => onRename( s.slug, name ) } />
								</div>
								<Button size="small" icon={ lineSolid } label={ sprintf( /* translators: %s: color name. */ __( 'Remove color: %s', 'events-certificates' ), labelOf( s ) ) } onClick={ () => onPalette( palette.filter( ( o ) => o.slug !== s.slug ) ) } />
							</HStack>
						</Item>
					) ) }
				</ItemGroup>
			) : (
				<div className="components-circular-option-picker__swatches evcert-colors__swatches">
					{ palette.map( ( s ) => (
						<div key={ s.slug } className="components-circular-option-picker__option-wrapper">
							{ circle( s ) }
						</div>
					) ) }
				</div>
			) }
		</VStack>
	);
}

export default function ColorsSection( { design, mode, onMode, onChange }: Props ) {
	const [ notice, setNotice ] = useState( '' );
	const [ details, setDetails ] = useState( false );
	const palette = design.palette || [];
	const entries = Object.entries( design.colors );

	/** Sets the theme; a swatch that is removed leaves its color on the page that used it. */
	const setPalette = ( next: Swatch[] ) => {
		const gone = palette.filter( ( s ) => ! next.some( ( n ) => n.slug === s.slug ) );
		let kept: Design = { ...design, palette: next };
		for ( const s of gone ) {
			// What pointed at it keeps its color: the page's background and the colors of the Elements.
			kept = { ...kept, colors: replaceRefs( kept.colors, s.slug, s.color ), page: { ...kept.page, bg: replaceRefs( kept.page.bg as unknown, s.slug, s.color ) as Design[ 'page' ][ 'bg' ] } };
		}
		onChange( kept );
	};

	const setColor = ( key: string, v: ColorVar ) => onChange( { ...design, colors: { ...design.colors, [ key ]: v } } );

	/** Removes colors; what used them keeps their Light value, so nothing changes on paper. */
	const remove = ( keys: string[] ) => {
		let next = design;
		const used: string[] = [];
		for ( const key of keys ) {
			const v = next.colors[ key ];
			if ( ! v ) {
				continue;
			}
			// The page that used it keeps its Light value too.
			if ( next.page.bg === `$${key}` ) {
				next = { ...next, page: { ...next.page, bg: v.light } };
			}
			if ( usesColor( next, key ) ) {
				used.push( v.name );
				next = {
					...next,
					elements: replaceRefs( next.elements, key, v.light ),
					...( next.back ? { back: { ...next.back, elements: replaceRefs( next.back.elements, key, v.light ) } } : {} ),
				};
			}
			const { [ key ]: gone, ...rest } = next.colors;
			next = { ...next, colors: rest };
		}
		onChange( next );
		setNotice( used.length ? sprintf( /* translators: %s: color names. */ __( 'Elements that used %s now use its Light color.', 'events-certificates' ), used.join( ', ' ) ) : '' );
	};

	const add = () => {
		const [ key, v ] = nextColor( design.colors, colorName, palette );
		setColor( key, v );
		setDetails( true );
	};

	const darkMode = (
		<Button
			size="small"
			className="evcert-colors__mode"
			icon={ styles }
			isPressed={ mode === 'dark' }
			label={ __( 'Dark mode', 'events-certificates' ) }
			onClick={ () => onMode( mode === 'dark' ? 'light' : 'dark' ) }
		/>
	);

	return (
		<PanelBody title={ __( 'Colors', 'events-certificates' ) } initialOpen={ false } className="evcert-panel">
			<VStack spacing={ 4 } className="evcert-colors">
				{ notice && (
					<Notice status="info" onRemove={ () => setNotice( '' ) }>
						{ notice }
					</Notice>
				) }

				<ThemeGroup palette={ palette } colors={ design.colors } onPalette={ setPalette } onRename={ ( slug, name ) => onChange( renameSwatch( design, slug, name ) ) } extra={ darkEnabled() ? darkMode : undefined } />

				<VStack spacing={ 2 }>
					<GroupHeader
						title={ __( 'Elements', 'events-certificates' ) }
						details={ details }
						onDone={ () => setDetails( false ) }
						onAdd={ add }
						addLabel={ __( 'Add color', 'events-certificates' ) }
						menu={
							entries.length
								? ( close ) => (
										<MenuGroup>
											{ ! details && (
												<MenuItem
													onClick={ () => {
														setDetails( true );
														close();
													} }
												>
													{ __( 'Show details', 'events-certificates' ) }
												</MenuItem>
											) }
											<MenuItem
												className="evcert-colors__remove"
												onClick={ () => {
													remove( entries.map( ( [ k ] ) => k ) );
													close();
												} }
											>
												{ __( 'Remove all colors', 'events-certificates' ) }
											</MenuItem>
										</MenuGroup>
								  )
								: undefined
						}
					/>
					{ /* Every color may be renamed and removed: none is fixed. */ }
					{ entries.length > 0 && (
					<ItemGroup isRounded isBordered isSeparated>
						{ entries.map( ( [ key, v ], i ) => (
							<Item key={ i } className={ `evcert-colors__row${ details ? ' is-details' : '' }${ details ? ' has-field' : '' }` }>
								<HStack justify="flex-start" spacing={ 1 }>
									<ColorToggle v={ v } design={ design } mode={ mode } onChange={ ( next ) => setColor( key, next ) } />
									{ details && (
										<>
											<div className="evcert-colors__swatch-name">
												<TextControl __nextHasNoMarginBottom hideLabelFromVision label={ sprintf( /* translators: %s: color name. */ __( 'Name of %s', 'events-certificates' ), nameOf( v ) ) } value={ nameOf( v ) } onChange={ ( name ) => onChange( renameColor( design, key, name ) ) } />
											</div>
											<Button size="small" icon={ lineSolid } label={ sprintf( /* translators: %s: color name. */ __( 'Remove color: %s', 'events-certificates' ), nameOf( v ) ) } onClick={ () => remove( [ key ] ) } />
										</>
									) }
								</HStack>
							</Item>
						) ) }
					</ItemGroup>
					) }
				</VStack>
			</VStack>
		</PanelBody>
	);
}
