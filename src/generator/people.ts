/**
 * The Generator screen's list of people, with no WordPress in it so it can be tested alone
 * (tests/js/generator-people.test.mjs): merging what the route answers, selecting, and what is
 * still missing before a download can start.
 */

export type Status = 'attended' | 'no_show';

/** A person as the route answers (includes/generator.php, listed_people()). */
export type Incoming = {
	name: string;
	email?: string;
	roles?: string[];
	role_labels?: string[];
	status?: Status;
};

/** A row of the list. `on` is the checkbox; who did not attend starts unchecked. */
export type Person = {
	id: number;
	name: string;
	email: string;
	roles: string[];
	roleLabels: string[];
	status: Status;
	on: boolean;
};

export type Merged = { people: Person[]; added: number; nextId: number };

const same = ( a: string, b: string ) => a.trim().toLowerCase() === b.trim().toLowerCase();

/** Adds the people not in the list yet (the same name, any case, is one person); the new ones are numbered from `nextId`. */
export function mergePeople( list: Person[], incoming: Incoming[], nextId: number ): Merged {
	const people = [ ...list ];
	let added = 0;
	for ( const one of incoming ) {
		const name = ( one.name || '' ).trim();
		if ( '' === name || people.some( ( p ) => same( p.name, name ) ) ) {
			continue;
		}
		const status: Status = 'no_show' === one.status ? 'no_show' : 'attended';
		people.push( {
			id: nextId++,
			name,
			email: one.email || '',
			roles: one.roles || [],
			roleLabels: one.role_labels || [],
			status,
			on: 'attended' === status,
		} );
		added++;
	}
	return { people, added, nextId };
}

export const setAll = ( list: Person[], on: boolean ): Person[] => list.map( ( p ) => ( { ...p, on } ) );

export const countSelected = ( list: Person[] ): number => list.filter( ( p ) => p.on ).length;

export const selectedPeople = ( list: Person[] ): Person[] => list.filter( ( p ) => p.on );

export type Need = 'title' | 'date' | 'people' | 'current';

/**
 * What is still missing before a download: the event's title and date, and who to issue for
 * (the checked people, or the current one for "just this one").
 */
export function whatIsMissing( values: Record< string, string >, selected: number, hasCurrent: boolean, justOne: boolean ): Need[] {
	const missing: Need[] = [];
	if ( '' === ( values.title || '' ).trim() ) {
		missing.push( 'title' );
	}
	if ( '' === ( values.date || '' ).trim() ) {
		missing.push( 'date' );
	}
	if ( justOne ? ! hasCurrent : selected < 1 ) {
		missing.push( justOne ? 'current' : 'people' );
	}
	return missing;
}
