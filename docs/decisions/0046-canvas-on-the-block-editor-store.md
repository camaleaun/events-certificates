# 0046 — The canvas edits blocks in the block editor's store

Status: accepted (0.72). Builds on ADR 0045 (a design is kept as blocks).

## Context

A design is kept as blocks, but the editor still works on its own `Design` array. The owner wants the drawing edited as blocks, with the
List View and the selection of the block editor, and the canvas working directly on its store; and the canvas shared with the Draw plugin.

## Decision

- The **canvas is the `DrawCanvas` of `camaleaun/draw`** (`packages/editor`): it draws the inner blocks of a frame and moves, resizes,
  nudges, deletes and inserts them by updating their attributes in `core/block-editor`. A drag is many updates of the same attributes of the
  same block, which the editor's history keeps as one step. The Draw plugin uses it today.
- The certificate editor goes there in steps; it equalled the editor it replaced, which was then removed (it lives only in the private `history` repository):
  1. **Layers** (done): `src/editor/blocks.tsx` is the bridge between the elements and the blocks — each element is a
     block of its type (the names ADR 0045 writes), an SVG's paths are inner blocks, the block's `clientId` is the element's `id`, the
     element's name is the block's `metadata.name`, and the front of the canvas is the first row. A `BlockEditorProvider` around the left
     panel shows the block editor's own List View; the selection is kept the same on both sides.
  2. **Canvas** (done): `src/editor/BlockCanvas.tsx` reads the elements from the store, draws them with the certificate's
     own `Shape` (now `src/editor/Shape.tsx`), and a selection, a drag (with the snap of the package) and a resize end in one update of the
     block's attributes, so the history keeps one step per gesture; the text editing in place, the picture placeholders and the element
     toolbar are still the editor's, handed to it as the overlay and the placeholder. The store is around the whole body of the editor.
     The shared `DrawCanvas` of the Draw plugin and this one are still two components: the certificate's draws far more (fields, fonts,
     anchors, screens, signature sample), and the two will meet when the Draw plugin needs the same.
  3. The old layers and canvas are gone, with no switch to bring them back: the Basic and Meetup templates, front and declaration page, draw the same
     HTML in both, and three moves, two resizes (with anchors) and the zoom ended in the same HTML.
- `__unstable*` history marks of the block editor are not used: the history groups what is already the same edit.

## Consequences

- The editor's script depends on `wp-block-editor` and `wp-blocks`, and its style on `wp-block-editor`.
- The block types of a drawing are registered in the editor only to be listed (their `edit` shows nothing: the canvas draws them).

- **Outputs**: the Draw packages and plugin make SVG only; paper (PDF) is the certificate's alone (dompdf, `render.php`), and nothing of it goes into the packages.
