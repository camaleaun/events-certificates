/**
 * The toolbar over several selected elements, as the block toolbar of several blocks (block-editor/src/components/block-toolbar): first the icon of the type when all are the
 * same (or "multiple elements" when not) that turns them into a group, then the movers, the four ways to group them (Group, Row, Stack, Grid) and, last, the Options menu with
 * what can be done with all of them. docs/decisions/0056.
 */
import { DropdownMenu, MenuGroup, MenuItem, Popover, Toolbar, ToolbarButton, ToolbarDropdownMenu, ToolbarGroup, ToolbarItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowDown, arrowUp, chevronDown, chevronUp, copy, copySmall, lock, moreVertical, seen, trash, unlock, unseen } from '@wordpress/icons';
import { cut, elementIcon } from './icons';
import { VARIATIONS } from './GroupPanel';
import type { Element } from './model';

type Props = {
	anchor: globalThis.Element | null;
	els: Element[];
	/** The type when all are the same (its icon leads the bar), else null. */
	type: { type: string; slot?: string } | null;
	canGroup: boolean;
	/** Whether every one of them has to stay (Delete is off). */
	required: boolean;
	onGroup: ( arrange?: ( typeof VARIATIONS )[ number ][ 'arrange' ] ) => void;
	onOrder: ( to: 'up' | 'down' | 'top' | 'bottom' ) => void;
	onToggle: ( key: 'locked' | 'hidden' ) => void;
	onDuplicate: () => void;
	onRemove: () => void;
	onCopy?: () => void;
	onCut?: () => void;
};

export default function SelectionToolbar( { anchor, els, type, canGroup, required, onGroup, onOrder, onToggle, onDuplicate, onRemove, onCopy, onCut }: Props ) {
	if ( ! anchor ) {
		return null;
	}
	const allLocked = els.every( ( el ) => el.locked );
	const allHidden = els.every( ( el ) => el.hidden );
	const label = __( 'Multiple elements selected', 'events-certificates' );
	return (
		<Popover anchor={ anchor } variant="unstyled" animate={ false } focusOnMount={ false } placement="top-start" resize={ false } flip shift offset={ 12 } className="evcert-element-toolbar-popover">
			<Toolbar label={ __( 'Selection tools', 'events-certificates' ) } className="evcert-element-toolbar">
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon={ type ? elementIcon( type.type as Element[ 'type' ], type.slot ) : copy }
						label={ label }
						controls={ [
							VARIATIONS.map( ( v ) => ( {
								title: v.label,
								icon: v.icon,
								isDisabled: ! canGroup,
								onClick: () => onGroup( v.arrange ),
							} ) ),
						] }
					/>
				</ToolbarGroup>
				<ToolbarGroup className="evcert-mover">
					<ToolbarButton icon={ chevronUp } label={ __( 'Bring forward', 'events-certificates' ) } onClick={ () => onOrder( 'up' ) } />
					<ToolbarButton icon={ chevronDown } label={ __( 'Send backward', 'events-certificates' ) } onClick={ () => onOrder( 'down' ) } />
				</ToolbarGroup>
				{ canGroup && (
					<ToolbarGroup>
						{ VARIATIONS.map( ( v ) => (
							<ToolbarButton key={ v.label } icon={ v.icon } label={ v.label } onClick={ () => onGroup( v.arrange ) } />
						) ) }
					</ToolbarGroup>
				) }
				<ToolbarGroup>
					<ToolbarItem>
						{ ( toggleProps ) => (
							<DropdownMenu icon={ moreVertical } label={ __( 'Options', 'events-certificates' ) } popoverProps={ { placement: 'bottom-start' } } toggleProps={ toggleProps }>
								{ ( { onClose } ) => (
									<>
										<MenuGroup>
											{ onCopy && (
												<MenuItem icon={ copySmall } shortcut="⌘C" onClick={ () => { onCopy(); onClose(); } }>
													{ __( 'Copy', 'events-certificates' ) }
												</MenuItem>
											) }
											{ onCut && (
												<MenuItem icon={ cut } shortcut="⌘X" onClick={ () => { onCut(); onClose(); } }>
													{ __( 'Cut', 'events-certificates' ) }
												</MenuItem>
											) }
											<MenuItem icon={ copy } shortcut="⌘D" onClick={ () => { onDuplicate(); onClose(); } }>
												{ __( 'Duplicate', 'events-certificates' ) }
											</MenuItem>
										</MenuGroup>
										<MenuGroup>
											<MenuItem icon={ arrowUp } onClick={ () => { onOrder( 'top' ); onClose(); } }>
												{ __( 'Bring to front', 'events-certificates' ) }
											</MenuItem>
											<MenuItem icon={ arrowDown } onClick={ () => { onOrder( 'bottom' ); onClose(); } }>
												{ __( 'Send to back', 'events-certificates' ) }
											</MenuItem>
										</MenuGroup>
										<MenuGroup>
											{ canGroup && (
												<MenuItem icon={ VARIATIONS[ 0 ].icon } shortcut="⌘G" onClick={ () => { onGroup(); onClose(); } }>
													{ __( 'Group', 'events-certificates' ) }
												</MenuItem>
											) }
											<MenuItem icon={ allLocked ? unlock : lock } onClick={ () => { onToggle( 'locked' ); onClose(); } }>
												{ allLocked ? __( 'Unlock', 'events-certificates' ) : __( 'Lock', 'events-certificates' ) }
											</MenuItem>
											<MenuItem icon={ allHidden ? seen : unseen } onClick={ () => { onToggle( 'hidden' ); onClose(); } }>
												{ allHidden ? __( 'Show', 'events-certificates' ) : __( 'Hide', 'events-certificates' ) }
											</MenuItem>
										</MenuGroup>
										<MenuGroup>
											<MenuItem
												icon={ trash }
												shortcut="⌫"
												disabled={ required }
												info={ required ? __( 'Required: they can be moved, not deleted.', 'events-certificates' ) : undefined }
												onClick={ () => { onRemove(); onClose(); } }
											>
												{ __( 'Delete', 'events-certificates' ) }
											</MenuItem>
										</MenuGroup>
									</>
								) }
							</DropdownMenu>
						) }
					</ToolbarItem>
				</ToolbarGroup>
			</Toolbar>
		</Popover>
	);
}
