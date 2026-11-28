import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

// The docs are Markdown files in src/content/docs/<group>/<page>.md; the order and the groups are src/data/sidebar.ts.
const docs = defineCollection( {
	loader: glob( { pattern: '**/*.md', base: './src/content/docs' } ),
	schema: z.object( { title: z.string(), description: z.string(), /** Languages that list this page; none = all. A page for one language is English source text for its translators and is not routed in the others. */ locales: z.array( z.string() ).optional() } ),
} );

export const collections = { docs };
