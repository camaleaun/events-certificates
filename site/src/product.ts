/**
 * Everything that is about THIS product and nothing else: the one file to change when the repository moves, the site gets a domain,
 * or WordPress.org lists the plugin. Framework files (libs/i18n.ts, components/LanguageMenu.astro, scripts/*) read only this.
 */
export const product = {
	name: 'Events Certificates',
	slug: 'events-certificates',
	/** GitHub "owner/name" of the plugin; change here when the repository is transferred. */
	repo: 'camaleaun/events-certificates',
	/** The plugin's slug in the WordPress.org directory; null while it is only on GitHub. */
	wporgSlug: null as string | null,
	/** localStorage key of the chosen language. */
	storageKey: 'evcert-site-lang',
	/** Terms that must never reach the pages of a language (checked in dist/ by npm run check). */
	forbidden: { en: [ /\bCPF\b/i, /identification document/i ] } as Record< string, RegExp[] >,
};

export const repoUrl = `https://github.com/${ product.repo }`;
