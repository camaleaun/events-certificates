/**
 * What the text editor does with the clipboard (docs/decisions/0054): a copy says the selected runs as text (`Dear [name], held in *[location]*`) and a paste reads
 * text as runs, with the grammar of camaleaun/draw, so a field comes back as a chip and a mark as a mark. The parts that need no DOM are here, to be tested.
 */
import { parseRuns, runsToText } from '@camaleaun/draw';
import type { Run } from './model';

/** The runs a pasted text is: the grammar of the written text, only the keys of the fields being fields. */
export const runsFromText = ( text: string, keys: string[] ): Run[] => parseRuns( text.replace( /\r\n?/g, '\n' ), { fields: keys } ) as Run[];

/** Runs as the text a copy puts on the clipboard. */
export const textFromRuns = ( runs: Run[] ): string => runsToText( runs as never );

/**
 * Pasted rich text (from another page) keeps its marks and its line breaks, and a `[field]` written in it is a field: the runs of the HTML with only the fields
 * of their text read (an asterisk in it is an asterisk, as its bold is already a mark).
 */
export function fieldsInRuns( runs: Run[], keys: string[] ): Run[] {
	const out: Run[] = [];
	const push = ( run: Run ) => {
		const last = out[ out.length - 1 ];
		if ( last && 'text' in last && 'text' in run && !! last.b === !! run.b && !! last.i === !! run.i ) {
			last.text += run.text;
			return;
		}
		out.push( run );
	};
	for ( const run of runs ) {
		if ( ! ( 'text' in run ) ) {
			push( run );
			continue;
		}
		const marks = { ...( run.b ? { b: true } : {} ), ...( run.i ? { i: true } : {} ) };
		let rest = run.text;
		for ( let m = /\[([A-Za-z0-9_-]{1,40})(?::([A-Za-z0-9_-]{1,40}))?\]/.exec( rest ); m; m = /\[([A-Za-z0-9_-]{1,40})(?::([A-Za-z0-9_-]{1,40}))?\]/.exec( rest ) ) {
			if ( keys.includes( m[ 1 ] ) ) {
				if ( m.index ) {
					push( { text: rest.slice( 0, m.index ), ...marks } );
				}
				push( { field: m[ 1 ], ...( m[ 2 ] && m[ 2 ] !== 'site' ? { format: m[ 2 ] } : {} ), ...marks } );
			} else {
				push( { text: rest.slice( 0, m.index + m[ 0 ].length ), ...marks } );
			}
			rest = rest.slice( m.index + m[ 0 ].length );
		}
		if ( rest ) {
			push( { text: rest, ...marks } );
		}
	}
	return out;
}

/** The tags that begin a new line of their own (a `div` the browser makes when Enter is pressed, a pasted paragraph). */
export const BLOCKS = [ 'DIV', 'P', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ];
