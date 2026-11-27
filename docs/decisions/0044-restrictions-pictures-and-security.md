# 0044 — Restrictions for networks, pictures as Media Library attachments, and a strict entry

**Status:** Accepted (2026-11-27). This file grows with the work: the restrictions come first.

## Context

On a network (WordPress.org's multisite, a WordCamp network) the data that goes in and out of a site is a security question: a ZIP with fonts and
pictures, SVG drawings. The plugin must let the network's administrator switch that off, without a screen in the settings.

## Decision: restrictions by flag

- `includes/restrictions.php`: the features `export`, `import`, `zip`, `fonts`, `pictures` and `vectors`, switched by the option
  `evcert_restrictions`, the constant `EVCERT_RESTRICTIONS` and the filters `evcert_restrictions` and `evcert_allow_{feature}` (the filter has
  the last word). Fonts and pictures travel in a ZIP, so they need `zip` too. On a single site everything is allowed; on a network `zip`,
  `fonts`, `pictures` and `vectors` are for super administrators by default.
- **Export** with a feature off is a JSON without that kind of data: the elements of a template keep their place, size and name but have no
  paths (`svg`) and no `src` (`image`), an event has no drawn logo or signature. **Import** leaves it out, says so in the log, refuses a ZIP when
  `zip` is off, and never clears the site's own signature because the file's was left out.
- The editor draws any drawing or picture with nothing in it as a dashed place with its name, so a template that came in without them can be
  filled by hand.
- The checks are on the server: the buttons only hide.

## Decision: a strict entry

Before, a design's JSON was kept as sent (the defence was escaping and `kses` when it was drawn) and only the paths of a drawing were
cleaned. Now what is **saved** (the editor's REST save) and what is **imported** is checked against what a design may have:

- **`Designs\clean()`** (`includes/design-clean.php`): only the nine element types the editor makes, only the keys of each, numbers in ranges
  (a number that is not one is dropped), colors that are a hex, `rgb()`, `hsl()` or a variable of the theme, texts with no markup (the spaces
  and line breaks stay: they are part of a design), a font that is only a family name, a picture whose address is a file of **this site's
  Media Library** (`javascript:`, `data:`, another site, a script file: all dropped), drawings through the cleaning of paths, at most 300
  elements and 2 MB, and every unknown key removed. A design that is not one (no page, no elements) is refused. The shipped designs and the
  ones in the dev site, and the snapshots, come out **identical**, which a test keeps true.
- **Paths of a drawing** (`Render\is_path_data()`): read command by command, with exactly the numbers each takes, the flags of an arc as a
  0 or a 1, numbers that are finite and at most 100,000, 5,000 segments; nothing else (no letter that is not a command, no markup, no
  address). A drawing of a design may have up to 100,000 characters in a path; the pictures of an event and the signature, 20,000.
- **Pictures that come in a package** are checked by their first bytes, `wp_getimagesize()` (a side up to 8,000 px and 40 megapixels) and are
  **written again** by the image library of WordPress (PNG or JPEG) before they enter the Media Library, so what was hidden in the file after
  the picture does not come through; never an SVG; for that one upload only PNG and JPEG are accepted. Without GD or Imagick the picture is
  not imported.
- **Fonts** are checked in the way they are built (`Fonts\is_well_formed()`: the table directory of a TrueType or OpenType font, the sizes of
  a WOFF, the header of a WOFF2) before they are installed.
- **ZIP**: besides the plain names and the SHA-256, an entry that is encrypted, a link, named twice, a name that looks like code (`.php.png`,
  `.phar`…) or that unpacks to more than 100 times its weight is left out, and each one is said in the log.
- **JSON** of a package is read to a depth of 40.

## Decision: pictures are attachments of the Media Library

A logo or a signature that is a raster picture was kept as a data URI (`data:image/png;base64,…`, up to 1.4 MB) in the event's meta, in an
option of Settings, in every certificate issued, and again in the JSON of an export. Now:

- **What is kept is the ID of an attachment** (`Pictures\clean()`: PNG, JPEG, GIF or WebP, never an SVG). The event's meta `_evcert_logo` and
  `_evcert_signature_image`, the option `evcert_signature_attachment` and the `logo` and `signature_image` of a certificate's frozen data all hold
  an ID. The option `evcert_signature_image` is gone.
- **What is drawn is the address** (`Pictures\src()`: the large size of the attachment, a file the PDF reads from the uploads folder). A
  deleted attachment shows nothing, with no error. A data URI is only accepted at run time (`Pictures\clean_runtime()`), as the Generator
  screen sends a picture it draws.
- **The choosers** (the Generator, the event form, Settings) take a picture from the Media Library or upload one to it and post the ID. An
  SVG file is never an attachment: a drawing is a `{vw, vh, paths}` structure (ADR 0005) as before.
- **A package** (ADR 0043) carries each picture of events, certificates and the signature as a file in `images/` of the ZIP and a
  `picture:<sha256>` in the JSON, never a data URI. The import makes the attachment (the same file twice is one attachment, found by its
  hash), and each place points at it. A plain JSON, or a site where `pictures` or `zip` is restricted, has those places empty; a data URI or
  an ID in an older file is left out, never kept.
- **Data that already exists** is moved by `bin/move-pictures-to-media.php` (not shipped): without arguments it says what it would do; with
  `yes` it makes one attachment for each different picture; `json old.json new.zip` turns an older export into a ZIP. The templates'
  snapshots (kept by their hash) are only counted.

A consequence the forms say: deleting or replacing the attachment in the Media Library changes the certificates that use it.
