/**
 * A line height or a letter spacing with its unit. Empty means none (what none is, is the placeholder); the unit it is typed in is the one chosen, `defaultUnit` until then. A
 * number from an earlier file is a line height `lh` (a multiplier) or a letter spacing in points.
 */
import { __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { parseTextLength } from '@camaleaun/draw';

type Unit = { value: string; label: string; step?: number };
export const LINE_HEIGHT_UNITS: Unit[] = [
	// "-": no unit, the multiplier of the size the line height has always been (1.2).
	{ value: '-', label: '-', step: 0.05 },
	{ value: '%', label: '%', step: 1 },
	{ value: 'px', label: 'px', step: 0.5 },
	{ value: 'pt', label: 'pt', step: 0.5 },
	{ value: 'mm', label: 'mm', step: 0.1 },
	{ value: 'cm', label: 'cm', step: 0.01 },
	{ value: 'in', label: 'in', step: 0.01 },
];
export const LETTER_SPACING_UNITS: Unit[] = [
	{ value: '%', label: '%', step: 0.5 },
	{ value: 'px', label: 'px', step: 0.05 },
	{ value: 'pt', label: 'pt', step: 0.05 },
	{ value: 'mm', label: 'mm', step: 0.01 },
	{ value: 'cm', label: 'cm', step: 0.001 },
	{ value: 'in', label: 'in', step: 0.001 },
];

const NONE = '-';

/** The unit a stored value is in, as the field says it: a number, or a text with no unit, is "-" for a line height (a multiplier) and a percentage for a letter spacing. */
function unitOfValue( value: number | string | undefined, defaultUnit: string ): string {
	if ( value === undefined || value === '' ) {
		return '';
	}
	const unit = typeof value === 'number' ? '' : parseTextLength( value )?.unit ?? '';
	if ( typeof value === 'number' ) {
		return defaultUnit === NONE ? NONE : 'pt';
	}
	return unit === '' || unit === 'lh' ? defaultUnit : unit;
}

/** A stored value as the text the field shows ("120%", "1.2"): a number is what files wrote before (a multiplier for a line height, points for a spacing). */
function shown( value: number | string | undefined, defaultUnit: string ): string {
	if ( value === undefined || value === '' ) {
		return '';
	}
	const unit = unitOfValue( value, defaultUnit );
	const n = typeof value === 'number' ? value : parseTextLength( value )?.n;
	return n === undefined ? '' : unit === NONE ? String( n ) : `${ n }${ unit }`;
}

export default function TypeLength( { label, value, units, defaultUnit, placeholder, onChange }: { label: string; value: number | string | undefined; units: Unit[]; defaultUnit: string; placeholder: string; onChange: ( value: number | string | undefined ) => void } ) {
	// The unit chosen while the field is empty (there is no value to carry it): used for what is typed next.
	const [ chosen, setChosen ] = useState( defaultUnit );
	const text = shown( value, defaultUnit );
	const unit = text ? unitOfValue( value, defaultUnit ) : chosen;
	return (
		<UnitControl
			// Another unit makes the field again: it would keep the number it had.
			key={ unit }
			__next40pxDefaultSize
			label={ label }
			units={ units }
			unit={ unit }
			value={ text }
			placeholder={ placeholder }
			onUnitChange={ ( next ) => setChosen( next || defaultUnit ) }
			onChange={ ( next ) => {
				// "1.2-" is a number with no unit (the control writes the unit after the number).
				const m = next ? /^\s*(-?\d*\.?\d+)\s*(%|px|pt|mm|cm|in|-)?\s*$/.exec( next ) : null;
				if ( ! m ) {
					onChange( undefined );
					return;
				}
				const chosenUnit = m[ 2 ] || chosen;
				setChosen( chosenUnit );
				onChange( chosenUnit === NONE ? Number( m[ 1 ] ) : `${ m[ 1 ] }${ chosenUnit }` );
			} }
		/>
	);
}
