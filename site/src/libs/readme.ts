// The FAQ and the changelog are the plugin's readme.txt: written once, shown on the site. A question a language must not show
// (product.forbidden) is left out of that language.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { product } from '../product';

const readme = readFileSync( resolve( process.cwd(), '../readme.txt' ), 'utf8' );

const section = ( name: string ): string => {
	const match = readme.match( new RegExp( `^== ${ name } ==\\n([\\s\\S]*?)(?=^== |$(?![\\s\\S]))`, 'm' ) );
	return match ? match[ 1 ].trim() : '';
};

/** `= Title =` blocks of a section. */
const blocks = ( text: string ) => [ ...text.matchAll( /^= (.+?) =\n([\s\S]*?)(?=^= |$(?![\s\S]))/gm ) ].map( ( m ) => ( { title: m[ 1 ].trim(), body: m[ 2 ].trim() } ) );

export const faq = ( locale = 'en' ) =>
	blocks( section( 'Frequently Asked Questions' ) ).filter( ( item ) => ! ( product.forbidden[ locale ] ?? [] ).some( ( term ) => term.test( `${ item.title } ${ item.body }` ) ) );

export const changelog = () => blocks( section( 'Changelog' ) );

const escape = ( text: string ) => text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );

/** Readme text as HTML: paragraphs, bullet lists, [links](url), **bold**, `code`. */
export const html = ( text: string ): string =>
	text
		.split( /\n{2,}/ )
		.map( ( part ) => {
			const inline = ( line: string ) =>
				escape( line )
					.replace( /\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<a href="$2">$1</a>' )
					.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' )
					.replace( /`([^`]+)`/g, '<code>$1</code>' );
			const lines = part.split( '\n' );
			return lines.every( ( line ) => /^[*-] /.test( line ) )
				? `<ul>${ lines.map( ( line ) => `<li>${ inline( line.slice( 2 ) ) }</li>` ).join( '' ) }</ul>`
				: `<p>${ inline( lines.join( ' ' ) ) }</p>`;
		} )
		.join( '' );

/** Readme text as plain Markdown for the .md twins (it already is Markdown-ish). */
export const markdown = ( text: string ): string => text;
