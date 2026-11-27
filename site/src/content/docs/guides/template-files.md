---
title: "Template files and blocks"
description: "A template is made of blocks: what the file of a template looks like, how to export, import and clear one."
---

A **template** is kept as **blocks**, the way WordPress keeps a page: one block for the certificate, and inside it a block for each rectangle, ellipse, line, shape, picture, text, QR code and verification part. What you see on the canvas is those blocks, and the **Layers** panel is the block editor's own List View.

## Export, import and clear

In the template editor, the **Options** menu (the three dots) has:

- **Export** downloads the template as `certificate.<name>.html`, a file of blocks. The name is the template's title made into a slug: *Basic* is `certificate.basic.html`.
- **Import…** replaces the whole template with a file of blocks, such as one exported here. A file of any other kind is refused, and nothing is saved until you click **Save**.
- **Restore the Basic design** puts the Basic template back.
- **Clear** empties the template: the page, the Background and Text colors and the palette Base and Contrast stay, and nothing else. Nothing in a template is required, not even the name.

Each of these is one step of the history, so **Undo** takes it back.

The **Export** screens of Certificates, Events and the rest also write each template as blocks, inside the file (a JSON, or a ZIP with the fonts and the pictures).

## What the file looks like

```html
<!-- wp:events-certificates/certificate {"page":{"width":"297mm","height":"210mm","backgroundColor":"$background","safe":"10mm"}} -->
	<!-- wp:events-certificates/certificate-rect {"x":0,"y":0,"width":841,"height":40,"fill":"$primary"} /-->
	<!-- wp:events-certificates/certificate-svg {"name":"Seal","x":600,"y":480,"width":120,"height":44,"viewBox":"0 0 120 44","paths":[{"d":"M0 0L10 10Z","fill":"$text"}]} /-->
<!-- /wp:events-certificates/certificate -->
```

- The page has lengths with their unit and no version: its size is in the unit that makes it round (`297mm` by `210mm` for an A4, `11in` by `8.5in` for a letter), and the safe margin and the bleed are in millimeters.
- Positions and sizes (`x`, `y`, `width`, `height`) are points; a drawing's `viewBox` is its own units. An element has no `id` unless the id means something (`title`, a label and its value). Colors are a color or a variable of the template (`$primary`).
- Every shape is a block of this plugin (`certificate-rect`, `certificate-text`, `certificate-svg`…). A drawing (SVG) is one block with its filled paths in the `paths` attribute: no SVG code is kept.
- What a file may not have is left out when it is saved or imported: other kinds of block, attributes a block does not have, pictures that are not in this site's Media Library, paths that are not made of commands and numbers. This is the same cleaning as for any template.

## Where the code lives

The reading, writing and cleaning of these blocks is the [`camaleaun/draw`](https://github.com/camaleaun/draw) library (Composer and TypeScript), and the shapes are also a plugin of their own, *Draw*, that draws them as SVG on any page. The PDF is only this plugin's: the library and the Draw plugin make SVG and nothing else.
