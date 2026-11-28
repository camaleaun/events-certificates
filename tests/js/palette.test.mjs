/**
 * The palette: none of it fixed (a built-in color stands in for one that is missing), a Dark value that falls back to Light, the page color folded into Background,
 * pure white spotted. The cases are shared with the PHP side (tests/fixtures/palette-cases.json). Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const cases = JSON.parse( readFileSync( path.join( root, 'tests/fixtures/palette-cases.json' ), 'utf8' ) );

const built = await esbuild.build( {
	absWorkingDir: root,
	stdin: { contents: "import * as m from './src/editor/model'; window.model = m;", resolveDir: root, loader: 'ts' },
	bundle: true,
	format: 'iife',
	write: false,
	plugins: [
		{
			name: 'stub-i18n',
			setup( build ) {
				build.onResolve( { filter: /^@wordpress\/i18n$/ }, () => ( { path: 'i18n', namespace: 'stub' } ) );
				build.onLoad( { filter: /.*/, namespace: 'stub' }, () => ( { contents: 'export const __ = ( s ) => s; export const sprintf = ( s, ...a ) => a.reduce( ( t, v ) => t.replace( "%s", v ), s );', loader: 'js' } ) );
			},
		},
	],
} );
const sandbox = { window: {} };
vm.runInNewContext( built.outputFiles[ 0 ].text, sandbox );
const m = sandbox.window.model;
const plain = ( v ) => JSON.parse( JSON.stringify( v ) );

for ( const c of cases ) {
	test( `normalizeColors: ${ c.name }`, () => {
		const out = m.normalizeColors( c.input );
		assert.deepEqual( plain( out.colors ), c.colors );
		assert.deepEqual( Object.keys( out.colors ), Object.keys( c.colors ) );
		assert.equal( out.page.bg, c.bg );
		assert.deepEqual( plain( out.palette ), c.palette );
		if ( c.elements ) {
			assert.deepEqual( plain( out.elements ), c.elements );
		}
		assert.deepEqual( plain( m.normalizeColors( out ) ), plain( out ), 'running it again changes nothing' );
	} );
}

test( 'Dark falls back to Light when an entry has no Dark value', () => {
	const design = { page: { bg: '$background' }, colors: { a: { name: 'A', light: '#111111' }, b: { name: 'B', light: '#222222', dark: '#333333' } }, elements: [] };
	assert.equal( m.color( design, '$a', 'dark' ), '#111111' );
	assert.equal( m.color( design, '$b', 'dark' ), '#333333' );
	assert.equal( m.color( design, '$b', 'light' ), '#222222' );
	assert.equal( m.color( design, '$missing', 'dark' ), 'none' );
} );

test( 'a design with no colors at all reads the built-in ones; any other name is none', () => {
	const design = { page: { bg: '$background' }, colors: {}, elements: [] };
	assert.equal( m.color( design, '$text', 'light' ), '#000000' );
	assert.equal( m.color( design, '$text', 'dark' ), '#ffffff' );
	assert.equal( m.color( design, '$background', 'light' ), 'transparent' );
	assert.equal( m.color( design, '$muted', 'light' ), '#000000' );
	assert.equal( m.color( design, '$line', 'light' ), '#000000' );
	assert.equal( m.color( design, '$primary', 'light' ), '#3858e9' );
	assert.equal( m.color( design, '$other', 'light' ), 'none' );
	assert.equal( m.pageColor( design, 'light' ), '#ffffff' );
	assert.equal( m.pageColor( design, 'dark' ), '#000000' );
	assert.equal( m.pageColor( { ...design, colors: { background: { name: 'Background', light: '' } } }, 'light' ), '#ffffff', 'a Background with no color is white paper' );
} );

test( 'pure white is spotted in its spellings and nothing else is', () => {
	for ( const v of [ '#fff', '#FFFFFF', '#ffffffff', 'white', 'rgb(255, 255, 255)', 'rgba(255,255,255,1)' ] ) {
		assert.ok( m.isWhite( v ), v );
	}
	for ( const v of [ '#fefefe', '#ffffff00', 'transparent', 'rgba(255,255,255,0.5)', '', undefined ] ) {
		assert.ok( ! m.isWhite( v ), String( v ) );
	}
} );

test( 'replaceRefs swaps a reference everywhere, and only that one', () => {
	const out = m.replaceRefs( [ { fill: '$x', runs: [ { color: '$x' }, { color: '$y' } ] } ], 'x', '#123456' );
	assert.deepEqual( plain( out ), [ { fill: '#123456', runs: [ { color: '#123456' }, { color: '$y' } ] } ] );
	assert.ok( m.usesColor( { elements: [ { fill: '$x' } ] }, 'x' ) );
	assert.ok( ! m.usesColor( { elements: [ { fill: '$xy' } ] }, 'x' ) );
} );

test( 'Base and Contrast are named when shown, in the site language; the others as named', () => {
	assert.equal( m.swatchName( { slug: 'base', color: '#ffffff' } ), 'Base' );
	assert.equal( m.swatchName( { slug: 'contrast', color: '#000000' } ), 'Contrast' );
	assert.equal( m.swatchName( { slug: 'custom-1', name: 'Brand', color: '#1D2A6B' } ), 'Brand' );
	assert.equal( m.swatchName( { slug: 'custom-2', color: '#123456' } ), '#123456' );
	assert.equal( m.swatchName( { slug: 'base', name: 'Paper', color: '#ffffff' } ), 'Paper', 'a name given to Base is the name' );
} );

test( 'the next own slug is after the highest in use', () => {
	assert.equal( m.nextSwatchSlug( [ { slug: 'base' }, { slug: 'contrast' } ] ), 'custom-1' );
	assert.equal( m.nextSwatchSlug( [ { slug: 'custom-1' }, { slug: 'custom-4' }, { slug: 'x' } ] ), 'custom-5' );
} );

test( 'the theme is one Theme group of the swatches with their labels', () => {
	const groups = plain( m.themeOptions( [ { slug: 'base', color: '#ffffff' }, { slug: 'custom-1', name: 'Brand', color: '#1D2A6B' } ] ) );
	assert.deepEqual( groups, [ { name: 'Theme', colors: [ { name: 'Base', color: '#ffffff', slug: 'base' }, { name: 'Brand', color: '#1D2A6B', slug: 'custom-1' } ] } ] );
} );

test( 'picking sets a mode of a color; Clear puts Light to none and drops Dark', () => {
	const text = { name: 'Text', light: '#111111', dark: '#eeeeee' };
	assert.deepEqual( plain( m.setRoleValue( text, 'light', '#222222' ) ), { name: 'Text', light: '#222222', dark: '#eeeeee' } );
	assert.deepEqual( plain( m.setRoleValue( text, 'dark', '#dddddd' ) ), { name: 'Text', light: '#111111', dark: '#dddddd' } );
	assert.deepEqual( plain( m.setRoleValue( text, 'light' ) ), { name: 'Text', light: '', dark: '#eeeeee' }, 'no color of its own: none' );
	assert.deepEqual( plain( m.setRoleValue( text, 'dark' ) ), { name: 'Text', light: '#111111' }, 'Dark goes back to the same as Light' );
} );

test( 'the + makes "Color N" with a slug made from the name: a black swatch in the Theme, an empty color in the Elements', () => {
	const name = ( n ) => `Color ${ n }`;
	assert.deepEqual( plain( m.nextSwatch( [], name ) ), { slug: 'color-1', name: 'Color 1', color: '#000000' } );
	assert.deepEqual( plain( m.nextSwatch( [ { slug: 'color-1', name: 'Color 1', color: '#fff' }, { slug: 'color-2', name: 'Color 2', color: '#000' } ], name ) ), { slug: 'color-3', name: 'Color 3', color: '#000000' } );
	assert.deepEqual( plain( m.nextSwatch( [ { slug: 'base', color: '#fff' } ], name ) ), { slug: 'color-1', name: 'Color 1', color: '#000000' } );
	assert.deepEqual( plain( m.nextColor( {}, name ) ), [ 'color-1', { name: 'Color 1', light: '' } ] );
	assert.deepEqual( plain( m.nextColor( { text: { name: 'Text', light: '#000' } }, name ) ), [ 'color-1', { name: 'Color 1', light: '' } ] );
	assert.deepEqual( plain( m.nextColor( { 'color-1': { name: 'Color 1', light: '#000' } }, name ) ), [ 'color-2', { name: 'Color 2', light: '' } ] );
	// One name for a color: the slug of the other list is taken, so the second one is made apart (the name is numbered by its own list).
	assert.deepEqual( plain( m.nextColor( {}, name, [ { slug: 'color-1', name: 'Color 1', color: '#000' } ] ) ), [ 'color-1-2', { name: 'Color 1', light: '' } ] );
} );

test( 'a slug is made from the name: lowercase, no accents, dashes, starting with a letter', () => {
	assert.equal( m.slugify( 'Primária' ), 'primaria' );
	assert.equal( m.slugify( '  Cor de  Fundo! ' ), 'cor-de-fundo' );
	assert.equal( m.slugify( '2º plano' ), 'color-2-plano' );
	assert.equal( m.slugify( '★' ), '' );
	assert.equal( m.uniqueSlug( 'a', [ 'a', 'a-2' ] ), 'a-3' );
} );

test( 'renaming a color of the Elements makes its slug from the new name, in the same place, and what used it follows', () => {
	const design = {
		page: { bg: '$custom-color-1' },
		colors: { 'custom-color-1': { name: 'Color 1', light: '#fff' }, 'custom-color-2': { name: 'Color 2', light: '#000' } },
		palette: [ { slug: 'base', color: '#fff' } ],
		elements: [ { type: 'rect', fill: '$custom-color-1', runs: [ { color: '$custom-color-2' } ] } ],
		back: { elements: [ { type: 'rect', fill: '$custom-color-1' } ] },
	};
	const out = plain( m.renameColor( design, 'custom-color-1', 'Fundo' ) );
	assert.deepEqual( Object.keys( out.colors ), [ 'fundo', 'custom-color-2' ] );
	assert.equal( out.colors.fundo.name, 'Fundo' );
	assert.equal( out.page.bg, '$fundo' );
	assert.equal( out.elements[ 0 ].fill, '$fundo' );
	assert.equal( out.elements[ 0 ].runs[ 0 ].color, '$custom-color-2', 'another color is not touched' );
	assert.equal( out.back.elements[ 0 ].fill, '$fundo' );
	assert.equal( plain( m.renameColor( design, 'custom-color-1', 'Cor 2' ) ).colors[ 'cor-2' ].name, 'Cor 2' );
	assert.equal( Object.keys( plain( m.renameColor( design, 'custom-color-1', '' ) ).colors )[ 0 ], 'custom-color-1', 'no name, the slug stays' );
	assert.equal( Object.keys( plain( m.renameColor( design, 'custom-color-1', 'Base' ) ).colors )[ 0 ], 'base-2', 'a slug of the Theme is taken' );
	assert.equal( Object.keys( plain( m.renameColor( design, 'custom-color-1', 'Color 1' ) ).colors )[ 0 ], 'color-1', 'the same name as before' );
} );

test( 'renaming a swatch of the Theme does the same in the Theme', () => {
	const design = { page: { bg: '$custom-1' }, colors: { text: { name: 'Text', light: '#000' } }, palette: [ { slug: 'custom-1', name: 'Color 1', color: '#fafafa' }, { slug: 'base', color: '#fff' } ], elements: [] };
	const out = plain( m.renameSwatch( design, 'custom-1', 'Mirtilo' ) );
	assert.deepEqual( out.palette.map( ( s ) => s.slug ), [ 'mirtilo', 'base' ] );
	assert.equal( out.palette[ 0 ].name, 'Mirtilo' );
	assert.equal( out.page.bg, '$mirtilo' );
	assert.equal( plain( m.renameSwatch( design, 'custom-1', 'Text' ) ).palette[ 0 ].slug, 'text-2', 'a slug of the Elements is taken' );
} );

test( 'the colors of an element are one list, in the order they are kept', () => {
	assert.deepEqual( plain( m.colorGroups( { colors: { b: { name: 'B', light: '#000' }, a: { name: 'A', light: '#fff' } } } ) ), [ { name: 'elements', keys: [ 'b', 'a' ] } ] );
	assert.deepEqual( plain( m.colorGroups( { colors: {} } ) ), [] );
	assert.deepEqual( plain( m.colorGroups( { colors: { a: { name: 'A', light: '' }, b: { name: 'B', light: '#fff' } } } ) ), [ { name: 'elements', keys: [ 'b' ] } ], 'a color with no value is not offered' );
	assert.deepEqual( plain( m.colorGroups( { colors: { a: { name: 'A', light: '' } } } ) ), [] );
} );

test( 'a new template starts filled: Background and Text, Base and Contrast', () => {
	assert.deepEqual( Object.keys( m.starterColors() ), [ 'background', 'text' ] );
	assert.deepEqual( plain( m.starterSwatches() ), [ { slug: 'base', color: '#ffffff' }, { slug: 'contrast', color: '#000000' } ] );
} );

test( 'the Base design ships the theme it would be given', () => {
	const base = JSON.parse( readFileSync( path.join( root, 'templates/base-design.json' ), 'utf8' ) );
	assert.deepEqual( plain( base.palette ), plain( m.defaultPalette( m.normalizeColors( { ...base, palette: undefined } ).colors ) ) );
	assert.ok( base.palette.every( ( s ) => ! /\(Dark\)/.test( s.name || '' ) ) );
} );

test( 'an A4 rounded to whole points is the A4, so it is kept in millimeters', () => {
	assert.deepEqual( plain( m.snapPaper( 842, 595 ) ), [ 841.89, 595.28 ] );
	assert.deepEqual( plain( m.snapPaper( 595, 842 ) ), [ 595.28, 841.89 ] );
	assert.deepEqual( plain( m.snapPaper( 792, 612 ) ), [ 792, 612 ], 'a letter stays' );
	assert.deepEqual( plain( m.snapPaper( 800, 595 ) ), [ 800, 595 ] );
	const design = m.fromStored( { page: { width: '842pt', height: '595pt' }, colors: {}, elements: [] } );
	assert.deepEqual( [ design.page.w, design.page.h ], [ 841.89, 595.28 ] );
	assert.deepEqual( plain( m.toStored( design ).page ).width, '297mm' );
} );

test( 'a name is read in the Elements or in the Theme (a slug is never in both), the built-in one else', () => {
	const design = { page: { bg: '$base' }, colors: { text: { name: 'Text', light: '#111111', dark: '#eeeeee' } }, palette: [ { slug: 'base', color: '#fafafa' } ], elements: [] };
	assert.equal( m.color( design, '$base', 'light' ), '#fafafa' );
	assert.equal( m.color( design, '$base', 'dark' ), '#fafafa', 'a swatch has one value' );
	assert.equal( m.color( design, '$text', 'dark' ), '#eeeeee' );
	assert.equal( m.pageColor( design, 'light' ), '#fafafa' );
	assert.equal( m.color( design, '$background', 'light' ), 'transparent', 'built in' );
} );

test( 'a color may point at the Theme: it follows the swatch, in light and dark, and a loop ends as none', () => {
	const design = { page: { bg: '$fundo' }, colors: { fundo: { name: 'Fundo', light: '$branco', dark: '$preto' }, ciclo: { name: 'Ciclo', light: '$ciclo' } }, palette: [ { slug: 'branco', color: '#ffffff' }, { slug: 'preto', color: '#000000' } ], elements: [ { type: 'rect', fill: '$fundo' } ] };
	assert.equal( m.color( design, '$fundo', 'light' ), '#ffffff' );
	assert.equal( m.color( design, '$fundo', 'dark' ), '#000000' );
	assert.equal( m.colorIn( design, design.colors.fundo, 'dark' ), '#000000' );
	assert.equal( m.pageColor( design, 'light' ), '#ffffff' );
	assert.equal( m.color( design, '$ciclo', 'light' ), 'none' );
	const moved = { ...design, palette: [ { slug: 'branco', color: '#fafafa' }, design.palette[ 1 ] ] };
	assert.equal( m.color( moved, '$fundo', 'light' ), '#fafafa', 'the swatch changes what points at it' );
} );

test( 'renaming a swatch is followed by the colors that point at it', () => {
	const design = { page: { bg: '$fundo' }, colors: { fundo: { name: 'Fundo', light: '$branco', dark: '$preto' } }, palette: [ { slug: 'branco', name: 'Branco', color: '#ffffff' }, { slug: 'preto', name: 'Preto', color: '#000000' } ], elements: [] };
	const out = plain( m.renameSwatch( design, 'branco', 'Papel' ) );
	assert.equal( out.colors.fundo.light, '$papel' );
	assert.equal( out.colors.fundo.dark, '$preto' );
	assert.equal( m.color( out, '$fundo', 'light' ), '#ffffff' );
} );

test( 'what a text says is escaped as text and as an attribute value: <, >, &, " and \'', () => {
	const html = m.runsHtml( [ { text: `<img src=x onerror="a()"> & 'b'` } ], {}, 'en_US' );
	assert.equal( html, '&lt;img src=x onerror=&quot;a()&quot;&gt; &amp; &#39;b&#39;' );
	assert.equal( html.includes( '<' ), false );
} );


test( 'a text an earlier version kept as a string is runs once it is loaded', () => {
	const design = m.migrate( { page: { w: 100, h: 50 }, colors: {}, palette: [], elements: [ { type: 'text', id: 't', x: 0, y: 0, w: 10, h: 5, text: '{name} and **bold** [location] [nope]' } ] } );
	const el = design.elements[ 0 ];
	assert.equal( el.text, undefined );
	assert.deepEqual( JSON.parse( JSON.stringify( el.runs ) ), [ { field: 'name' }, { text: ' and ' }, { text: 'bold', b: true }, { text: ' ' }, { field: 'location' }, { text: ' [nope]' } ] );
} );


test( 'a fill that is a color of the Theme goes to the file and comes back, with the Elements and a literal beside it', () => {
	// The pickers keep a color of the Theme as "$slug"; the file says it as a preset and reads it back (docs/decisions/0050, 0051).
	assert.equal( m.color( { page: { bg: '$background' }, colors: {}, palette: [ { slug: 'white', color: '#ffffff' } ], elements: [] }, '$white', 'light' ), '#ffffff' );
	assert.equal( m.color( { page: { bg: '$background' }, colors: {}, palette: [], elements: [] }, '', 'light' ), 'none', 'no fill is no color' );
} );

test( 'currentColor is the document text color in the mode, and a fully transparent color paints nothing', () => {
	const design = { page: { bg: '$background' }, colors: { text: { name: 'Text', light: '#112233', dark: '#eeeeee' } }, palette: [], elements: [] };
	assert.equal( m.color( design, 'currentColor', 'light' ), '#112233' );
	assert.equal( m.color( design, 'CURRENTCOLOR', 'dark' ), '#eeeeee' );
	assert.equal( m.color( { ...design, colors: {} }, 'currentColor', 'light' ), '#000000', 'no Text color: the built-in black' );
	assert.equal( m.color( design, '#00000000', 'light' ), 'none' );
	assert.equal( m.color( design, '#0000', 'light' ), 'none' );
	assert.equal( m.color( design, '#ff000080', 'light' ), '#ff000080', 'half transparent is a color' );
} );

test( 'a new rectangle starts with a black fill and an empty stroke', () => {
	const rect = m.create( 'rect', { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } } );
	assert.equal( rect.fill, '#000000' );
	assert.equal( rect.stroke, '' );
	assert.equal( rect.strokeWidth, undefined, 'the border width starts empty' );
	assert.equal( m.create( 'ellipse', { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } } ).strokeWidth, undefined );
	assert.equal( m.create( 'group', { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } } ).strokeWidth, undefined );
	assert.equal( m.create( 'rect', { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } }, { fill: '', stroke: '#f00' } ).strokeWidth, 1, 'with a stroke remembered it shows' );
} );

test( 'a margin and a bleed keep the unit they were chosen in (mm when none)', () => {
	const stored = { page: { width: '297mm', height: '210mm', safe: '28.35pt', bleed: '3mm' }, colors: {}, elements: [] };
	const design = m.fromStored( stored );
	assert.equal( design.page.safeUnit, 'pt' );
	assert.equal( design.page.bleedUnit, undefined );
	assert.ok( Math.abs( design.page.safe - 28.35 ) < 0.01 );
	const back = plain( m.toStored( design ).page );
	assert.equal( back.safe, '28.35pt' );
	assert.equal( back.bleed, '3mm' );
	assert.equal( back.safeUnit, undefined, 'the unit is in the length, not a field' );
	const inches = m.toStored( { ...design, page: { ...design.page, safe: 36, safeUnit: 'in' } } );
	assert.equal( plain( inches.page ).safe, '0.5in' );
} );

test( 'a new rectangle or ellipse starts with the colors last set (an empty Fill too); other elements do not', () => {
	const design = { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } };
	const paint = { fill: '', stroke: '#ff0000' };
	for ( const type of [ 'rect', 'ellipse' ] ) {
		const el = m.create( type, design, paint );
		assert.equal( el.fill, '' );
		assert.equal( el.stroke, '#ff0000' );
		assert.equal( el.strokeWidth, 1, 'a remembered stroke has the width of 1 (the width itself is not remembered)' );
	}
	assert.equal( m.create( 'rect', design ).fill, '#000000', 'nothing remembered: the default' );
	assert.equal( m.create( 'rect', design ).stroke, '' );
	assert.equal( m.create( 'line', design, paint ).stroke, m.create( 'line', design ).stroke, 'a line keeps its own' );
	assert.equal( m.create( 'text', design, paint ).type, 'text' );
} );

test( 'a new text has no line height and no letter spacing of its own: the default is only shown', () => {
	const text = m.create( 'text', { colors: {}, elements: [], page: { w: 842, h: 595, safe: 0, bleed: 0 } } );
	assert.equal( text.lh, undefined );
	assert.equal( text.ls, undefined );
} );

