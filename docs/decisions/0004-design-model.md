# 0004 — One design JSON in points

**Status:** Accepted (2026-11-04)

## Decision

A design is one JSON document (stored in the `evcert_design` post type's
content): the page (size in points, background, safe margin, bleed), a color
palette with a **light and a dark** value per color, preview sample data, and a
list of elements (text with runs and fields, rectangles with corner shapes,
ellipses, lines, images, SVG, the authenticity QR). Positions are in points;
elements may be **anchored** (start / center / end / stretch, relative to the
page or the safe margin) with lengths in pt, mm, cm, in, % or `calc()`.

The editor draws the JSON as SVG/HTML; the server renders the same JSON to
HTML for dompdf (0002). Export and import use the same JSON, so a design moves
between sites as one file (a Campus Connect design is kept outside the plugin
because of the WordPress trademarks).

## Why

- One source of truth for screen and PDF avoids two renderers drifting.
- Points match print; anchors make the same design adapt to other page sizes
  and to screens (0009).
- Text is runs (text and fields, with bold/italic) in memory (a file and the clipboard say them as text, see 0054):
  fields keep their formats (dates follow the site format by default) and the
  editor shows them as chips.

## Consequences

- The Base design ships as `templates/base-design.json`, its texts translated
  when a design starts from it. Nothing is required (since 0.72 not even the name: a design may be as empty as its author wants; before, a
  design that lacked the name got it added). The QR code, the code and the verification hint are optional parts of a design: they work when the design
  has them (and verification is on, and the issuer leaves them on the first page), never forced into a design.

## Addendum (0.72): the page is kept with units

The page's lengths are written with their unit, as CSS says them, and a design has **no version**: the unit says what it is.
`"page": { "width": "297mm", "height": "210mm", "backgroundColor": "$background", "safe": "10mm" }`, and `"safe"` and `"bleed"` only when there is one (a page with no safe margin does not say so). The
width and height are in the unit that makes both round, because the points the editor works in are not (an A4 is 841.89 by 595.28 pt): an A4 is
`297mm` by `210mm`, a letter `11in` by `8.5in`, and points only when neither unit is round (`Page::size()` in PHP, `pageSize()` in TypeScript, in the
camaleaun/draw package 0.10). The safe margin and the bleed are in mm because that is how the editor shows them. The editor and the PDF still work in
points (`w`, `h`, `bg`, `safe`, `bleed`): `toStored()`/`fromStored()` (`model.ts`) and `Render\page_stored()`/`page_in_points()` (`render.php`) are the
two ends. What an earlier version wrote (a point as `px`, `"841.89px"`, with `"version": 2`, or numbers in points) is still read and kept this way the
next time it is saved. Everything else in a design (element positions and sizes) is still points.
