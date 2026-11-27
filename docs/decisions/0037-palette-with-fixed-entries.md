# 0037 — The palette has two fixed entries, a Light value and an optional Dark one, and a white page is not painted in a PDF

**Status:** Accepted (2026-11-26). The fixed entries (Base, Contrast, Background, Text) are replaced by [0051](0051-colors-without-fixed-entries.md): nothing is fixed any more.

## Context

The template editor had a "Palette for" switch (Light or Dark) over core's palette editor, and the page background set in a row of
its own. Every entry could be renamed or removed, Background and Text included, and a removed one left elements pointing at nothing.
The Site Editor's Styles › Colors is the model people already know: one "Palette" row that opens "Edit palette".

## Decision

- **The palette is one list.** Background and Text are fixed: always there, their names cannot change and they cannot be removed,
  but their values can. The other entries are the template's own (`custom-…`): add, rename, recolor, remove. The page background
  is the Background entry (`page.bg` stays in the JSON as `"$background"`, so older readers and bundles keep working).
- **An entry has a Light value and an optional Dark one**; without Dark, Light is used in dark mode (`colorOf()` in
  `src/editor/model.ts`, `palette_value()` in `includes/render.php`). Defaults: Background white and black, Text black and white.
- **Existing designs keep their colors.** `normalizeColors()` (editor, on load and import) and `Render\with_palette()` (PHP, on
  read) do the same thing: add what is missing, fold the page color into Background, a transparent Light background becomes white,
  put Background and Text first. A Background entry that elements use, and that the page color was not, moves to
  `custom-background`. Both are idempotent and share their cases (`tests/fixtures/palette-cases.json`).
- **A pure white page is not painted in a PDF**, so the paper shows through: dompdf (`for_pdf` in `artwork()` and
  `declaration()`) and jsPDF (`skipWhitePage` of the PDF backend). The certificate and verification pages, the preview image and
  the QR code's own backing keep their white.
- **Removing an entry that elements use** swaps the references for its Light value, so nothing changes on paper, and says so.
- **The editor's palette picker chooses by position**, not by value: two entries can share a color.

## Left out

- A row per element type (Link, Captions, Button…) as in Styles: the palette list is the quick-change list.
- Gradients, and the Site Editor's preset palettes.

## Consequences

- A design saved by this version has `background` and `text` first and `page.bg` equal to `"$background"`.
- Both normalizers must change together; the shared cases fail whichever is left behind.

> Superseded in part by [0038](0038-palette-separate-from-colors.md): the palette is a separate list of swatches, not the list of colors.
