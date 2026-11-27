import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

// The docs are Markdown files in src/content/docs/<group>/<page>.md; the order and the groups are src/data/sidebar.ts.
const docs = defineCollection( {
	loader: glob( { pattern: '**/*.md', base: './src/content/docs' } ),
	schema: z.object( { title: z.string(), description: z.string() } ),
} );

export const collections = { docs };
