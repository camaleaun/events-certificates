// The site is built in the plugin's repo (site/) and published on GitHub Pages. The address is set by the environment so the
// same build serves the repository's own Pages now and the organization's later (SITE and BASE in the workflow).
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

const site = process.env.SITE || 'https://camaleaun.github.io';
const base = process.env.BASE ?? '/events-certificates';

export default defineConfig( {
	site,
	base,
	trailingSlash: 'always',
	integrations: [ sitemap() ],
	build: { inlineStylesheets: 'always' },
	markdown: { shikiConfig: { themes: { light: 'github-light-high-contrast', dark: 'github-dark-high-contrast' } } },
} );
