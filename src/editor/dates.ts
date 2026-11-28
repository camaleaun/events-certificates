/**
 * Dates as PHP's date() writes them: the formats of Settings → General (and the one the date of emission writes), for the editor's preview and the quick generators.
 */

/** A date or time as PHP's date() writes it (the formats of Settings → General), with the names of the language. */
export function phpDate( format: string, d: Date, locale: string ): string {
	const ordinal = ( n: number ) => ( n % 100 >= 11 && n % 100 <= 13 ? 'th' : ( { 1: 'st', 2: 'nd', 3: 'rd' } as Record< number, string > )[ n % 10 ] || 'th' );
	const pad = ( n: number ) => String( n ).padStart( 2, '0' );
	const name = ( options: Intl.DateTimeFormatOptions ) => new Intl.DateTimeFormat( locale, options ).format( d );
	const hours12 = d.getHours() % 12 || 12;
	const period = () => {
		const part = new Intl.DateTimeFormat( locale, { hour: 'numeric', hour12: true } ).formatToParts( d ).find( ( p ) => 'dayPeriod' === p.type );
		return part ? part.value : d.getHours() < 12 ? 'AM' : 'PM';
	};
	let out = '';
	for ( let i = 0; i < format.length; i++ ) {
		const c = format[ i ];
		switch ( c ) {
			case '\\': out += format[ ++i ] || ''; break;
			case 'd': out += pad( d.getDate() ); break;
			case 'j': out += d.getDate(); break;
			case 'D': out += name( { weekday: 'short' } ); break;
			case 'l': out += name( { weekday: 'long' } ); break;
			case 'm': out += pad( d.getMonth() + 1 ); break;
			case 'n': out += d.getMonth() + 1; break;
			case 'M': out += name( { month: 'short' } ); break;
			case 'F': out += name( { month: 'long' } ); break;
			case 'Y': out += d.getFullYear(); break;
			case 'y': out += pad( d.getFullYear() % 100 ); break;
			case 'H': out += pad( d.getHours() ); break;
			case 'G': out += d.getHours(); break;
			case 'h': out += pad( hours12 ); break;
			case 'g': out += hours12; break;
			case 'i': out += pad( d.getMinutes() ); break;
			case 's': out += pad( d.getSeconds() ); break;
			case 'a': out += period().toLowerCase(); break;
			case 'A': out += period().toUpperCase(); break;
			case 'S': out += ordinal( d.getDate() ); break;
			case 'N': out += d.getDay() || 7; break;
			case 'w': out += d.getDay(); break;
			case 'z': out += Math.round( ( Date.UTC( d.getFullYear(), d.getMonth(), d.getDate() ) - Date.UTC( d.getFullYear(), 0, 1 ) ) / 864e5 ); break;
			case 't': out += new Date( d.getFullYear(), d.getMonth() + 1, 0 ).getDate(); break;
			case 'L': out += new Date( d.getFullYear(), 1, 29 ).getMonth() === 1 ? 1 : 0; break;
			case 'U': out += Math.floor( d.getTime() / 1000 ); break;
			default: out += c;
		}
	}
	return out;
}
