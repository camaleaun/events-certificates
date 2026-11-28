// What the home (the hub) says, besides what the catalog and the scenarios say. Keyed by id; order lives in `order`.
export const hub = {
	hero: {
		title: 'Every attendee gets their certificate. You keep your evening.',
		lead: 'Events Certificates turns the list of who came into a PDF for each person, inside WordPress: from your own list, from GatherPress, or from a WordCamp ticket.',
		primary: 'Try it in your browser',
		secondary: 'Read the docs',
		tertiary: 'Get it on GitHub',
	},
	pains: {
		manual: { title: 'One by one, by hand', text: 'Names go from a list into a design tool and out as a PDF, one person at a time.' },
		fix: { title: 'A typo means starting over', text: 'A wrong name is a new file to make, send and explain.' },
		proof: { title: 'Hard to prove later', text: 'A PDF alone cannot say it is the one you issued.' },
	},
	order: { pains: [ 'manual', 'fix', 'proof' ], core: [ 'template-editor', 'roles', 'records-not-files' ] },
	which: { title: 'Which one is you?', lead: 'The plugin adapts to where your events live. Pick the case that is yours.' },
	common: { title: 'The same engine everywhere' },
	compare: { title: 'What works where', lead: 'Facts from the code, not promises. "Yes:" explains a difference.' },
	finale: { title: 'See it with your own eyes', text: 'Open a demo in your browser: nothing to install.' },
};
