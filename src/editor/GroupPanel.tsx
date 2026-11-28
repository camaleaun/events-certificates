/**
 * The layout of a group, as the core's Group block says it: the variation (Group, Row, Stack, Grid, in that order), the space between what is in it and
 * around it, how it spreads along and across, and the columns of a grid. A group that is not a row, a stack or a grid is free: what is in it has its own place.
 */
import {
	CheckboxControl,
	RangeControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	grid,
	group as groupIcon,
	justifyBottom,
	justifyCenter,
	justifyCenterVertical,
	justifyLeft,
	justifyRight,
	justifySpaceBetween,
	justifyStretch,
	justifyStretchVertical,
	justifyTop,
	row,
	stack,
} from '@wordpress/icons';
import type { Element, GroupEl } from './model';

type Arrange = GroupEl[ 'arrange' ];
type Option = { value: string; label: string; icon: JSX.Element };

/** The variations of the core's Group, in its order. */
export const VARIATIONS: Array< { arrange: Arrange; label: string; icon: JSX.Element } > = [
	{ arrange: undefined, label: __( 'Group', 'events-certificates' ), icon: groupIcon },
	{ arrange: 'row', label: __( 'Row', 'events-certificates' ), icon: row },
	{ arrange: 'stack', label: __( 'Stack', 'events-certificates' ), icon: stack },
	{ arrange: 'grid', label: __( 'Grid', 'events-certificates' ), icon: grid },
];

export default function GroupPanel( { el, onChange }: { el: GroupEl; onChange: ( patch: Partial< Element > ) => void } ) {
	const arrange = el.arrange;
	const flowed = !! arrange;
	const vertical = arrange === 'stack';
	const set = ( patch: Partial< GroupEl > ) => onChange( patch as Partial< Element > );
	const field = ( key: 'gap' | 'padding', label: string ) => (
		<ToolsPanelItem label={ label } hasValue={ () => !! el[ key ] } onDeselect={ () => set( { [ key ]: undefined } ) } isShownByDefault>
			<NumberControl __next40pxDefaultSize label={ label } min={ 0 } step={ 1 } value={ el[ key ] ?? 0 } suffix={ <span className="evcert-suffix">pt</span> } onChange={ ( v ) => set( { [ key ]: Number( v ) > 0 ? Number( v ) : undefined } ) } />
		</ToolsPanelItem>
	);
	// Along the row (the width), or the stack (the height); across it the other way. What a grid has is its columns.
	const along: Option[] = vertical
		? [
				{ value: 'start', label: __( 'Justify items top', 'events-certificates' ), icon: justifyTop },
				{ value: 'center', label: __( 'Justify items center', 'events-certificates' ), icon: justifyCenterVertical },
				{ value: 'end', label: __( 'Justify items bottom', 'events-certificates' ), icon: justifyBottom },
				{ value: 'between', label: __( 'Space between items', 'events-certificates' ), icon: justifySpaceBetween },
		  ]
		: [
				{ value: 'start', label: __( 'Justify items left', 'events-certificates' ), icon: justifyLeft },
				{ value: 'center', label: __( 'Justify items center', 'events-certificates' ), icon: justifyCenter },
				{ value: 'end', label: __( 'Justify items right', 'events-certificates' ), icon: justifyRight },
				{ value: 'between', label: __( 'Space between items', 'events-certificates' ), icon: justifySpaceBetween },
		  ];
	const across: Option[] = vertical
		? [
				{ value: 'start', label: __( 'Align items left', 'events-certificates' ), icon: justifyLeft },
				{ value: 'center', label: __( 'Align items center', 'events-certificates' ), icon: justifyCenter },
				{ value: 'end', label: __( 'Align items right', 'events-certificates' ), icon: justifyRight },
				{ value: 'stretch', label: __( 'Stretch items', 'events-certificates' ), icon: justifyStretch },
		  ]
		: [
				{ value: 'start', label: __( 'Align items top', 'events-certificates' ), icon: justifyTop },
				{ value: 'center', label: __( 'Align items center', 'events-certificates' ), icon: justifyCenterVertical },
				{ value: 'end', label: __( 'Align items bottom', 'events-certificates' ), icon: justifyBottom },
				{ value: 'stretch', label: __( 'Stretch items', 'events-certificates' ), icon: justifyStretchVertical },
		  ];
	const toggle = ( label: string, key: 'justify' | 'align', options: Option[], fallback: string ) => (
		<ToolsPanelItem label={ label } hasValue={ () => !! el[ key ] && el[ key ] !== fallback } onDeselect={ () => set( { [ key ]: undefined } ) } isShownByDefault>
			<ToggleGroupControl __next40pxDefaultSize __nextHasNoMarginBottom isBlock label={ label } value={ el[ key ] || fallback } onChange={ ( v ) => set( { [ key ]: v === fallback ? undefined : v } as Partial< GroupEl > ) }>
				{ options.map( ( o ) => (
					<ToggleGroupControlOptionIcon key={ o.value } value={ o.value } icon={ o.icon } label={ o.label } />
				) ) }
			</ToggleGroupControl>
		</ToolsPanelItem>
	);
	return (
		<ToolsPanel label={ __( 'Layout', 'events-certificates' ) } resetAll={ () => set( { arrange: undefined, gap: undefined, padding: undefined, justify: undefined, align: undefined, columns: undefined, clip: undefined } ) } className="evcert-group-panel">
			<ToolsPanelItem label={ __( 'Type', 'events-certificates' ) } hasValue={ () => flowed } onDeselect={ () => set( { arrange: undefined } ) } isShownByDefault>
				<ToggleGroupControl __next40pxDefaultSize __nextHasNoMarginBottom isBlock label={ __( 'Type', 'events-certificates' ) } value={ arrange || 'group' } onChange={ ( v ) => set( { arrange: v === 'group' ? undefined : ( v as Arrange ) } ) }>
					{ VARIATIONS.map( ( o ) => (
						<ToggleGroupControlOptionIcon key={ o.label } value={ o.arrange || 'group' } icon={ o.icon } label={ o.label } />
					) ) }
				</ToggleGroupControl>
			</ToolsPanelItem>
			{ flowed && field( 'gap', __( 'Gap', 'events-certificates' ) ) }
			{ field( 'padding', __( 'Padding', 'events-certificates' ) ) }
			{ arrange === 'grid' && (
				<ToolsPanelItem label={ __( 'Columns', 'events-certificates' ) } hasValue={ () => !! el.columns && el.columns !== 2 } onDeselect={ () => set( { columns: undefined } ) } isShownByDefault>
					<RangeControl __next40pxDefaultSize __nextHasNoMarginBottom label={ __( 'Columns', 'events-certificates' ) } min={ 1 } max={ 12 } value={ el.columns ?? 2 } onChange={ ( v ) => set( { columns: v && v !== 2 ? v : undefined } ) } />
				</ToolsPanelItem>
			) }
			{ ( arrange === 'row' || arrange === 'stack' ) && toggle( __( 'Justification', 'events-certificates' ), 'justify', along, 'start' ) }
			{ flowed && toggle( __( 'Alignment', 'events-certificates' ), 'align', across, 'start' ) }
			<ToolsPanelItem label={ __( 'Clip content', 'events-certificates' ) } hasValue={ () => !! el.clip } onDeselect={ () => set( { clip: undefined } ) } isShownByDefault>
				<CheckboxControl __nextHasNoMarginBottom label={ __( 'Clip content', 'events-certificates' ) } help={ __( 'Cut what goes out of the group’s box.', 'events-certificates' ) } checked={ !! el.clip } onChange={ ( v ) => set( { clip: v ? true : undefined } ) } />
			</ToolsPanelItem>
		</ToolsPanel>
	);
}
