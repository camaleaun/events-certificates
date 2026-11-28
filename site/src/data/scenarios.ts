// The three scenarios, each a landing page built from the catalog. Everything translatable is keyed by id; the order lives in `order`
// (ids, never translated), so a translation cannot shift when something is added. Plain TypeScript: scripts/check-prominence.mjs imports it.
import type { ScenarioId } from './catalog.ts';

export interface Scenario {
	id: ScenarioId;
	/** Shown name. */
	name: string;
	audience: string;
	hero: { title: string; lead: string; primary: string; note: string };
	pains: Record< string, { title: string; text: string } >;
	steps: Record< string, string >;
	order: { pains: string[]; steps: string[]; highlight: string[]; secondary: string[]; limits: string[] };
	/** Where it is not like the others, said plainly. */
	limits: Record< string, string >;
	/** A picture of this case in place of the feature's own (a shot of the Standalone menus would mislead here); '' shows none. */
	shots?: Record< string, string >;
	docs: string;
}

export const scenarios: Record< ScenarioId, Scenario > = {
	standalone: {
		id: 'standalone',
		name: 'Standalone',
		audience: 'Meetups, courses and workshops with no event plugin.',
		hero: {
			title: 'Paste the names. Download the certificates.',
			lead: 'Events Certificates keeps your events, people and attendance on its own: no other plugin needed. Bring a Meetup export or type the names, mark who came, and hand out a PDF for each.',
			primary: 'Try Standalone in your browser',
			note: 'WordPress 7.1 or later.',
		},
		pains: {
			list: { title: 'The list is in another tool', text: 'Names live in a spreadsheet or a Meetup export, and the certificates in a design app, copied one by one.' },
			names: { title: 'Typos, one by one', text: 'Fixing a name means opening a file, editing and exporting again.' },
			proof: { title: 'Nothing proves it later', text: 'A PDF in an inbox cannot say it is the one you issued.' },
		},
		steps: {
			settings: 'Certificates › Settings: set "Signed by" and the signature.',
			people: 'Certificates › Events › Add event, then Add participants: paste names or upload a CSV, and tick "They all attended".',
			issue: 'Issue certificates, or open Certificates › Generator and download one PDF or a ZIP.',
		},
		order: {
			pains: [ 'list', 'names', 'proof' ],
			steps: [ 'settings', 'people', 'issue' ],
			highlight: [ 'generator-events', 'quick-admin', 'public-generator', 'meetup-import', 'roles', 'template-editor' ],
			secondary: [ 'authentication', 'starter-templates', 'fonts', 'records-not-files', 'dark-mode', 'share', 'multi-language', 'export-import-lists', 'template-export-import', 'abilities-cli' ],
			limits: [ 'email', 'pdf400', 'nocode' ],
		},
		limits: { email: 'It does not e-mail certificates.', pdf400: 'One PDF download holds up to 400 certificates.', nocode: 'Certificates from the public page and the Quick generator have no code.' },
		docs: 'start/introduction',
	},
	gatherpress: {
		id: 'gatherpress',
		name: 'GatherPress',
		audience: 'Communities that run their events with GatherPress.',
		hero: {
			title: 'Who RSVP’d and checked in gets a certificate.',
			lead: 'Your GatherPress events already know who said yes and who showed up. Events Certificates reads that, and gives each person a certificate, from inside GatherPress.',
			primary: 'Try with GatherPress in your browser',
			note: 'Tested with GatherPress 0.37.0.',
		},
		pains: {
			rsvp: { title: 'RSVPs, but no certificates', text: 'GatherPress knows who came, and still the certificates are made by hand.' },
			twice: { title: 'The list, typed twice', text: 'Copying who checked in into another tool invites mistakes.' },
			organizers: { title: 'Organizers left out', text: 'Hosts and speakers deserve a certificate that says so.' },
		},
		steps: {
			run: 'Run the event in GatherPress: RSVP and check people in.',
			sync: 'Events › the event’s row action "Certificates": import or "Sync with GatherPress".',
			issue: 'In Participants, "Issue certificates".',
		},
		order: {
			pains: [ 'rsvp', 'twice', 'organizers' ],
			steps: [ 'run', 'sync', 'issue' ],
			highlight: [ 'gatherpress-sync', 'meetup-import', 'roles', 'template-editor' ],
			secondary: [ 'authentication', 'starter-templates', 'fonts', 'records-not-files', 'dark-mode', 'share', 'multi-language', 'template-export-import', 'abilities-cli' ],
			limits: [ 'events', 'generator', 'lists', 'email' ],
		},
		limits: { events: 'Events are GatherPress’s: this plugin keeps no events of its own here.', generator: 'No Generator screen: you issue from Participants.', lists: 'Exporting and importing lists is off; one template can still be exported.', email: 'It does not e-mail certificates.' },
		shots: { 'meetup-import': 'gp-participants', authentication: 'gp-certificates', 'gatherpress-sync': 'gp-events', 'template-editor': 'editor' },
		docs: 'scenarios/gatherpress',
	},
	wordcamp: {
		id: 'wordcamp',
		name: 'WordCamp.org (CampTix)',
		audience: 'WordCamp organizers, on WordCamp.org’s CampTix.',
		hero: {
			title: 'Attendees issue their own certificate from the ticket e-mail.',
			lead: 'Organizers mark who was present, day by day. Each attendee opens their ticket, confirms what they attended and gets their certificate. No spreadsheet, no lines at the desk.',
			primary: 'See how it works',
			note: 'Needs CampTix, as WordCamp.org runs it.',
		},
		pains: {
			many: { title: 'Hundreds of attendees', text: 'Issuing certificates one by one after the event takes days.' },
			days: { title: 'Days and tables', text: 'People attend one day, or one contributor-day table: the certificate must say which.' },
			fix: { title: 'Corrections', text: 'A wrong name means reissuing and recalling the old one.' },
		},
		steps: {
			setup: 'Tickets › Setup › Certificates: who may issue, the event days, and a role on each ticket.',
			present: 'Tickets › Attendees: mark who was present, day by day.',
			issue: 'Each attendee opens the "Certificate" step of their ticket and issues it.',
		},
		order: {
			pains: [ 'many', 'days', 'fix' ],
			steps: [ 'setup', 'present', 'issue' ],
			highlight: [ 'self-issue', 'presence-days', 'talk-selection', 'declaration-page', 'identification-document', 'roles', 'template-editor' ],
			secondary: [ 'authentication', 'starter-templates', 'fonts', 'records-not-files', 'dark-mode', 'share', 'multi-language', 'template-export-import', 'abilities-cli' ],
			limits: [ 'camptix', 'bulk', 'lists', 'email' ],
		},
		limits: { camptix: 'CampTix is not in the WordPress.org plugin directory; the schedule comes from wc-post-types.', bulk: 'Organizers have no bulk issue: attendees issue their own. With self-issue off, nobody can.', lists: 'Exporting and importing lists is off.', email: 'It does not e-mail certificates.' },
		docs: 'scenarios/wordcamp',
	},
};

export const scenarioIds = Object.keys( scenarios ) as ScenarioId[];
