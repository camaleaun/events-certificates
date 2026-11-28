/**
 * The elements of a design as blocks of the block editor (docs/decisions/0045, 0046, 0047): each element is a block of its type (an SVG
 * has its paths as an attribute), and the block's clientId is the element's id, so a selection means the same in both. The block editor's own
 * List View shows them (as layers) and its store is where a drawing is edited.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { BlockEditorProvider, __experimentalListView as ListView, store as blockEditorStore } from '@wordpress/block-editor';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { elementIcon } from './icons';
import { stockName } from './base';
import { BLOCK_NAMES, FALLBACK } from './dialect';
import type { Element } from './model';
import { groupsDeep, canHold, flatten, isGroup, moveEl, findEl, mapEl, MAX_DEPTH, MAX_ELEMENTS } from './tree';

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
			// What a group holds is anything but the certificate's own parts (tree.ts says which: the layers' drag is checked there).
			edit: () => null,
			save: () => null,
		} as never );
	} );
}

/** Elements as blocks, in the order of the design: the first is the back of the canvas, the last the front (the order they were added in; the layers list them so). */
export function toBlocks( elements: Element[] ): Instance[] {
	return elements.map( ( element ) => {
		const { type, id, name, ...attributes } = element as Element & { children?: Element[] };
		// What is in a group is its inner blocks, all the way down.
		delete ( attributes as { children?: unknown } ).children;
		return {
			clientId: id,
			name: BLOCK_OF[ type ] || FALLBACK,
			isValid: true,
			// The List View shows a block's own name (metadata.name) when it has one.
			attributes: { ...attributes, ...( BLOCK_OF[ type ] ? {} : { type } ), ...( name ? { metadata: { name: stockName( name ) } } : {} ) },
			innerBlocks: isGroup( element ) ? toBlocks( element.children || [] ) : [],
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
		if ( type === 'group' ) {
			element.children = toElements( block.innerBlocks || [] );
		}
		return element as unknown as Element;
	} );
}

/** Keeps the editor's selection and the block editor's the same one. */
function SyncSelection( { selected, onSelect, onMany }: { selected: string | null; onSelect: ( id: string | null ) => void; onMany?: ( ids: string[] ) => void } ) {
	// More than one selected (a range, with Shift): the editor needs to know to group them.
	const many = ( useSelect as any )( ( select: any ) => ( select( blockEditorStore ).getSelectedBlockClientIds() as string[] ), [] ) as string[];
	const more = many.length > 1 ? many : [];
	useEffect( () => {
		onMany?.( more );
		// Only when the selection changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ more.join( ',' ) ] );
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

/**
 * Tells the block editor's store that every group holds blocks of its own (what InnerBlocks does when a block draws them): without it the List View's menu does not offer to
 * duplicate, copy or move what is in a group, as the store takes the group for a block that has no list.
 */
function GroupLists() {
	const ids = ( useSelect as any )(
		( select: any ) => {
			const walk = ( blocks: Instance[] ): string[] => blocks.flatMap( ( b ) => ( b.name === BLOCK_OF.group ? [ b.clientId, ...walk( b.innerBlocks ) ] : walk( b.innerBlocks ) ) );
			return walk( select( blockEditorStore ).getBlocks() ).join( ',' );
		},
		[]
	) as string;
	const { updateBlockListSettings } = ( useDispatch as any )( blockEditorStore ) as { updateBlockListSettings: ( id: string, settings: Record< string, unknown > ) => void };
	useEffect( () => {
		ids.split( ',' ).filter( Boolean ).forEach( ( id ) => updateBlockListSettings( id, { templateLock: false } ) );
		// Only when the groups change.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ ids ] );
	return null;
}

/** Whether a tree of elements may be: no group deeper than MAX_DEPTH, no more than MAX_ELEMENTS, and nothing in a group that has its place on the certificate itself. */
export function validTree( list: Element[] ): boolean {
	return flatten( list ).length <= MAX_ELEMENTS && list.every( ( el ) => groupsDeep( el ) <= MAX_DEPTH ) && list.every( ( el ) => ! isGroup( el ) || ( el.children || [] ).every( canHold ) );
}

/**
 * What the layers' drag did, as elements: an element that changed of group keeps its place on the page (its numbers, or its anchors, are written from
 * the new origin), and a tree the rules do not allow is refused (null). `page` is the page the elements are placed in.
 */
export function reparented( before: Element[], after: Element[], page: Parameters< typeof moveEl >[ 1 ] ): Element[] | null {
	if ( ! validTree( after ) ) {
		return null;
	}
	const parent = ( list: Element[], id: string ): string | null | undefined => {
		const walk = ( items: Element[], up: string | null ): string | null | undefined => {
			for ( const el of items ) {
				if ( el.id === id ) {
					return up;
				}
				const inner = walk( isGroup( el ) ? el.children || [] : [], el.id );
				if ( inner !== undefined ) {
					return inner;
				}
			}
			return undefined;
		};
		return walk( list, null );
	};
	let out = after;
	for ( const el of flatten( after ) ) {
		const was = parent( before, el.id );
		const now = parent( after, el.id );
		if ( was !== undefined && was !== now ) {
			// The element as it would be if it had been moved there, with its place kept.
			const moved = findEl( moveEl( before, page, el.id, now ?? null ), el.id );
			if ( moved ) {
				out = mapEl( out, el.id, ( current ) => ( { ...moved, ...( isGroup( current ) ? { children: current.children } : {} ) } as Element ) );
			}
		}
	}
	return out;
}

/** The block editor's store for the editor: the elements as blocks, the selection kept the same, what changes in it written back. */
export function BlocksProvider( { elements, page, selected, onSelect, onMany, onElements, children }: { elements: Element[]; page: Parameters< typeof moveEl >[ 1 ]; selected: string | null; onSelect: ( id: string | null ) => void; onMany?: ( ids: string[] ) => void; onElements: ( next: Element[] ) => void; children: React.ReactNode } ) {
	// A key that changes when a change was refused, so the store goes back to the elements.
	const [ refused, setRefused ] = useState( 0 );
	const blocks = useMemo( () => toBlocks( elements ), [ elements ] );
	const change = ( next: Instance[] ) => {
		const after = reparented( elements, toElements( next ), page );
		if ( after ) {
			onElements( after );
		} else {
			setRefused( ( n ) => n + 1 );
		}
	};
	return (
		<BlockEditorProvider key={ refused } value={ blocks } onChange={ change } onInput={ change } settings={ {} }>
			<SyncSelection selected={ selected } onSelect={ onSelect } onMany={ onMany } />
			<GroupLists />
			{ children }
		</BlockEditorProvider>
	);
}

/** The layers of a design: the block editor's List View (inside a BlocksProvider). */
export function BlockLayers() {
	// The groups start closed on every load (the group of the selected element opens by itself, as in the core's List View).
	return <ListView isExpanded={ false } showBlockMovers={ false } />;
}
