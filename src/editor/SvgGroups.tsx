/**
 * The fields of an SVG in the groups of the sidebar: the viewBox and the size in the first, the paths in the second. The sidebar of the
 * editor and the two SVG dialogs (their right column) render this, so they look the same (docs/decisions/0005).
 */
import { __experimentalHStack as HStack, __experimentalNumberControl as NumberControl } from '@wordpress/components';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { DropdownRow } from './DesignPanel';

/** The viewBox row: its width and height in the units of the drawing. */
export function ViewBoxRow( { vw, vh, onChange }: { vw: number; vh: number; onChange: ( patch: { vw?: number; vh?: number } ) => void } ) {
	return (
		<DropdownRow label={ __( 'ViewBox', 'events-certificates' ) } value={ `${ vw } × ${ vh }` }>
			{ () => (
				<HStack alignment="top">
					<NumberControl __next40pxDefaultSize label={ __( 'Width', 'events-certificates' ) } min={ 1 } value={ vw } onChange={ ( v ) => Number( v ) > 0 && onChange( { vw: Number( v ) } ) } />
					<NumberControl __next40pxDefaultSize label={ __( 'Height', 'events-certificates' ) } min={ 1 } value={ vh } onChange={ ( v ) => Number( v ) > 0 && onChange( { vh: Number( v ) } ) } />
				</HStack>
			) }
		</DropdownRow>
	);
}

export default function SvgGroups( { viewBox, size, paths }: { viewBox: ReactNode; size: ReactNode; paths: ReactNode } ) {
	return (
		<div className="evcert-svg-panel">
			<div className="evcert-inspector__section">
				{ viewBox }
				{ size }
			</div>
			<div className="evcert-inspector__section">{ paths }</div>
		</div>
	);
}
