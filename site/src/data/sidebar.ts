// The docs, in order: groups of page ids (group/file-name, no extension). The build fails when a page listed here is missing, or
// a page exists that is not listed. A page's title is the one in its front matter.
export const sidebar = [
	{ title: 'Start', pages: [ 'start/introduction', 'start/first-certificate' ] },
	{ title: 'Guides', pages: [ 'guides/events-and-participants', 'guides/meetup-csv', 'guides/generators', 'guides/templates', 'guides/group-elements', 'guides/template-files', 'guides/roles-and-languages', 'guides/verification', 'guides/get-my-certificate', 'guides/declaration-page', 'guides/identification-documents', 'guides/export-and-import', 'guides/restricting-export-and-import', 'guides/settings' ] },
	{ title: 'Cases', pages: [ 'scenarios/standalone', 'scenarios/gatherpress', 'scenarios/wordcamp', 'scenarios/both' ] },
	{ title: 'Use cases', pages: [ 'use-cases/wordpress-meetup-public-generator', 'use-cases/meetup-quick-generator', 'use-cases/course-with-classes', 'use-cases/gatherpress-meetup', 'use-cases/wordcamp-self-issue' ] },
	{ title: 'Reference', pages: [ 'reference/hooks', 'reference/abilities', 'reference/block-and-shortcodes', 'reference/cli' ] },
];
