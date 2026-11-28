/**
 * The site's languages (translations.json): English at the root, each other language under /<code>/ once
 * scripts/translations.mjs has put its translation in src/content/i18n/<code>/ (git-ignored).
 * A language is added by adding its entry to translations.json, nothing here changes.
 */
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import config from '../../translations.json';
import { href as url } from './site';
import { scenarioIds } from '@data/scenarios';

export interface Locale {
	code: string;
	lang: string;
	/** Open Graph's locale, like en_US. */
	og: string;
	/** Its name, in it: the translation's strings.json `language` (English's here). */
	label: string;
	/** WordPress's locale, like pt_BR. */
	wp?: string;
	repo?: string;
	ref?: string;
}

export const defaultLocale: Locale = config.default;

const folder = ( code: string ): string => resolve( process.cwd(), 'src/content/i18n', code );

/** English, then every language whose translation is present (named by it). */
export const locales = (): Locale[] => [
	defaultLocale,
	...config.locales
		.filter( ( locale ) => existsSync( resolve( folder( locale.code ), 'strings.json' ) ) )
		.map( ( locale ) => ( {
			...locale,
			label: String( JSON.parse( readFileSync( resolve( folder( locale.code ), 'strings.json' ), 'utf8' ) ).language ?? locale.code ),
		} ) ),
];

/** The other languages, the ones under /<code>/. */
export const translatedLocales = (): Locale[] => locales().slice( 1 );

/** getStaticPaths of the /[locale]/ pages: one per translated language. */
export const localeParams = () => translatedLocales().map( ( locale ) => ( { params: { locale: locale.code } } ) );

/** The language of a page, from its path. */
export const localeOf = ( pathname: string ): Locale => {
	const first = pathname.replace( import.meta.env.BASE_URL, '/' ).split( '/' )[ 1 ];
	return translatedLocales().find( ( locale ) => locale.code === first ) ?? defaultLocale;
};

/** A page's address in a language. */
export const href = ( locale: Locale, path = '' ): string =>
	locale.code === defaultLocale.code ? url( path ) : url( `${ locale.code }/${ path.replace( /^\//, '' ) }` );

/** The path of a page without its language prefix: "/br/docs/" -> "docs/". */
export const pathWithoutLocale = ( pathname: string ): string => {
	const path = pathname.replace( import.meta.env.BASE_URL, '/' ).replace( /^\//, '' );
	const locale = localeOf( pathname );
	return locale.code === defaultLocale.code ? path : path.slice( locale.code.length + 1 );
};

/** Pages that exist in every language: the home and the scenario landings. The rest (docs...) is English until translated. */
const translated = ( path: string ) => path === '' || scenarioIds.some( ( id ) => path === `${ id }/` );

/** The same page in every language; a page with no translation points to the language's home (the 404 page is only in English). */
export const alternates = ( pathname: string ): { locale: Locale; href: string }[] => {
	const path = pathWithoutLocale( pathname );
	return ( path.startsWith( '404' ) ? [ defaultLocale ] : locales() ).map( ( locale ) => ( {
		locale,
		href: href( locale, locale.code === defaultLocale.code || translated( path ) ? path : '' ),
	} ) );
};
