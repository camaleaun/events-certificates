// The screenshots of the site and of WordPress.org: the source of truth. scripts/screenshots.mjs takes them on the throwaway demo
// sites (scripts/demo-sites.sh) seeded by ../bin/demo/seed.php. For each one:
//   scenario  the demo site it is taken on (standalone, gatherpress, wordcamp); `any` is taken on standalone
//   locales   the languages it is taken in (the English file is here, the others live in the translation repository)
//   path      the screen, with {event} {course} {design} filled from the seed's ids (.demo/<scenario>.json)
//   scene     a step in scripts/lib/scenes.mjs that puts the screen in the state to show
//   sources   the plugin files the picture depends on: when one changes after the capture, `npm run check` fails until it is taken again
//   usedIn    where it is shown: docs pages (group/page), or `catalog` for the scenario landings and the home
//   wporg     true: it is also a WordPress.org screenshot (the readme's `== Screenshots ==`, in this order)
export const viewport = { width: 1440, height: 900, scale: 2 };

export interface Screenshot {
	id: string;
	scenario: 'standalone' | 'gatherpress' | 'wordcamp';
	locales: string[];
	caption: string;
	path: string;
	scene?: string;
	sources: string[];
	usedIn: string[];
	wporg?: boolean;
}

export const screenshots: Screenshot[] = [
	{ id: 'generator', scenario: 'standalone', locales: [ 'en' ], caption: 'The Generator: pick an event, preview each certificate, download.', path: '/wp-admin/admin.php?page=evcert-generator', sources: [ 'includes/events-generator.php', 'src/events-generator/', 'assets/scss/generator.scss' ], usedIn: [ 'start/first-certificate', 'catalog' ], wporg: true },
	{ id: 'participants', scenario: 'standalone', locales: [ 'en' ], caption: 'The participants of an event, with the status, roles and certificates.', path: '/wp-admin/admin.php?page=evcert-event&event={event}&view=participants', sources: [ 'includes/events-admin.php', 'includes/class-people-table.php', 'assets/scss/events-admin.scss' ], usedIn: [ 'guides/events-and-participants', 'catalog' ], wporg: true },
	{ id: 'certificates', scenario: 'standalone', locales: [ 'en' ], caption: 'The Certificates screen: issued, replaced and the Trash.', path: '/wp-admin/admin.php?page=evcert-certificate', sources: [ 'includes/admin.php', 'includes/class-list-table.php' ], usedIn: [ 'guides/verification', 'catalog' ], wporg: true },
	{ id: 'editor', scenario: 'standalone', locales: [ 'en' ], caption: 'The template editor.', path: '/wp-admin/admin.php?page=evcert-template&design={design}&action=edit', scene: 'editor-panels', sources: [ 'src/editor/', 'assets/scss/editor-screen.scss' ], usedIn: [ 'guides/templates', 'catalog' ], wporg: true },
	{ id: 'editor-selection', scenario: 'standalone', locales: [ 'en' ], caption: 'The template editor with several elements selected: the bar to group them, the layers and the options.', path: '/wp-admin/admin.php?page=evcert-template&design={design}&action=edit', scene: 'editor-select-all', sources: [ 'src/editor/', 'assets/scss/editor-screen.scss' ], usedIn: [ 'guides/group-elements' ] },
	{ id: 'export', scenario: 'standalone', locales: [ 'en' ], caption: 'Export to a file.', path: '/wp-admin/admin.php?page=evcert-export', sources: [ 'includes/bundle-admin.php', 'assets/scss/transfer.scss' ], usedIn: [ 'guides/export-and-import', 'catalog' ], wporg: true },
	{ id: 'settings', scenario: 'standalone', locales: [ 'en' ], caption: 'The settings and the certificate signature.', path: '/wp-admin/admin.php?page=evcert-options&tab=certificates', sources: [ 'includes/admin-settings.php', 'assets/scss/settings.scss' ], usedIn: [ 'guides/settings' ], wporg: true },
	{ id: 'quick-admin', scenario: 'standalone', locales: [ 'en' ], caption: 'The Quick generator in the admin: paste names, pick a template, download.', path: '/wp-admin/admin.php?page=evcert-quick-generator', sources: [ 'includes/generator.php', 'src/quick-generator/', 'assets/scss/quick-generator.scss' ], usedIn: [ 'use-cases/meetup-quick-generator', 'catalog' ] },
	{ id: 'public-generator', scenario: 'standalone', locales: [ 'en' ], caption: 'The public page where attendees type their name and download their certificate.', path: '/certificate-generator/', scene: 'hide-tutorial', sources: [ 'includes/quick-generator.php', 'assets/es/quick-generator.js', 'assets/scss/quick-generator.scss' ], usedIn: [ 'use-cases/wordpress-meetup-public-generator', 'catalog' ] },
	{ id: 'events', scenario: 'standalone', locales: [ 'en' ], caption: 'The events of a course, one per class.', path: '/wp-admin/admin.php?page=evcert-event', sources: [ 'includes/events-admin.php', 'assets/scss/events-admin.scss' ], usedIn: [ 'use-cases/course-with-classes' ] },
];
