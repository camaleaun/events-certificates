/**
 * The two SVG dialogs have the sidebar's column of fields (docs/decisions/0005): its width, its groups, and only it scrolls. The styles
 * are compiled as the build does and the rules are read; the sources are read for the groups. Run: npm run test:js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as sass from 'sass';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const css = ( file ) => sass.compile( path.join( root, file ), { loadPaths: [ root ], silenceDeprecations: [ 'import', 'global-builtin', 'mixed-decls' ], quietDeps: true, logger: sass.Logger.silent } ).css;
const rule = ( text, selector ) => {
	const found = [];
	const re = /([^{}]+)\{([^{}]*)\}/g;
	let m;
	while ( ( m = re.exec( text ) ) ) {
		if ( m[ 1 ].split( ',' ).map( ( s ) => s.trim() ).includes( selector ) ) {
			found.push( m[ 2 ] );
		}
	}
	return found.join( ';' );
};
const editor = css( 'src/editor/style.scss' );
const media = css( 'assets/scss/signature-chooser.scss' );

test( 'the column of fields has the sidebar\'s width inside its border, and only it scrolls', () => {
	assert.match( rule( editor, '.evcert-sidebar' ), /width:\s*280px/ );
	assert.match( rule( editor, '.evcert-svg-editor__body' ), /grid-template-columns:\s*minmax\(0,\s*1fr\)\s*calc\(280px \+ 1px\)/ );
	const fields = rule( editor, '.evcert-svg-editor__fields' );
	assert.match( fields, /border-left:\s*1px solid #e0e0e0/ );
	assert.match( fields, /overflow-y:\s*auto/ );
	assert.match( fields, /overflow-x:\s*hidden/ );
	assert.doesNotMatch( fields, /\bgap:/, 'The groups give the spaces, not the column.' );
	assert.doesNotMatch( fields, /padding-right/ );
	assert.doesNotMatch( rule( editor, '.evcert-svg-editor__body' ), /\bgap:|padding:/ );
} );

test( 'the media modal\'s tab does not scroll apart from the column, and has no padding of its own', () => {
	const content = rule( media, '.evcert-svg-tab__content' );
	assert.match( content, /overflow:\s*hidden/ );
	assert.match( content, /padding:\s*0/ );
} );

test( 'the groups are the sidebar\'s: a section of 16px with its line, one component for the sidebar and the dialogs', () => {
	const section = rule( editor, '.evcert-inspector__section' );
	assert.match( section, /padding:\s*16px/ );
	assert.match( section, /border-bottom:\s*1px solid #e0e0e0/ );
	const panel = readFileSync( path.join( root, 'src/editor/SvgPanel.tsx' ), 'utf8' );
	const fields = readFileSync( path.join( root, 'src/editor/SvgFields.tsx' ), 'utf8' );
	assert.ok( panel.includes( '<SvgGroups' ), 'The sidebar renders the groups.' );
	assert.ok( fields.includes( '<SvgGroups' ), 'The dialogs render the same groups.' );
	assert.ok( ! fields.includes( "label={ __( 'ViewBox'" ), 'The viewBox row is one component, not copied.' );
} );
