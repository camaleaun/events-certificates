# 0053 — The group element

Status: accepted (0.72). Builds on 0045–0047 (the elements are blocks in the block editor's store), 0050 (what a file says) and 0052.

## Context

A template made by hand from a Figma file stacked rectangles and SVGs that belong together, and only the page could place them. The core's Group block
(with its Row, Stack and Grid variations) is what a person expects: a box that holds other things and decides where they go.

## Decision

- **The element is `group`** (`draw/group` in the drawing package, `events-certificates/certificate-group` in a certificate file). It is a rectangle (fill, stroke, radius,
  corner shape) whose inner blocks are what it holds, down to **3 groups deep and 300 elements in all**. The authenticity code and the verification
  have their place on the certificate itself and are not held by a group (`Clean`, the layers' drag and `Group` all refuse them). A signature may be in a group (it is
  where the signer signs, wherever that is; only the old signature picture slot of an image stays out).
- **What is in a group is placed from its top-left corner**, and its anchors (`layout.ref: 'parent'`) are to the group's size: `x`, `y`, `%` and "fill" are of the group's box, not
  of the page's. The ids of what is in a group follow the group's (`group-2-rect-1`) and are made again by position like any id.
- **Variations, in the core's order: Group, Row, Stack, Grid** (`arrange`: none, `row`, `stack`, `grid`). Row and stack place what is in it one after the other inside the
  `padding`, with `gap` between, `justify` (start, center, end, between) along it and `align` (start, center, end, stretch) across it; an element with `grow` shares the room
  that is left (the core's Fill; no `grow` is Fit). A grid has `columns` of one width. There is no wrap. The group keeps its own size. `flowBoxes()` (TypeScript) and
  `Flow::boxes()` (PHP) are the same and tested with the same numbers. What a row, a stack or a grid places is not dragged or resized by hand.
- **A group shows what goes out of its box** unless it clips (`clip: true`, the "Clip content" box, off by default): a file writes only the exception.
- **The editor.** A new group asks for its layout (the core's variation picker: Group, Row, Stack, Grid); the Layout panel has the type, gap, padding, justification, alignment, the
  columns and Clip content. Several elements are selected (Shift in the layers or on the canvas) and grouped from the bar over them, the toolbar or Cmd+G (Shift+Cmd+G
  ungroups); the group is as big as they are together, in their place. In the layers a group is a node with its children, and dragging an element into or out of one keeps its
  place on the page (`src/editor/tree.ts`: `moveEl`, `groupEls`, `ungroupEl`). A group that changes how it places what is in it leaves them the size and place they have.
- **The server and the PDF.** `render.php` draws a group as its rectangle and a container with what is in it (the flow in a row, a stack or a grid); the quick generator flattens the
  tree (`flattenTree()`: a group is its rectangle, then what is in it on the page) and does not clip.

## Consequences

- A design with groups needs `camaleaun/draw` 0.18; an earlier version keeps `draw/group` as a block it does not know (a generic block), so nothing is lost.
- The text of an element takes a click only on its lines, not on the whole box: a tall box no longer hides what is under it. A rectangle or an ellipse with no fill is picked by its outline.
