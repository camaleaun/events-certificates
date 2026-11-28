/**
 * A design as its file says it (src/editor/dialect.ts, the same as to_file()/from_file() in includes/designs.php; docs/decisions/0050, 0051): the
 * colors like a theme.json and a reference to a color as a preset. The elements are written by the camaleaun/draw package (names in full, no id that only
 * counts, no default name). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const built = await esbuild.build( { absWorkingDir: root, stdin: { contents: "export * from './src/editor/dialect'; export { toMarkup, fromMarkup } from '@camaleaun/draw';", resolveDir: root, loader: 'ts' }, bundle: true, write: false, format: 'iife', globalName: 'dialect' } );
const sandbox = {};
vm.runInNewContext( built.outputFiles[ 0 ].text + ';this.dialect = dialect;', sandbox );
// Objects of the sandbox have its own prototypes: they are compared as the plain data they are.
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );
const toFile = ( d ) => plain( sandbox.dialect.toFile( d ) );
const fromFile = ( d ) => plain( sandbox.dialect.fromFile( d ) );

test( 'a reference to a color is written as a preset, and read back', () => {
	const design = { page: { width: '297mm', height: '210mm', backgroundColor: '$background' }, elements: [ { type: 'rect', fill: '$primary', stroke: '#112233', runs: [ { text: '$100' }, { color: '$text' } ] } ] };
	const file = toFile( design );
	assert.equal( file.page.backgroundColor, 'var:preset|color|background' );
	assert.equal( file.elements[ 0 ].fill, 'var:preset|color|primary' );
	assert.equal( file.elements[ 0 ].runs[ 1 ].color, 'var:preset|color|text' );
	assert.equal( file.elements[ 0 ].stroke, '#112233', 'a literal is as it is' );
	assert.equal( file.elements[ 0 ].runs[ 0 ].text, '$100', 'a text is as it is' );
	assert.equal( /"\$[A-Za-z]/.test( JSON.stringify( file ) ), false, 'no $name left in the file' );
	const back = fromFile( file );
	assert.equal( back.page.backgroundColor, '$background' );
	assert.equal( back.elements[ 0 ].fill, '$primary' );
	assert.equal( back.elements[ 0 ].runs[ 1 ].color, '$text' );
} );

test( 'a file of the first form (with "element|") is read too', () => {
	assert.equal( fromFile( { page: { backgroundColor: 'var:preset|color|element|background' }, elements: [] } ).page.backgroundColor, '$background' );
} );

test( 'the colors are written like a theme.json: color.palette for the Theme and color.elements for the Elements', () => {
	const design = {
		page: { width: '297mm', height: '210mm', backgroundColor: '$fundo' },
		colors: { fundo: { name: 'Fundo', light: '#ffffff', dark: '#000000' }, 'cor-1': { name: 'Cor 1', light: '' } },
		palette: [ { slug: 'base', color: '#ffffff' }, { slug: 'mirtilo', name: 'Mirtilo', color: '#3858e9' } ],
		elements: [],
	};
	const file = toFile( design );
	assert.deepEqual( Object.keys( file ), [ 'page', 'color', 'elements' ] );
	assert.deepEqual( file.color.palette, [ { slug: 'base', color: '#ffffff' }, { slug: 'mirtilo', color: '#3858e9', name: 'Mirtilo' } ] );
	assert.deepEqual( file.color.elements, [ { slug: 'fundo', color: 'light-dark(#ffffff,#000000)', name: 'Fundo' }, { slug: 'cor-1', color: '', name: 'Cor 1' } ] );
	assert.equal( 'colors' in file || 'palette' in file, false );
	const back = fromFile( file );
	assert.deepEqual( back.colors, design.colors );
	assert.deepEqual( back.palette, design.palette );
	assert.deepEqual( Object.keys( back ), [ 'page', 'colors', 'palette', 'elements' ] );
	// A design with no theme says none by writing no palette; one with an empty theme writes an empty palette.
	assert.equal( 'palette' in toFile( { page: {}, colors: {}, elements: [] } ).color, false );
	assert.deepEqual( toFile( { page: {}, colors: {}, palette: [], elements: [] } ).color, { palette: [], elements: [] } );
} );

test( 'a color is written as CSS says it, and may point at the Theme', () => {
	const design = { page: {}, colors: { fundo: { name: 'Fundo', light: '$branco', dark: '$preto' }, texto: { name: 'Texto', light: '$preto' }, vidro: { name: 'Vidro', light: 'rgba(1,2,3,.5)', dark: 'rgba(4,5,6,.5)' } }, palette: [ { slug: 'branco', color: '#ffffff' }, { slug: 'preto', color: '#000000' } ], elements: [] };
	const file = toFile( design );
	assert.deepEqual( file.color.elements.map( ( e ) => e.color ), [ 'light-dark(var:preset|color|branco,var:preset|color|preto)', 'var:preset|color|preto', 'light-dark(rgba(1,2,3,.5),rgba(4,5,6,.5))' ] );
	assert.deepEqual( fromFile( file ).colors, design.colors, 'read back, the commas of rgba() are not a separator' );
} );

test( 'the elements are written by the package: names in full, no id that only counts, no default name, the certificate\'s own default names too', () => {
	const design = { page: { width: '297mm', height: '210mm' }, elements: [
		{ id: 'rect-2', type: 'rect', x: 1, y: 2, w: 30, h: 20, name: 'Rectangle', layout: { h: { mode: 'stretch' }, v: { mode: 'start' }, ref: 'safe' } },
		{ id: 'svg-mwzbwe9c-1', type: 'svg', name: 'SVG', w: 96, h: 96, vw: 24, vh: 24, paths: [ { d: 'M0 0L1 1Z' } ] },
		{ id: 'title', type: 'text', name: 'Band', lh: 1.2, runs: [ { text: 'a', b: true } ] },
		{ id: 'signature', type: 'signature', name: 'Signature', w: 10, h: 5 },
		{ id: 'logo', type: 'image', slot: 'logo', name: 'Community logo', w: 10, h: 5 },
	] };
	const markup = sandbox.dialect.toMarkup( sandbox.dialect.toFile( design ), sandbox.dialect.BLOCK_OPTIONS );
	assert.equal( /"(w|h|vw|vh|lh|b)":/.test( markup ), false, 'no short name' );
	assert.equal( /"id":"(rect-2|svg-)/.test( markup ), false );
	assert.equal( /"name":"(Rectangle|SVG|Signature|Community logo)"/.test( markup ), false, 'no default name' );
	assert.ok( markup.includes( '"name":"Band"' ) && markup.includes( '"id":"title"' ) );
	assert.ok( markup.includes( '"viewBox":"0 0 24 24"' ) && markup.includes( '"horizontal"' ) && markup.includes( '"text":"**a**"' ) && ! markup.includes( '"runs"' ) );
	const back = plain( sandbox.dialect.fromFile( sandbox.dialect.fromMarkup( markup, sandbox.dialect.BLOCK_OPTIONS ) ) );
	assert.deepEqual( back.elements.map( ( e ) => e.id ), [ 'rect-1', 'svg-2', 'title', 'signature', 'logo' ] );
	assert.deepEqual( [ back.elements[ 1 ].w, back.elements[ 1 ].vw ], [ 96, 24 ] );
} );


test( 'the date of emission is a block of its own: no slot, no name, no text while it is empty; a format written stays', () => {
	const empty = { id: 'text-3', type: 'text', slot: 'date-of-emission', name: 'Date of emission', x: 1, y: 2, w: 200, h: 16 };
	const custom = { id: 'date', type: 'text', slot: 'date-of-emission', name: 'Date of emission', x: 1, y: 2, w: 200, h: 16, runs: [ { text: 'j \\d\\e F \\d\\e Y' } ] };
	const markup = sandbox.dialect.toMarkup( sandbox.dialect.toFile( { page: {}, elements: [ empty, custom ] } ), sandbox.dialect.BLOCK_OPTIONS );
	assert.equal( ( markup.match( /certificate-date-of-emission/g ) || [] ).length, 2, 'one self-closing block each' );
	assert.equal( /"slot"|"name"|"runs"|"id":"text-3"/.test( markup ), false, markup );
	assert.ok( markup.includes( '"text":"j \\\\\\\\d\\\\\\\\e F \\\\\\\\d\\\\\\\\e Y"' ), markup );
	const back = plain( sandbox.dialect.fromFile( sandbox.dialect.fromMarkup( markup, sandbox.dialect.BLOCK_OPTIONS ) ) );
	assert.deepEqual( back.elements.map( ( e ) => [ e.type, e.slot ] ), [ [ 'text', 'date-of-emission' ], [ 'text', 'date-of-emission' ] ] );
	assert.equal( back.elements[ 1 ].runs[ 0 ].text, 'j \\d\\e F \\d\\e Y' );
} );
