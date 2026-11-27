# 0048 — The signature is an element of its own, and an SVG's size is in its Size row

Status: accepted (0.72). Changes ADR 0035's way of marking the signature (an SVG with the signature slot) and the sidebar of ADR 0005.

## Context

The signer's signature was an SVG element with `slot: 'signature'`, set by a toggle "Signer's signature" in the sidebar of any SVG. It had a
drawing of its own (a sample), the sidebar showed its viewBox and paths although the certificate draws the signer's, and an SVG had its width
and height in the Dimensions panel, apart from the Size row its dialog now has.

## Decision

- The signature is **an element of its own**, as the community's logo is: the type `signature`, a block `events-certificates/certificate-signature`,
  an entry "Signature" in the inserter (it may be put more than once: the first has the id `signature`). It is a **place**: a position, a size and
  the color of the ink, with **no drawing of its own** (the cleaning keeps only its `color`). The editor shows the signer's signature in it
  (a dashed place when there is none) and certificates draw the signer's signature, upright and as large as it fits, and nothing when there is none:
  the declaration page no longer falls back to a drawing of the template (`Render\signature_src()`).
- The SVG's sidebar has **Size right after ViewBox**: the element's own width and height in points, the row of the SVG dialog. Typing a size changes
  the element at once and makes its layout again (`src/editor/size.ts`, `SizeRow.tsx`). A side that something else decides is shown and not typed:
  the one that follows when the ratio is locked, and an axis that fills. The signature's sidebar has the Size row, the Signature color and the same
  Dimensions panel.
- The **Dimensions** panel of an SVG or a signature no longer has the width and height fields of its anchors (the Left/Top, or Right/Bottom, remain,
  and the two margins when an axis fills). Fill and the ratio lock stay: they decide the anchors.
- The package does not know the signature: the certificate gives the type to the package's cleaning as a parameter (`types` in `includes/design-clean.php`, `camaleaun/draw` 0.9.0) and its block in the `blocks` option of the design (ADR 0047).
- The toggle is gone. The shipped templates (`templates/base-design.json`, `basic-design.json`) have the type `signature` for their signature.
- The dialog of an SVG now gets the element as it is shown (resolved), so its Size row is not that of a stale layout.
- No compatibility: an SVG that only has the id or slot `signature` is a drawing; the templates of the development site were rewritten.

## Alternatives

- An SVG with a `slot` created from the inserter (no change in the package and the blocks): less to touch, but the layers would still call it an
  SVG and its drawing would still be one of the template. Rejected: the signature is not a drawing.
- An `image` with a slot, like the logo: an image keeps no color, and the signer's signature is a drawing in the ink of the template.
