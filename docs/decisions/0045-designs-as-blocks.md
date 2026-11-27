# 0045 — A design is kept as blocks

Status: accepted (0.72). The block names and the SVG's inner blocks below were changed by ADR 0047 (blocks of this plugin, an SVG as one block). Supersedes the "one design JSON" of ADR 0004 for how a design is **kept**; the array the editor and the PDF read is
the same.

## Context

A template was a JSON of its own in `post_content`. The owner wants the drawing to be blocks and inner blocks like Gutenberg's, a plugin
that has only the draw block, and the PHP and JS that read and clean a drawing to be packages shared by both plugins.

## Decision

- A template is kept as **block markup**: the root `events-certificates/certificate` (page, colors, palette…), `draw/rect`, `draw/ellipse`,
  `draw/line`, `draw/path`, `draw/text`, `draw/image`, `draw/svg` with a `draw/svg-path` inner block for each path,
  `events-certificates/certificate-qr` and `-verification`, and `events-certificates/certificate-back` (the declaration sheet) with its own
  blocks. Every key of an element is an attribute of its block, so what goes in comes out the same; a type this version does not know keeps
  its place as `draw/element`.
- **The reading and cleaning are the `camaleaun/draw` package's** (https://github.com/camaleaun/draw, `packages/php` for Composer,
  `packages/js` for TypeScript, one repository): `Markup` (block comments, no WordPress needed), `Design` (design ↔ blocks), `Clean` (the
  strict cleaning of ADR 0044, with what WordPress decides given as callbacks), `Paths` (the path data parser, the drawing and its preferred
  size) and `Page` (the page with units, ADR 0004's addendum). This plugin takes it with Composer and `evcert deps` moves it under its own
  namespace (`EventsCertificate\Vendor\Camaleaun\Draw`, ADR 0032), so two plugins can carry the same library; the editor builds the
  TypeScript from `vendor/camaleaun/draw/packages/js/src` (an alias in `tsconfig.json`), so there is one source for both languages.
- **Nothing else changed in what reads a design**: `Designs\get()` gives the same array as before (`Designs\decode()` reads markup or the
  JSON of an earlier version), so `render.php`, the PDF and the snapshots are the same. `Designs\keep_json()` cleans what is saved and writes it
  as blocks, whatever form it came in; `Designs\content()` does the same for the starters, copies and imports. A template saved by an earlier
  version stays JSON until it is saved again.
- The editor loads markup or JSON and saves markup (`fromMarkup`/`toMarkup` with `toStored()` from ADR 0004).

## Consequences

- The block comments' JSON escapes `--`, `<`, `>` and `&` as the block editor does, so a text cannot end the comment or be read as markup.
- Snapshots of issued certificates keep the form they were stored in (JSON); `Certificates\design()` reads both.
- **Exported files** (the JSON and the ZIP of Export) carry each template's `design` as the block markup (`Bundle\for_file()`), and the importer
  reads it back to the array it works on (`Bundle\from_file()`), then cleans it and keeps it as blocks. There is no compatibility with a file whose
  designs are arrays (the form before blocks; 0.71 is not released): that template is left out. The designs of the snapshots in a bundle stay arrays: a certificate's design hash is the hash of that JSON.
- The **quick generator** (jsPDF) is given the design as an array by the server (`Designs\get()`), so it needed no change.
- Next: the plugin of the draw block itself (`plugins/draw` in the repository) and an editor that edits the blocks, not only saves them.
