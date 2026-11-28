/**
 * The Certificate tab of the sidebar, like the block editor's document panel
 * (editor/src/components/post-card-panel + post-panel-row): a card with icon,
 * name and status, then label/value rows whose value opens a dropdown, and the
 * colors as a bordered, separated item group like the global styles palette.
 */
import {
	Button,
	Dropdown,
	PanelBody,
	SelectControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalNumberControl as NumberControl,
	__experimentalUnitControl as UnitControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption, CheckboxControl, __experimentalVStack as VStack, RadioControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, page as pageIcon } from '@wordpress/icons';
import ColorsSection from './ColorsSection';
import { pageSize } from '@camaleaun/draw';
import { ColorChoices, colorLabel } from './ColorChoices';
import { SIZE_UNITS } from './SizeRow';
import { lengthIn, unitOf } from './size';
import type { LengthUnit } from './model';
import type { Design, Mode } from './model';

const MM = 2.83465;
const POINTS: Record< LengthUnit, number > = { pt: 1, px: 1, mm: MM, cm: MM * 10, in: 72 };

/**
 * A margin or a bleed in the unit the person chose (mm by default), with decimals. Choosing another unit keeps the length and says it in
 * that unit (10mm is 28.35pt); typing changes the length in the unit it is in.
 */
function Margin( { label, pt, unit, onChange }: { label: string; pt: number; unit: LengthUnit; onChange: ( pt: number, unit: LengthUnit ) => void } ) {
	return (
		<UnitControl step="any" spinFactor={ 0.1 }
			// Another unit makes the field again: it would keep the number it had (10) for the same length in the new unit (28.35).
			key={ unit }
			__next40pxDefaultSize
			label={ label }
			min={ 0 }
			units={ SIZE_UNITS.filter( ( u ) => u.value !== '%' ) }
			value={ lengthIn( pt, unit ) }
			onChange={ ( v ) => {
				if ( v === undefined || v === '' ) {
					onChange( 0, unit );
					return;
				}
				const next = unitOf( v ) as LengthUnit;
				onChange( next !== unit ? pt : ( Number.parseFloat( v ) || 0 ) * POINTS[ unit ], next );
			} }
		/>
	);
}

/** Fields whose preview value can be changed (the others come from the site or the certificate). */
export const PREVIEW_FIELDS = (): Array< [ string, string ] > => [
	[ 'name', __( 'Participant', 'events-certificates' ) ],
	[ 'event', __( 'Event', 'events-certificates' ) ],
	[ 'location', __( 'Location', 'events-certificates' ) ],
	[ 'hours', __( 'Workload (hours)', 'events-certificates' ) ],
	[ 'role', __( 'Role', 'events-certificates' ) ],
	[ 'details', __( 'Details', 'events-certificates' ) ],
	[ 'signer_name', __( 'Signer', 'events-certificates' ) ],
	[ 'signer_role', __( 'Signer role', 'events-certificates' ) ],
];
const SIZES = {
	a4: { w: 841.89, h: 595.28 },
	letter: { w: 792, h: 612 },
};

/** A paper's name: A4, and the letter in the site's language (Carta). */
const paperName = ( key: keyof typeof SIZES ): string => ( key === 'letter' ? __( 'Letter', 'events-certificates' ) : 'A4' );

/** The page's size in the unit it is kept in (297 × 210 mm, 11 × 8.5 in; points only when it is round in neither). */
function sizeText( w: number, h: number ): string {
	const [ width, height ] = pageSize( w, h );
	const unit = height.replace( /^[\d.]+/, '' );
	return sprintf( /* translators: 1: width, 2: height, 3: unit (mm, in or pt). */ __( '%1$s × %2$s %3$s', 'events-certificates' ), width.replace( unit, '' ), height.replace( unit, '' ), unit );
}

type Props = {
	design: Design;
	mode: Mode;
	title: string;
	status: string;
	// The template's conditions (Templates screen): the roles it applies to; the default one takes everyone else.
	roles: string[];
	roleLabels: Record< string, string >;
	isDefault: boolean;
	onRoles: ( roles: string[] ) => void;
	sample: Record< string, string >;
	defaults: Record< string, string >;
	onTitle: ( title: string ) => void;
	onChange: ( design: Design ) => void;
	// The preview mode (the editor's, shared with its top bar).
	onMode: ( mode: Mode ) => void;
};

function Row( { label, children }: { label: string; children: React.ReactNode } ) {
	return (
		<HStack className="evcert-panel-row">
			<div className="evcert-panel-row__label">{ label }</div>
			<div className="evcert-panel-row__control">{ children }</div>
		</HStack>
	);
}

/** A row whose value is a tertiary button opening a dropdown, like PostStatus/PostSchedule. */
/** A row with a label and a value that opens a popover; `plain` leaves the popover's padding and width to what it shows (a color picker is 260px with its own 16px). */
export function DropdownRow( { label, value, children, plain = false }: { label: string; value: React.ReactNode; children: ( close: () => void ) => React.ReactNode; plain?: boolean } ) {
	return (
		<Row label={ label }>
			<Dropdown
				popoverProps={ { placement: 'left-start', offset: 36, shift: true, ...( plain ? { className: 'evcert-colors__popover-wrap' } : {} ) } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button size="compact" variant="tertiary" onClick={ onToggle } aria-expanded={ isOpen }>
						{ value }
					</Button>
				) }
				renderContent={ ( { onClose } ) => ( plain ? <>{ children( onClose ) }</> : <div className="evcert-row-popover">{ children( onClose ) }</div> ) }
			/>
		</Row>
	);
}

export default function DesignPanel( { design, mode, title, status, roles, roleLabels, isDefault, onRoles, sample, defaults, onTitle, onChange, onMode }: Props ) {
	const { page } = design;
	const landscape = page.w >= page.h;
	const sizeKey = ( Object.keys( SIZES ) as Array< keyof typeof SIZES > ).find( ( k ) => ( [ SIZES[ k ].w, SIZES[ k ].h ].sort().join() === [ page.w, page.h ].sort().join() ) ) || 'a4';
	const setPage = ( patch: Partial< Design[ 'page' ] > ) => onChange( { ...design, page: { ...page, ...patch } } );

	return (
		<div className="evcert-design-panel">
			<div className="evcert-card">
				<span className="evcert-card__icon">
					<Icon icon={ pageIcon } />
				</span>
				<div>
					<h2 className="evcert-card__title">{ title || __( 'Certificate', 'events-certificates' ) }</h2>
					<p className="evcert-card__description">{ status }</p>
				</div>
			</div>

			<div className="evcert-inspector__section">
				<DropdownRow label={ __( 'Name', 'events-certificates' ) } value={ title || __( 'Certificate', 'events-certificates' ) }>
					{ () => <TextControl __next40pxDefaultSize __nextHasNoMarginBottom label={ __( 'Name', 'events-certificates' ) } value={ title } onChange={ onTitle } /> }
				</DropdownRow>
				<DropdownRow
					label={ __( 'Verification', 'events-certificates' ) }
					value={ design.verifyFirstPage === 'without-declaration' ? __( 'On the declaration page', 'events-certificates' ) : __( 'On the first page', 'events-certificates' ) }
				>
					{ () => (
						<RadioControl
							label={ __( 'QR code, code and hint', 'events-certificates' ) }
							selected={ design.verifyFirstPage === 'without-declaration' ? 'without-declaration' : 'always' }
							options={ [
								{ label: __( 'Always on the first page', 'events-certificates' ), value: 'always' },
								{ label: __( 'On the declaration page when there is one', 'events-certificates' ), value: 'without-declaration' },
							] }
							onChange={ ( v ) => onChange( { ...design, verifyFirstPage: v === 'without-declaration' ? 'without-declaration' : undefined } ) }
						/>
					) }
				</DropdownRow>
				<DropdownRow
					label={ __( 'Applies to', 'events-certificates' ) }
					value={ isDefault ? __( 'Everyone else', 'events-certificates' ) : roles.map( ( r ) => roleLabels[ r ] ).filter( Boolean ).join( ', ' ) || __( 'Nobody yet', 'events-certificates' ) }
				>
					{ () =>
						isDefault ? (
							<p className="evcert-meta">{ __( 'The default template: certificates no other template applies to.', 'events-certificates' ) }</p>
						) : (
							<VStack spacing={ 2 }>
								{ Object.entries( roleLabels ).map( ( [ key, label ] ) => (
									<CheckboxControl
										key={ key }
										__nextHasNoMarginBottom
										label={ label }
										checked={ roles.includes( key ) }
										onChange={ ( on ) => onRoles( on ? [ ...roles, key ].filter( ( r, i, all ) => all.indexOf( r ) === i ) : roles.filter( ( r ) => r !== key ) ) }
									/>
								) ) }
							</VStack>
						)
					}
				</DropdownRow>
				<DropdownRow label={ __( 'Size', 'events-certificates' ) } value={ `${ paperName( sizeKey ) } · ${ landscape ? __( 'Landscape', 'events-certificates' ) : __( 'Portrait', 'events-certificates' ) }` }>
					{ () => (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Paper', 'events-certificates' ) }
								value={ sizeKey }
								options={ ( Object.keys( SIZES ) as Array< keyof typeof SIZES > ).map( ( k ) => ( { label: paperName( k ), value: k } ) ) }
								onChange={ ( k ) => {
									const s = SIZES[ k as keyof typeof SIZES ];
									setPage( landscape ? { w: s.w, h: s.h } : { w: s.h, h: s.w } );
								} }
							/>
							<ToggleGroupControl __next40pxDefaultSize __nextHasNoMarginBottom isBlock label={ __( 'Orientation', 'events-certificates' ) } value={ landscape ? 'landscape' : 'portrait' } onChange={ ( v ) => ( ( v === 'landscape' ) !== landscape ? setPage( { w: page.h, h: page.w } ) : null ) }>
								<ToggleGroupControlOption value="landscape" label={ __( 'Landscape', 'events-certificates' ) } />
								<ToggleGroupControlOption value="portrait" label={ __( 'Portrait', 'events-certificates' ) } />
							</ToggleGroupControl>
							<p className="evcert-meta">{ sizeText( page.w, page.h ) }</p>
						</>
					) }
				</DropdownRow>
				<DropdownRow label={ __( 'Safe margin', 'events-certificates' ) } value={ lengthIn( page.safe, page.safeUnit || 'mm' ) }>
					{ () => <Margin label={ __( 'Safe margin', 'events-certificates' ) } pt={ page.safe } unit={ page.safeUnit || 'mm' } onChange={ ( safe, safeUnit ) => setPage( { safe, safeUnit } ) } /> }
				</DropdownRow>
				{ /* The page is not an element: its color is chosen here, from the Theme or the Elements (the Background by default). */ }
				<DropdownRow plain label={ __( 'Background', 'events-certificates' ) } value={ colorLabel( design, page.bg as string ) }>
					{ () => <ColorChoices theme value={ typeof page.bg === 'string' ? page.bg : '$background' } clearTo="$background" design={ design } mode={ mode } onChange={ ( bg ) => setPage( { bg } ) } /> }
				</DropdownRow>
				<DropdownRow label={ __( 'Bleed', 'events-certificates' ) } value={ lengthIn( page.bleed, page.bleedUnit || 'mm' ) }>
					{ () => <Margin label={ __( 'Bleed', 'events-certificates' ) } pt={ page.bleed } unit={ page.bleedUnit || 'mm' } onChange={ ( bleed, bleedUnit ) => setPage( { bleed, bleedUnit } ) } /> }
				</DropdownRow>
			</div>

			<ColorsSection design={ design } mode={ mode } onMode={ onMode } onChange={ onChange } />

			<PanelBody title={ __( 'Preview data', 'events-certificates' ) } initialOpen={ false } className="evcert-panel">
				{ PREVIEW_FIELDS().map( ( [ key, label ] ) => (
					<DropdownRow key={ key } label={ label } value={ sample[ key ] || '—' }>
						{ () => (
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ label }
								// Your value; empty means the default (the signer, the event…), shown as the placeholder.
								value={ design.sample?.[ key ] || '' }
								placeholder={ defaults[ key ] || '' }
								onChange={ ( v ) => onChange( { ...design, sample: setSample( design.sample, key, v ) } ) }
							/>
						) }
					</DropdownRow>
				) ) }
				{ design.sample && Object.values( design.sample ).some( ( v ) => v !== '' ) && (
					<Button variant="link" onClick={ () => onChange( { ...design, sample: undefined } ) }>
						{ __( 'Reset preview data', 'events-certificates' ) }
					</Button>
				) }
			</PanelBody>
		</div>
	);
}

/** Sets one preview value; an empty one goes back to the default. */
export function setSample( current: Record< string, string > | undefined, key: string, value: string ): Record< string, string > | undefined {
	const next = { ...( current || {} ), [ key ]: value };
	if ( ! value ) {
		delete next[ key ];
	}
	return Object.keys( next ).length ? next : undefined;
}
