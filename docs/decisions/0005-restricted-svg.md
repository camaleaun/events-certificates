# 0005 — SVG is kept as fields, never as markup

**Status:** Accepted (2026-11-05)

## Question

Logos, ornaments and signatures need vector art. How much SVG do we accept?

## Decision

An SVG element is **fields**: a viewBox size and a list of paths, each with its
path data (`d`, only path commands and numbers), a fill (a palette color, a
hex/rgb color, or none = the element's current color) and an optional even-odd
fill rule. No stroke, no other shapes, no groups, styles, scripts, links,
transforms or embedded content.

Pasting SVG code into a path field reads it into those fields (groups are
unwrapped, their fill passed to their paths) and keeps nothing else. There is
no code editor. Art with strokes is converted to filled outlines before
importing (for the Campus Connect assets we used Inkscape's "Stroke to Path").

## Why

- Markup is an injection surface (scripts, external references, CSS) on a page
  anyone can open; fields can be validated one by one, in the editor and again
  on the server.
- Strokes scale differently in the browser and in dompdf; filled outlines look
  the same everywhere.
- Colors as palette references follow the light/dark modes.

## Consequences

- Designs saved with SVG markup are migrated to fields when they load.
- Raster images stay possible (PNG, JPEG, GIF from the Media Library or a URL)
  for photos and logos without vector versions.

## Addendum (2026-11-26): the signature chooser

Settings now chooses the signature in a dialog (Upload files, Media Library, Edit SVG). SVG stays fields only, and the rules
are stricter than a `wp_kses()` list for icons:

- **An SVG file is never uploaded.** It is read in the browser (a `DOMParser` document is inert: no script runs and nothing
  loads, and it is never attached to the page), turned into paths and shown in the Edit SVG tab. Only pictures (PNG, JPEG,
  GIF, WebP) go to the media library, and the certificate keeps the ID of the attachment (ADR 0044; until 0.72 it kept a PNG data URI), never a file's address.
- **Refused before anything is parsed**, on both sides: code over 512 KB, a DTD or entity, a style sheet instruction, a
  script, an event handler. A file that carries them is not taken for a signature at all.
- **Only `<path>` elements are read**: at most 50 paths of at most 20,000 characters each (commands and numbers only), a fill
  that is a color, a fill rule, and a viewBox up to 5,000. The server cleans what the field posts again
  (`Signer\sanitize_signature()`, `Events\clean_vector()`); the numbers are `Signer\SVG_MAX_*` and `SVG_LIMITS` in
  `src/editor/model.ts`, and both sides have the same tests.
- The certificate's `<svg>` is built from those fields with escaped values, never from the file's markup.

## Addendum (2026-11-26): the media modal's SVG tab starts from code

The tab (named SVG) opens as a textarea for SVG code. A text that is a usable SVG (pasted, dropped as a file, or offered by the clipboard toast) is read at once into the same fields as before (viewBox and filled paths) and the tab shows them; nothing about what is kept changes. Only `<path>` elements are read, so shapes and text must be converted to paths first, and the text says so.

## Addendum (0.72): a size beside the viewBox

A drawing kept for a logo or a signature may carry `w` and `h`, the size it prefers to be shown at (a 24 × 24 icon shown at 512 × 512). Both
numbers or none, positive and within the viewBox limit, and not the viewBox itself; `Events\clean_vector()` and `Signer\sanitize_signature()`
keep them and drop anything else. The dialogs leave the fields empty with the viewBox as the placeholder, and pasted SVG code that says a
`width` and `height` fills them. The certificate gives the drawing that proportion inside its place in the template, centered
(`Render\fit_preferred()`); the place still decides the size. An SVG element of a template has its own `w` and `h` and has no such field.

## Addendum (0.72): the two dialogs are one, and a drawing is never empty

The editor's Edit SVG dialog and the media modal's SVG tab show the same body (`SvgFields`) and the same first step (`SvgSource`), and look the
same (the frame, the rows, the buttons: the rules in `src/editor/style.scss` that the media modal's own would otherwise change).

- The media modal's tab **starts with the info icon** of the WordPress icon library, 24 × 24 shown at 96 × 96 (`defaultDrawing()` in
  `src/editor/svg-draft.ts`, where the path lives once: the element a new SVG inserts and the example of the SVG code box use it too), instead of
  the SVG code box. A signature or logo that is already kept opens as it is.
- **Paste another SVG** goes to the SVG code box, in both dialogs (it replaces the 2026-11-26 rule that the tab opens as a textarea). The
  editor's dialog starts at the box when the element has no path to edit (the place of a signature); for an element of a template a pasted SVG
  keeps its title and its size on the page.
- **Every path can be removed**, in the dialogs and in the sidebar of an SVG element: when the last one is, the drawing gets the info icon again (a
  drawing back to 24 × 24 shown at 96 × 96; an element of a template keeps its size on the page, and the sidebar's change is one step to undo). The
  signer's sidebar keeps the last path: the signature is the organizer's drawing, and an icon there would be printed as a signature.
- A path with no fill of its own, which takes the current color, is a swatch of a circle in that color cut across by a stroke that shows on it
  (white over a dark color, near black over a light one): the "no color" of the palettes is an empty circle with a slash.
- The editor's dialog has the **Size** row too: the element's own size on the page in points (Apply changes it and makes its layout again), and
  no Title row in either.

## Addendum (0.72): the dialogs' column of fields is the sidebar's

The right column of the two SVG dialogs is the sidebar's: its width (280px inside its 1px line: `$evcert-sidebar-width` in `src/editor/style.scss`), its
groups (`SvgGroups`: the viewBox and the size, then the paths, each a `.evcert-inspector__section` of 16px with its line) and its scroll (only the
column scrolls; the preview and the buttons stay in view, and the media modal's tab has no scroll of its own). The sidebar and the dialogs render the
same components (`SvgGroups`, `ViewBoxRow`, `SizeRow`, `PathList`), so the rows measure the same (label 94px, control 146px, a path 246px). The
path data popover opens to the left of the column, as in the sidebar. "Paste another SVG" is the first thing in the column, above its groups, in both dialogs; the media modal keeps its tabs (with the SVG tab active) and the editor's dialog has none. Tested in
`tests/js/svg-layout.test.mjs`.
