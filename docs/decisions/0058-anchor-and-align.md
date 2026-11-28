# 0058 — The anchor and the alignment are two things; the font size takes decimals

Status: accepted (2026-11-28). Replaces the rule "Alignment sets the offsets" of 0052. Builds on 0004 (the anchors of the model), 0053 (groups) and 0056 (selection).

## Context

0052 (commit 899fe14) made the Horizontal and Vertical buttons (Left, Center, Right, Fill) write the anchor **with an offset of 0**. They then moved the element to a side and, at the same
time, were the only way to change **what the element is measured from**; the old place (and the unit the axis was written in) was lost. The two are different things and both are needed: an
element anchored to the right stays at the right when the page changes size, whatever its place today; and a designer also wants to put it at the left, the center or the right of its box.

## Decision

- **The anchor** (the Horizontal and Vertical buttons) is what the element is measured from. Changing it **keeps the element where it is**: the offset is written again from the new anchor, in
  the unit the axis had (`anchorAxis()`, `axisAt()` in `src/editor/size.ts`). Only the keys the mode reads are written; a drawing that says no size keeps saying none.
- **Align** (an Align row, six icons: left, center, right, top, middle, bottom, under Relative to) **moves** the element to that side or the center of its box (the page, the margin or the group)
  and **keeps its anchor** (`alignAxis()`): anchored to the end and aligned to the start, the offset "right" is the box minus the width. An axis that fills has nothing to align. The row is not
  shown for what a row, a stack or a grid places.
- **Several elements** align to the box they make together (`alignEls()` in `src/editor/tree.ts`; the selection bar's Options menu and the card of the selection): each moves by the difference with
  the editor's own `shift`, so it keeps its anchor and its unit. What is locked or placed by a flow stays and still counts in the box. One undo step.
- No change to the file, to PHP or to camaleaun/draw: the model (`Axis`, `Layout`) already says all of this.
- **The font size takes decimals**: WordPress's `FontSizePicker` rounds a custom size to the step of its unit (12.5 pt became 13). `src/editor/FontSize.tsx` has the list (8 to 42 pt) and a custom
  field with the units pt, px, mm, cm and in, `step="any"`, arrows and slider by 0.1; what is typed is kept to three decimals (`fontSizePt()`) and shown with no zeros at the end
  (`fontSizeText()`). The element keeps points, and the file, the PDF (`font-size:%.3Fpt`) and the canvas already took decimals.

## Consequences

- A length is written with two decimals in its unit (0.01 mm is 0.028 pt): an anchor or an alignment written back resolves to the box within 0.02 pt.
- The Horizontal "Fill" button anchors the axis to both sides keeping the element where it is; making it fill the page is the Fill row (Width, Height, Both).
