# 0056 — Selecting, grouping and copying several elements

Status: accepted (0.72). Builds on 0045–0047 (the elements are blocks in the block editor's store), 0053 (the group element) and 0054 (the text of an element).

## Context

Selecting several elements and grouping them was clumsier in the template editor than in the post editor: the store of the block editor can only hold a run of siblings, so a selection of
several was shown (dashed boxes, a floating bar) but Delete, the arrows and Duplicate acted on the last single element; there was no toggle, no box to select with, no copy and paste, and the
bar over the selection had nothing but the four ways to group.

## Decision

- **The selection is the editor's own list of ids** (`Selection` in `blocks.tsx`: `ids` and the `anchor` of a range). The store of the layers mirrors it when it is one element or a run of
  siblings (`SyncSelection`) and what is chosen in the layers is taken back; a selection that is not a run (a Cmd-click toggle, a box) is only in the editor, and the layers' own keys then
  act on the row that has the focus. The pure parts are in `src/editor/selection.ts`: `rangeIds` and `toggleIds` lift both ends to one list as the core does, `selectAllIds` (the level, then its
  group), `escapeIds` (several → the first → its group → nothing), `clickTarget` (the outermost group, or the level of what is selected) and `marqueeIds`.
- **Gestures.** Shift+click is a range in the order of the list and Cmd/Ctrl+click adds or takes out one (as in the post editor). A box dragged on the empty page, or on a locked element (a
  click on that still selects it), selects the elements it touches (not locked or hidden ones): a group the box holds whole is selected as a group, one it only touches gives the elements in it that the box touches (so several inner
  elements are all selected, from different groups too), and the box that begins inside the group of what is selected looks only at that group. Pressing on one of several moves them all (one undo step;
  not the locked ones or those placed by a row, a stack or a grid). A click on something in a group selects the outermost group and a double click goes down one level (as in Figma); the
  element a click would select is outlined under the pointer. Shift+arrow still moves by 10 (the convention of design tools), it does not extend the selection.
- **Actions on all selected, one undo each** (`removeEls`, `duplicateEls`, `reorderEls`, `setFlag`, `nudgeEls` in `tree.ts`): Delete (what has to stay is kept, with a notice), Cmd+D and
  Cmd+Shift+D (once: a key the layers already took is not handled again), Cmd+A, Esc, the arrows, lock, hide and order.
- **Look.** Several selected elements are a fill of the theme color at 40% (as the post editor's selected blocks) and the box of them all is outlined; one element keeps its 1px outline
  and handles.
- **The toolbar** over several (`SelectionToolbar`): the icon of the type when all are the same, or the "multiple" icon, as the menu that turns them into a Group, Row, Stack or Grid; the movers;
  the four ways to group; and an Options menu with icons (Copy, Cut, Duplicate; Bring to front, Send to back; Group, Lock, Hide; Delete). The menu of a layer has Group, Ungroup, Lock and Hide
  with the editor's rules (the core's would make a group with no place, and write attributes the design does not have: the blocks turn those off).
- **Copy, cut and paste** (`clipboard.ts`): a copy writes the elements as they are on the page (`application/x-events-certificates+json`, with the colors they use), the same blocks the layers
  copy, as markup, in `text/html` and in `text/plain` (a text editor gets the blocks, as with the post editor; amended 2026-11-28: it was the words of the texts). The words of a
  text are copied by selecting them in the text's editing mode (ADR 0054), and text the user selects anywhere else is copied as that text, not as the selected elements. A paste reads those, or plain text (a new Text); new ids; into the selected group when only that is selected, else right
  after the one at the front of the selection, in its own list, else at the end of the page; never in place of the selection. The same copy pasted again moves a little more each time; the code
  of authenticity and the verification are not pasted twice; colors of the theme the other template lacks become their values. The text editor keeps its own copy and paste (ADR 0054).

## Consequences

- A selection that is not a run of siblings is not in the layers' store: their shortcuts (copy, delete, duplicate) then act on the focused row only.
- The 40% fill tints the page when a large element is selected (select all with a full-page background).
- The copy puts block markup of this plugin on the clipboard: pasted in the post editor it makes `events-certificates/certificate-*` blocks, as the layers' copy already did.
- Alt-drag to duplicate is not done (Alt still turns snapping off); a marquee begins on the page, not on the gray around it.
