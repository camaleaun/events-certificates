# 0042 — Fonts are installed in WordPress; the plugin ships only the two small ones

**Status:** Accepted (2026-11-27).

## Context

The plugin shipped six TrueType files of Inter and Lora (about 900 KB of the zip, 1.4 MB in git) in a fixed table, `Render\FONTS`, read in
six places: the PDF, the pages, the editor's canvas, the Quick generator, the share image and the declaration page. No template it ships
uses them (Basic and Meetup WordPress use the two small fonts of the Quick generator); they served the declaration page, the editor's
default, the PDF's fallback and the font picker. WordPress has its own place for fonts, the Font Library.

## Decision

- **The plugin ships two fonts**: IBM Plex Sans (`CertSans`, 400 and 600) and Instrument Serif (`CertSerif`, 400), subsets of about 19 KB
  each, which the Meetup WordPress template of the Quick generator uses. Nothing else is in `fonts/` but their licenses.
- **Every other font is installed in WordPress**, in the Font Library (`wp_font_family` and `wp_font_face`), and `includes/fonts.php`
  (`Fonts\faces()`, `installed()`, `choices()`) reads it for all six places. The result is remembered in a transient and forgotten when a
  font is saved or deleted.
- **A font a design names that is neither shipped nor installed** is drawn with one that stands in for it (`Fonts\resolve()`): the serif for
  a serif family (and for Lora, which the first templates asked for), the sans otherwise. The page lists both (`'Inter','CertSans'`), so a
  template never breaks; the editor says "not installed".
- **The PDF reads TrueType, OpenType and WOFF, not WOFF2**, which is what the Font Library installs from Google Fonts, and the PHP of a
  site has no Brotli to convert it. Such a family is listed in the editor (with "the PDF uses IBM Plex Sans") and shown on the page, and
  the PDF uses its stand-in; whoever wants the PDF to match uploads a TrueType, OpenType or WOFF file. A variable font (one file for a
  range of weights) is registered for the weights a design uses inside the range; the PDF shows the file's default weight.
- **A weight the font lacks is not asked for** (`Fonts\weight_for()`, and the same in the editor): the one CSS would pick (500 between 400
  and 600 is the 400), because the PDF engine answers a missing weight with another font.
- **The declaration page** is drawn in `CertSans` and `CertSerif`, and a new text of the editor starts in `CertSans`.
- **Third-party code and its big files** (jsPDF, JSZip, SheetJS, dompdf) were already out of git: `assets/third-party/` and
  `includes/third-party/` are ignored and made by the build from the pinned versions (0032); they enter the zip only.

## Consequences

- The zip is about 0.9 MB smaller and git about 1.4 MB.
- A template that used Inter or Lora looks the same once the site installs them, and in the shipped fonts until then.
- Installing a font in WordPress is enough for the editor, the page, the generator (TrueType) and, as above, the PDF.

## Update (2026-11-27)

Export and Import can take the fonts a template uses along, in a ZIP (0043); the Import installs them in the Font Library
(`Fonts\\install_face()`), where the Fonts screen of WordPress lists them like the ones uploaded there.
