# 0050 — An element is written as its block says it

Status: accepted (0.72). Changes how ADR 0045 and 0047 write an element; the editor's and the PDF's model does not change.

## Context

A design is kept as blocks (ADR 0045, 0047), but an element was written with the names of the editor's model: `"id":"svg-mwzbwe9c-1"`, `"w":96,"h":96`,
`"vw":24,"vh":24`. The file is read by people (and by other tools) as block markup, where an attribute is called `width`, and a drawing has a `viewBox`.
And the `id` the editor makes for an element means nothing: it is there to select, undo and list an element, not to say what it is.

## Decision

- In the file an element has **`width` and `height`** (points, as before) and an SVG a **`viewBox`** (`"0 0 24 24"`), not `w`, `h`, `vw` and `vh`:
  `<!-- wp:events-certificates/certificate-svg {"name":"SVG","x":0,"y":0,"width":96,"height":96,"viewBox":"0 0 24 24","paths":[…]} /-->`.
- An **id the editor made** (`<type>-<base36>-<n>`) is not written. It is made again when the file is read (`<type>-<position>`, not taken twice). The
  **ids that mean something stay**: `title`, `band-text`, and the `x-label`/`x-value` pairs (the label is not drawn when its value says nothing), which the
  PDF and the templates look for by id.
- It is the **boundary** that changes, and only there: `file_element()`/`to_file()`/`from_file()` in `includes/designs.php` (where a design is kept and
  read) and `toFile()`/`fromFile()` in `src/editor/dialect.ts` (where the editor saves, exports and imports). The model in memory (`w`, `h`, `vw`, `vh`,
  `id`), the camaleaun/draw package and the cleaning are the same.
- No compatibility with the earlier names (0.71 is not released); the templates of the development sites were written again.

## Addendum (2026-11-27)

A reference to a color is written as a block says a preset, `var:preset|color|<slug>` (a color of the Theme or of the Elements: the slug is one name, ADR 0051), in
any value of the file; the model keeps `$slug`. `color_refs()` in `includes/designs.php` and `colorRefs()` in `src/editor/dialect.ts`.

## Addendum (2026-11-27): the package writes the names of every element

The names in full are not the certificate's alone: `camaleaun/draw` 0.13/0.14 does it for every element, inside `Design` (`Names` in PHP, `names.ts` in
TypeScript), so the Draw plugin writes them too. `width` and `height`, `viewBox`, `lineHeight` and `letterSpacing` (also in a verification's hint and
code), `horizontal` and `vertical` in a layout, `bold` and `italic` in a run; no `id` that only counts (`rect-2`: made again by position when read); no
default name of the type (`Text`, `Rectangle`, `SVG`…), the certificate adding its own (`signature`, `image:logo`, `text:date-of-emission`) in the `names` option. `x`, `y` and the
coordinates of a line stay. What stays in the plugin is the colors (`color.palette`, `color.elements`, ADR 0051) and the references to a color
(`var:preset|color|slug`): `to_file()` / `from_file()` and `toFile()` / `fromFile()`.

