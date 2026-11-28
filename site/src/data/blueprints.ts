// The Playground blueprints of the cases that can run in a browser: one file each in .wordpress-org/blueprints/, written by
// scripts/blueprints.mjs from this data and bin/demo/seed.php (the same file that seeds the demo sites of the screenshots). Plain TypeScript, no aliases.
// WordCamp.org (CampTix) has none yet: CampTix is not in the WordPress.org plugin directory (see the plan: a branch with a tag per build).
export interface BlueprintSpec {
	/** The file in .wordpress-org/blueprints/. */
	file: string;
	title: string;
	description: string;
	/** Where it opens. */
	landing: string;
	/** Plugins of WordPress.org to install besides the plugin. */
	plugins: string[];
}

export const blueprints: Record< string, BlueprintSpec > = {
	standalone: {
		file: 'blueprint.json',
		title: 'Events Certificates: Standalone',
		description: 'Try Events Certificates on its own: a demo meetup and a course are ready; issue and download certificates in the Generator, or open the Quick generator to type your own.',
		landing: '/wp-admin/admin.php?page=evcert-generator',
		plugins: [],
	},
	gatherpress: {
		file: 'blueprint-gatherpress.json',
		title: 'Events Certificates with GatherPress',
		description: 'Try Events Certificates inside GatherPress: an event with RSVPs and check-ins is ready; use the Certificates action of the event to bring who came, then issue their certificates.',
		landing: '/wp-admin/edit.php?post_type=gatherpress_event',
		plugins: [ 'gatherpress' ],
	},
};
