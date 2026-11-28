# 0047 — A certificate is kept with blocks of its own, and an SVG is one block

Status: accepted (0.72). Changes the names ADR 0045 wrote; the rest of 0045 stands.

## Context

ADR 0045 kept a design as blocks of the Draw plugin (`draw/rect`, `draw/text`…) and an SVG as `draw/svg` with a `draw/svg-path` inner block
for each path. A certificate is not a drawing of the Draw plugin: it is a document of this plugin, and a file of a template should not need
another plugin's blocks to be read. And the inner blocks of an SVG are right for the Draw plugin (a path is a block in its List View) but
heavy for a template, where the paths of an SVG are one thing: the shape.

## Decision

- Every shape of a design is a block of **this plugin**: `events-certificates/certificate-text`, `-rect`, `-ellipse`, `-line`, `-path`,
  `-image`, `-svg`, next to the ones it already had (`-qr`, `-verification`, `-back`) and the date of emission (`-date-of-emission`, a text with a slot: ADR 0055); a type this version does not know keeps its place as
  `events-certificates/certificate-element`. A design uses no `draw/*` block.
- An SVG is **one block** with its paths in the `paths` attribute, and no inner blocks:
  `<!-- wp:events-certificates/certificate-svg {"name":"Seal","x":600,"y":480,"width":120,"height":44,"viewBox":"0 0 120 44","paths":[{"d":"M0 0L10 10Z","fill":"$text"},{"d":"…"}]} /-->` (the names of the attributes: ADR 0050).
- This is **a parameter of the `camaleaun/draw` package**, not a fork: `Design::to_markup()`/`from_markup()` (PHP) and `toMarkup()`/`fromMarkup()`
  (TypeScript) take options (`blocks`, `fallback`, `paths`), and what they do without options is the native way of the blocks, as the
  Draw plugin keeps it (`draw/*` and `draw/svg-path` inner blocks). The options of this plugin are `block_options()` in `includes/designs.php`
  and `BLOCK_OPTIONS` in `src/editor/dialect.ts`, the same names. The package is 0.7.0.
- The block editor store of the editor (ADR 0046) registers the same names (`src/editor/blocks.tsx`), so the List View and the selection work
  as they did; a block has no inner blocks, so the layers of an SVG are one row.
- **No compatibility**: markup with `draw/*` names is not read as a design (0.71 is not released); the templates of the development sites
  were written again. JSON of an earlier version is still read, as ADR 0045 said.

## Consequences

- Strict cleaning (ADR 0044) is the same: the paths are cleaned one by one on the array the package gives (`Clean`).
- A file of a template is smaller and reads as one block per shape.
- The Draw plugin and the certificate no longer share block names, only the package; the canvas of the editor does not depend on the Draw
  blocks being registered.
