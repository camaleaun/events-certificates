// The texts of a language: English from src/data/, with the language's strings.json (translations repository) laid over them.
// Everything translatable is an object keyed by id, so inserting or reordering in English never shifts a translation.
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { features, type Feature } from '@data/catalog';
import { hub } from '@data/hub';
import { scenarios, type Scenario } from '@data/scenarios';
import { ui } from '@data/ui';
import { defaultLocale } from './i18n';

const isObject = ( value: unknown ): value is Record< string, unknown > => typeof value === 'object' && value !== null && ! Array.isArray( value );

/** `over` laid on `base`, key by key; what `over` lacks stays; a value of another type is ignored. */
const merge = < T, >( base: T, over: unknown ): T => {
	if ( isObject( base ) ) {
		if ( ! isObject( over ) ) {
			return base;
		}
		const out: Record< string, unknown > = { ...base };
		for ( const key of Object.keys( base ) ) {
			if ( key in over ) {
				out[ key ] = merge( ( base as Record< string, unknown > )[ key ], over[ key ] );
			}
		}
		return out as T;
	}
	return ( typeof over === typeof base ? over : base ) as T;
};

const cache = new Map< string, Record< string, unknown > >();
const overlay = ( code: string ): Record< string, unknown > => {
	if ( code === defaultLocale.code ) {
		return {};
	}
	if ( ! cache.has( code ) ) {
		const file = resolve( process.cwd(), 'src/content/i18n', code, 'strings.json' );
		cache.set( code, existsSync( file ) ? JSON.parse( readFileSync( file, 'utf8' ) ) : {} );
	}
	return cache.get( code )!;
};

export const uiOf = ( code: string ) => merge( ui, overlay( code ).ui );
export const hubOf = ( code: string ) => merge( hub, overlay( code ).hub );
export const featureOf = ( id: string, code: string ): Feature => merge( features[ id ], ( overlay( code ).features as Record< string, unknown > | undefined )?.[ id ] );
export const scenarioOf = ( id: keyof typeof scenarios, code: string ): Scenario => merge( scenarios[ id ], ( overlay( code ).scenarios as Record< string, unknown > | undefined )?.[ id ] );
/** A text with its `{name}` values filled in. */
export const fill = ( text: string, values: Record< string, string | number > ): string => text.replace( /\{(\w+)\}/g, ( match, name: string ) => ( name in values ? String( values[ name ] ) : match ) );
