# 0041 — Certificates are light; dark is a choice

**Status:** Accepted (2026-11-27).

## Context

Every certificate existed in two versions, light and dark: the check page drew both and had a button for the viewer, the editor had a dark
preview and a Dark tab in every color, and a design kept a `light` and a `dark` value for each color. The PDF was always light (nothing
reads a color scheme in a PDF), so a certificate that looked one way on the page looked another on paper, and each template had twice the
colors to care about.

## Decision

- **Settings → Issuing → Certificate colors**: **Light only** (the default) or **Light and dark** (`certificate_modes`, `Settings\dark_enabled()`).
  The help text says the PDF is always light.
- **Light only**: the check page (and the preview in a new tab) draws no dark certificate and has no light/dark button; its body gets
  `evcert-verify--light-only`, so the styles keep the light certificate whatever the system or a saved choice says (without the class a
  dark system would hide the only certificate there is). The editor (`window.evcertEditor.darkMode`) hides the dark preview, its command
  and the Dark tab of the colors, which show one value.
- **Nothing is deleted.** The dark values stay in each design and in the palette, and come back when the site chooses "Light and dark".
- The rest of the check page (the dock, the texts) keeps following the system as before; so does the Quick generator's own app.

## The Basic design's background: the SVG, and a PNG kept beside it

The Basic design's background is one SVG element of filled shapes in the template's Lines color (0039): it follows the colors of the
template, so it needs nothing from the Media Library. `templates/basic-background.png` is the same drawing in grey and transparency only
(2246 x 1588, 192 dpi), for anyone who wants it as a picture (a template of their own, a print): `bin/make-basic-background.py` writes the
SVG and the PNG together. The PNG is compressed by the script itself (an indexed picture, one grey with 16 steps of transparency, about
50 KB); the plugin does not depend on an image tool. Both files ship with the plugin; the plugin does not make anything in the Media Library.

## Consequences

- The PNG shows on a light page and on a dark one; its contrast with the text is tested for both.
