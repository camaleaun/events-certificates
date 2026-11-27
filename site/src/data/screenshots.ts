// The screenshots of the site and of WordPress.org: the source of truth. scripts/screenshots.mjs takes them on a throwaway
// site seeded with made-up data (bin/screenshots-seed.php); `usedIn` says where each one is shown, so the build can tell a
// picture nobody shows from a page that shows one that is not here.
export const viewport = { width: 1440, height: 900, scale: 2 };

export const screenshots = [
	{ id: 'generator', caption: 'The Generator: pick an event, preview each certificate, download.', path: '/wp-admin/admin.php?page=evcert-generator', usedIn: [ 'start/first-certificate' ] },
	{ id: 'participants', caption: 'The participants of an event, with the status, roles and certificates.', path: '/wp-admin/admin.php?page=evcert-event&event={event}&view=participants', usedIn: [ 'guides/events-and-participants' ] },
	{ id: 'certificates', caption: 'The Certificates screen: issued, replaced and the Trash.', path: '/wp-admin/admin.php?page=evcert-certificate', usedIn: [ 'guides/verification' ] },
	{ id: 'editor', caption: 'The template editor.', path: '/wp-admin/admin.php?page=evcert-template&design={design}&action=edit', usedIn: [ 'guides/templates' ] },
	{ id: 'export', caption: 'Export to a file.', path: '/wp-admin/admin.php?page=evcert-export', usedIn: [ 'guides/export-and-import' ] },
	{ id: 'settings', caption: 'The settings and the certificate signature.', path: '/wp-admin/admin.php?page=evcert-options&tab=certificates', usedIn: [ 'guides/settings' ] },
];
