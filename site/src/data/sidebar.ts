// The docs, in order: groups of page ids (group/file-name, no extension). The build fails when a page listed here is missing, or
// a page exists that is not listed. A page's title is the one in its front matter.
export const sidebar = [
	{ title: 'Start', pages: [ 'start/introduction', 'start/first-certificate' ] },
	{ title: 'Guides', pages: [ 'guides/events-and-participants', 'guides/generators', 'guides/templates', 'guides/roles-and-languages', 'guides/verification', 'guides/get-my-certificate', 'guides/export-and-import', 'guides/settings' ] },
	{ title: 'Integrations', pages: [ 'integrations/camptix', 'integrations/gatherpress' ] },
	{ title: 'Reference', pages: [ 'reference/hooks', 'reference/abilities', 'reference/block-and-shortcodes', 'reference/cli' ] },
];
