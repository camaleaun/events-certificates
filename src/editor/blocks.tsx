/**
 * The elements of a design as blocks of the block editor (docs/decisions/0045, 0046, 0047): each element is a block of its type (an SVG
 * has its paths as an attribute), and the block's clientId is the element's id, so a selection means the same in both. The block editor's own
 * List View shows them (as layers) and its store is where a drawing is edited.
 */
import { useEffect, useMemo } from '@wordpress/element';
import { BlockEditorProvider, __experimentalListView as ListView, store as blockEditorStore } from '@wordpress/block-editor';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { elementIcon } from './icons';
import { stockName } from './base';
import { BLOCK_NAMES, FALLBACK } from './dialect';
import type { Element } from './model';

/** The block of each element type (the names the design is written with: src/editor/dialect.ts). */
export const BLOCK_OF = BLOCK_NAMES;
const TYPE_OF = Object.fromEntries( Object.entries( BLOCK_OF ).map( ( [ type, name ] ) => [ name, type ] ) );

type Instance = { clientId: string; name: string; isValid: boolean; attributes: Record< string, unknown >; innerBlocks: Instance[] };

/** Makes the editor know the blocks of a drawing (only to list them: what draws them is the canvas). */
export function registerBlocks( titles: Record< string, string > ) {
	[ ...Object.values( BLOCK_OF ), FALLBACK ].forEach( ( name ) => {
		if ( getBlockType( name ) ) {
			return;
		}
		const type = TYPE_OF[ name ];
		registerBlockType( name, {
			apiVersion: 3,
			title: titles[ type ] || name,
			category: 'design',
			icon: type ? elementIcon( type as Element[ 'type' ] ) : 'edit',
			attributes: {},
			supports: { html: false, reusable: false, className: false, customClassName: false },
			edit: () => null,
			save: () => null,
		} as never );
	} );
}

/** Elements as blocks, in the order of the design: the first is the back of the canvas, the last the front (the order they were added in; the layers list them so). */
export function toBlocks( elements: Element[] ): Instance[] {
	return elements.map( ( element ) => {
		const { type, id, name, ...attributes } = element;
		return {
			clientId: id,
			name: BLOCK_OF[ type ] || FALLBACK,
			isValid: true,
			// The List View shows a block's own name (metadata.name) when it has one.
			attributes: { ...attributes, ...( BLOCK_OF[ type ] ? {} : { type } ), ...( name ? { metadata: { name: stockName( name ) } } : {} ) },
			innerBlocks: [],
		};
	} );
}

/** Blocks back to elements: the same order. */
export function toElements( blocks: Instance[] ): Element[] {
	return blocks.map( ( block ) => {
		const type = TYPE_OF[ block.name ] || ( block.attributes.type as string );
		const { metadata, ...attributes } = block.attributes as Record< string, unknown > & { metadata?: { name?: string } };
		delete attributes.type;
		const element: Record< string, unknown > = { type, id: block.clientId, ...( metadata?.name ? { name: metadata.name } : {} ), ...attributes };
		return element as unknown as Element;
	} );
}

/** Keeps the editor's selection and the block editor's the same one. */
function SyncSelection( { selected, onSelect }: { selected: string | null; onSelect: ( id: string | null ) => void } ) {
	const inStore = ( useSelect as any )( ( select: any ) => select( blockEditorStore ).getSelectedBlockClientId() as string | null, [] ) as string | null;
	const { selectBlock, clearSelectedBlock } = ( useDispatch as any )( blockEditorStore ) as { selectBlock: ( id: string ) => void; clearSelectedBlock: () => void };
	useEffect( () => {
		if ( inStore !== selected ) {
			if ( selected ) {
				selectBlock( selected );
			} else {
				clearSelectedBlock();
			}
		}
		// Only when the editor's selection changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ selected ] );
	useEffect( () => {
		if ( inStore && inStore !== selected && ! inStore.includes( ':' ) ) {
			onSelect( inStore );
		}
		// Only when the block editor's selection changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ inStore ] );
	return null;
}

/** The block editor's store for the editor: the elements as blocks, the selection kept the same, what changes in it written back. */
export function BlocksProvider( { elements, selected, onSelect, onElements, children }: { elements: Element[]; selected: string | null; onSelect: ( id: string | null ) => void; onElements: ( next: Element[] ) => void; children: React.ReactNode } ) {
	const blocks = useMemo( () => toBlocks( elements ), [ elements ] );
	const change = ( next: Instance[] ) => onElements( toElements( next ) );
	return (
		<BlockEditorProvider value={ blocks } onChange={ change } onInput={ change } settings={ {} }>
			<SyncSelection selected={ selected } onSelect={ onSelect } />
			{ children }
		</BlockEditorProvider>
	);
}

/** The layers of a design: the block editor's List View (inside a BlocksProvider). */
export function BlockLayers() {
	return <ListView isExpanded showBlockMovers={ false } />;
}

/** The store around the editor when it is wanted (ADR 0046); otherwise the editor as it is. */
export function BlocksIf( { enabled, children, ...props }: { enabled: boolean; elements: Element[]; selected: string | null; onSelect: ( id: string | null ) => void; onElements: ( next: Element[] ) => void; children: React.ReactNode } ) {
	return enabled ? <BlocksProvider { ...props }>{ children }</BlocksProvider> : <>{ children }</>;
}
