// Every capability of the plugin, written once. The scenario landings, the comparison table, the "what works where" page, index.md
// and llms.txt are all built from this, so they cannot disagree. `where` follows includes/environment.php (integration()):
// facts, not marketing. `prominence` is how loud a feature is, per language: the owner's rules (a hidden feature never reaches that language).
// Plain TypeScript with no aliases: scripts/check-prominence.mjs imports this file.
export type ScenarioId = 'standalone' | 'gatherpress' | 'wordcamp';
export type WhereKey = ScenarioId | 'both';
/** 'yes', 'no', or a note: it works here with a difference worth saying. */
export type Where = 'yes' | 'no' | { note: string };
export type Prominence = 'highlight' | 'secondary' | 'hidden';

export interface Feature {
	title: string;
	text: string;
	where: Record< WhereKey, Where >;
	/** `default` for every language, then a language code (en, br) overrides. */
	prominence: { default: Prominence } & Partial< Record< string, Prominence > >;
	/** Screenshot id (src/assets/screenshots/<id>.webp), when one shows it. */
	screenshot?: string;
	/** Docs page id. */
	docs: string;
	/** The honest limit, shown in the matrix. */
	claimNote?: string;
}

const all: Record< WhereKey, Where > = { standalone: 'yes', gatherpress: 'yes', wordcamp: 'yes', both: 'yes' };
const only = ( ...keys: WhereKey[] ): Record< WhereKey, Where > => ( {
	standalone: keys.includes( 'standalone' ) ? 'yes' : 'no',
	gatherpress: keys.includes( 'gatherpress' ) ? 'yes' : 'no',
	wordcamp: keys.includes( 'wordcamp' ) ? 'yes' : 'no',
	both: keys.includes( 'both' ) ? 'yes' : 'no',
} );

export const features: Record< string, Feature > = {
	'generator-events': {
		title: 'The Generator, with your events',
		text: 'Register an event and its people, preview the real certificate of each one and download one PDF or a ZIP.',
		where: only( 'standalone' ),
		prominence: { default: 'highlight' },
		screenshot: 'generator',
		docs: 'guides/generators',
	},
	'quick-admin': {
		title: 'Quick generator in the admin',
		text: 'Paste names, pick a template, download. Nothing is saved on the site: no events, no records.',
		where: only( 'standalone' ),
		prominence: { default: 'highlight' },
		screenshot: 'quick-admin',
		docs: 'guides/generators',
		claimNote: 'Saves nothing, so the certificates have no code to check.',
	},
	'public-generator': {
		title: 'A public page for attendees',
		text: 'A page on your site where anyone types their name and downloads their own certificate. It runs in the visitor’s browser and saves nothing.',
		where: { standalone: 'yes', gatherpress: { note: 'the block and shortcode, not the ready-made page' }, wordcamp: { note: 'the block and shortcode, not the ready-made page' }, both: { note: 'the block and shortcode, not the ready-made page' } },
		prominence: { default: 'highlight' },
		screenshot: 'public-generator',
		docs: 'guides/generators',
		claimNote: 'No code on these certificates.',
	},
	'meetup-import': {
		title: 'Bring the list you already have',
		text: 'Paste names, upload a Meetup export or a CSV. Mark who attended; the Trash keeps what you remove.',
		where: only( 'standalone', 'gatherpress', 'both' ),
		prominence: { default: 'highlight' },
		screenshot: 'participants',
		docs: 'guides/events-and-participants',
	},
	'gatherpress-sync': {
		title: 'Straight from GatherPress',
		text: 'Who RSVP’d "attending" arrives as registered, who checked in as attended, and the organizers with their role. Sync again whenever you like; it never demotes anyone.',
		where: only( 'gatherpress', 'both' ),
		prominence: { default: 'highlight' },
		screenshot: 'gp-events',
		docs: 'scenarios/gatherpress',
		claimNote: 'Tested with GatherPress 0.37.0.',
	},
	'self-issue': {
		title: 'Attendees issue their own',
		text: 'The ticket e-mail links to a "Certificate" step: the attendee confirms, and gets their certificate. Organizers only mark who was present.',
		where: only( 'wordcamp', 'both' ),
		prominence: { default: 'highlight' },
		docs: 'scenarios/wordcamp',
		claimNote: 'Needs CampTix, as WordCamp.org runs it.',
	},
	'presence-days': {
		title: 'Presence, day by day',
		text: 'Mark who was present on each day (or at each contributor-day table) from the Attendees list; a certificate says exactly what the person attended.',
		where: only( 'wordcamp', 'both' ),
		prominence: { default: 'highlight' },
		docs: 'scenarios/wordcamp',
	},
	'talk-selection': {
		title: 'The talks they attended',
		text: 'The attendee picks the sessions they sat in and the certificate lists them.',
		where: { standalone: 'no', gatherpress: 'no', wordcamp: { note: 'the schedule comes from the wc-post-types plugin' }, both: { note: 'the schedule comes from the wc-post-types plugin' } },
		prominence: { default: 'secondary', br: 'highlight' },
		docs: 'scenarios/wordcamp',
	},
	'declaration-page': {
		title: 'A declaration page',
		text: 'An extra page the attendee fills in when they issue: the certificate becomes a declaration of what they attended.',
		where: only( 'wordcamp', 'both' ),
		prominence: { default: 'secondary', br: 'highlight' },
		docs: 'guides/settings',
	},
	'identification-document': {
		title: 'Identification document (CPF)',
		text: 'Asks for the attendee’s document on the declaration and keeps it hashed, as a password is.',
		where: only( 'wordcamp', 'both' ),
		prominence: { default: 'hidden', br: 'highlight' },
		docs: 'guides/settings',
	},
	authentication: {
		title: 'Proof it is theirs',
		text: 'Participants ask for their certificate with the e-mail they registered, and a certificate can carry a code and QR that anyone checks on your site.',
		where: all,
		prominence: { default: 'secondary', br: 'highlight' },
		screenshot: 'certificates',
		docs: 'guides/verification',
		claimNote: 'The code, QR and check page are optional and need a public site.',
	},
	roles: {
		title: 'One certificate per role',
		text: 'Attendee, speaker, organizer, volunteer, sponsor: each gets the wording and template that fits, and a person with two roles gets two certificates.',
		where: all,
		prominence: { default: 'highlight' },
		docs: 'guides/roles-and-languages',
	},
	'template-editor': {
		title: 'A template editor made for certificates',
		text: 'Text with fields and bold, shapes, SVG, images, QR and signature, groups in rows, stacks or grids, multi-select and copy and paste. The screen and the PDF are drawn from the same markup.',
		where: all,
		prominence: { default: 'highlight' },
		screenshot: 'editor',
		docs: 'guides/templates',
	},
	'starter-templates': {
		title: 'Starter templates',
		text: '"Basic" and "Meetup WordPress" are made for you the first time they are needed, and restored from Tools if you change them.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/settings',
	},
	fonts: {
		title: 'Your fonts',
		text: 'The fonts of the WordPress Font Library, besides the two that ship.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/templates',
		claimNote: 'WOFF2 fonts (what the Font Library installs from Google Fonts) are not used in the PDF.',
	},
	'records-not-files': {
		title: 'Records, not files',
		text: 'No PDF is stored: each certificate is a record drawn again every time. Correct a name and reissue, revoke, or send it to the Trash.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/verification',
	},
	'dark-mode': {
		title: 'Light and dark',
		text: 'A certificate page can follow the visitor’s light or dark scheme.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/templates',
		claimNote: 'On screen only: the PDF is always light.',
	},
	share: {
		title: 'Share buttons',
		text: 'People share their certificate page with a picture made for the link.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/verification',
		claimNote: 'Needs a public site.',
	},
	'multi-language': {
		title: 'Several languages',
		text: 'Write a text in each language of your site; each certificate is shown in the language it was issued in, and the visitor can switch.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/roles-and-languages',
	},
	'export-import-lists': {
		title: 'Move it to another site',
		text: 'Export templates, events, participants, certificates and settings to one file and import them elsewhere, or work from a CSV.',
		where: only( 'standalone' ),
		prominence: { default: 'secondary' },
		screenshot: 'export',
		docs: 'guides/export-and-import',
		claimNote: 'Off while GatherPress or CampTix is active.',
	},
	'template-export-import': {
		title: 'A template is a file of blocks',
		text: 'Export a template from the editor as plain HTML with block comments, the way WordPress keeps a page, and import it back or on another site. Version it, review it, or edit it by hand.',
		where: all,
		prominence: { default: 'secondary' },
		docs: 'guides/template-files',
		claimNote: 'Pictures must be in the site’s Media Library; anything the template may not have is left out on import.',
	},
	'abilities-cli': {
		title: 'For scripts and AI agents',
		text: 'WordPress Abilities and WP-CLI tools to read, issue and tidy up.',
		where: { standalone: 'yes', gatherpress: { note: 'fewer abilities: events are GatherPress’s' }, wordcamp: { note: 'overview, certificates and settings' }, both: { note: 'fewer abilities: events are GatherPress’s' } },
		prominence: { default: 'secondary' },
		docs: 'reference/abilities',
	},
};

/** The visible prominence of a feature in a language. */
export const prominenceOf = ( feature: Feature, locale: string ): Prominence => feature.prominence[ locale ] ?? feature.prominence.default;
