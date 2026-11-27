# 0052 — Units on every length, an empty color, the order of the layers

Status: accepted (0.72). Adds to 0049 (the editor's shell) and 0051 (colors).

## Context

Building a template by hand from a Figma file showed what the editor lacked: sizes and positions only in points, a corner radius only in points,
a border width with no unit, color rows that ended in a word ("Default", "None"), a layers list whose order did not match what was added, and
a close button for the settings that was not the core's.

## Decision

- **A pixel is a point** (1/72 in, as in `camaleaun/draw`). Size and position accept pt, px, mm, cm and in, with decimals (two, three in inches).
  The unit lives in the length of the layout (`"42.5mm"`), not in a separate field; x, y and the size stay numbers in points in the block's attributes.
  Switching the unit rewrites the same length in the new one (four decimals, no resize), so going pt → cm → pt does not drift.
- **Alignment sets the offsets.** Left, Center, Right and Fill write the anchor with an offset of 0 (`alignAxis()`), instead of keeping the old ones.
- **"Relative to" (Page / Margin) is disabled, with nothing selected, when the safe margin is 0**: both boxes are the same. The stored reference is kept.
- **A corner radius is points or a percentage** of the shorter side (0 to 50%; 50% is a pill): `"25%"` in the block's `radius` list, a unit selector
  for all the corners and for each. The draw package resolves it (`radiusPt()` in JS, `Svg::radius()` in PHP) so the page, the PDF and the quick
  generator draw the same. The border width has its unit, px.
- **Colors.**
  - Every color popover offers the Theme and the Elements, and a row has no trailing text: the name of the color is the tooltip.
  - **Empty is empty.** Fill and Stroke clear to no value (nothing is painted) and show the plain empty circle; a new rectangle, ellipse or path
    starts with a `#000000` fill and no stroke. The SVG's own color has the same empty circle for "the current color".
  - `currentColor` is understood: it is the document's text color (`$text`, in the mode), not the black an image would make of it; and a fully
    transparent color (`#00000000`) paints nothing. Neither is a default, but a design that has them draws them well.
- **Layers** list the elements in the order of the design: the first is the back, the last the front, which is the order they were added in and
  the order of the file. A new element goes to the end of the list and is in front. (The first version of this decision listed the front first;
  the owner replaced it.)
- **Close settings** is the core's: the `close` icon in a compact button, a tooltip with the shortcut, and Shift + Cmd/Ctrl + comma (`event.code ===
  'Comma'`, because with Shift the key is "<") opens and closes the settings; the Settings button of the top bar has the same shortcut.

## Consequences

- Designs saved before keep their lengths: a number is points, a length with a unit keeps it.
- The draw package gained `%` radii and `currentColor` (v0.15.0, v0.15.1); the markup docs list them.
- Elements added from now on have a literal black fill; the Fill of old ones is not touched.
