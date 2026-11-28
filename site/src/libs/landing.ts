// One place decides what a page says for a scenario in a language: availability (the catalog's `where`) AND prominence
// (the language's rules). The pages, the Markdown twins, llms.txt, the comparison table and the "what works where" page all call this.
import { features, prominenceOf, type Feature, type ScenarioId, type Where, type WhereKey } from '@data/catalog';
import { scenarios, scenarioIds, type Scenario } from '@data/scenarios';
import { featureOf, scenarioOf } from './strings';

export type { ScenarioId };
export interface Item {
	id: string;
	feature: Feature;
}

const available = ( feature: Feature, key: WhereKey ) => feature.where[ key ] !== 'no';

/** The features of a scenario in a language, loud ones and quiet ones, in the scenario's order. */
export function landingFor( id: ScenarioId, locale = 'en' ) {
	const scenario: Scenario = scenarioOf( id, locale );
	const pick = ( ids: string[], wanted: 'highlight' | 'secondary' ): Item[] =>
		ids
			.filter( ( key ) => features[ key ] && available( features[ key ], id ) && prominenceOf( features[ key ], locale ) === wanted )
			.map( ( key ) => ( { id: key, feature: featureOf( key, locale ) } ) );
	// A feature the language makes loud but the scenario lists as secondary is promoted; one it hides is dropped.
	const every = [ ...scenario.order.highlight, ...scenario.order.secondary ];
	return {
		scenario,
		scenarioId: id,
		pains: scenario.order.pains.map( ( key ) => ( { id: key, ...scenario.pains[ key ] } ) ),
		steps: scenario.order.steps.map( ( key ) => ( { id: key, text: scenario.steps[ key ] } ) ),
		highlight: pick( every, 'highlight' ),
		secondary: pick( every, 'secondary' ),
		limits: scenario.order.limits.map( ( key ) => ( { id: key, text: scenario.limits[ key ] } ) ),
	};
}

/** Rows of the comparison and the matrix: every feature visible in the language, with where it works. */
export function matrix( locale = 'en', keys: WhereKey[] = [ ...scenarioIds, 'both' ] ) {
	return Object.entries( features )
		.filter( ( [ , feature ] ) => prominenceOf( feature, locale ) !== 'hidden' )
		.map( ( [ id ] ) => ( { id, feature: featureOf( id, locale ), cells: keys.map( ( key ) => ( { key, where: features[ id ].where[ key ] as Where } ) ) } ) );
}

export const whereText = ( where: Where, strings = { yes: 'Yes', no: 'No', yesNote: 'Yes: {note}' } ): string =>
	where === 'yes' ? strings.yes : where === 'no' ? strings.no : strings.yesNote.replace( '{note}', where.note );

/** A landing as Markdown, for agents: the same data as the page. */
export function landingMarkdown( id: ScenarioId, locale = 'en' ): string {
	const page = landingFor( id, locale );
	const { hero } = page.scenario;
	return [
		`# ${ hero.title }`,
		'',
		hero.lead,
		'',
		`For: ${ page.scenario.audience } ${ hero.note }`,
		'',
		'## The problem',
		'',
		...page.pains.map( ( pain ) => `- **${ pain.title }.** ${ pain.text }` ),
		'',
		'## In three steps',
		'',
		...page.steps.map( ( step, i ) => `${ i + 1 }. ${ step.text }` ),
		'',
		...page.highlight.flatMap( ( { feature } ) => [ `## ${ feature.title }`, '', feature.text, feature.claimNote ? `\n*${ feature.claimNote }*` : '', '' ] ),
		'## Also included',
		'',
		...page.secondary.map( ( { feature } ) => `- **${ feature.title }.** ${ feature.text }${ feature.claimNote ? ` ${ feature.claimNote }` : '' }` ),
		'',
		'## What it does not do',
		'',
		...page.limits.map( ( limit ) => `- ${ limit.text }` ),
	].join( '\n' );
}
