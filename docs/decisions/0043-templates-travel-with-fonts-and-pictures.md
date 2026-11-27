# 0043 — Templates travel with the fonts and pictures they use: a ZIP

**Status:** Accepted (2026-11-27).

## Context

The export is one JSON bundle (0035). Since fonts are installed in WordPress and not shipped (0042), a template that uses an installed font
reached another site without it, and a picture a template took from the Media Library kept an address and an ID of the site it came from.
Pictures put into a design in the editor, and the logos and signatures of events, are inline (data addresses) and already travel in the JSON.

## Decision

- **The Export screen has a box** "Include the fonts and pictures the templates use", **ticked when the plugin finds any** (it lists them),
  hidden when there are none and disabled when the server has no `ZipArchive`. Ticked, the file is `events-certificates-DATE.zip`:
  `events-certificates.json` (the bundle of always), `fonts/` and `images/`. Not ticked, it is the JSON as before.
- **What is found** (`Bundle\assets_of()`): the families a template names that are installed and are not the plugin's (every face with a
  readable file), and the pictures of the templates (and of the declaration page) whose address is in this site's uploads. A picture on
  another site is not copied.
- **The JSON lists the files** in an optional key `assets` (`fonts[]` with family, label, generic, weight, file, size and SHA-256;
  `images[]` with the original address, alt, title, file, size and SHA-256). The `$schema` stays `bundle/1`: an old importer ignores the key.
- **The import reads the ZIP without trusting it** (`Bundle\read_zip()`, `clean_assets()`): only the files the JSON lists, with a plain name
  under `fonts/` or `images/`; an extension of a font (`ttf`, `otf`, `woff`, `woff2`) or of a picture (`png`, `jpg`, `jpeg`, `gif`, `webp`),
  the first bytes of that kind, the size and the SHA-256 the JSON says; at most 200 files, 10 MB a font, 5 MB a picture, 100 MB in all, counted
  while unpacking (a ZIP that says less than it holds is stopped). An SVG is never taken (0005). The files wait in a private folder of the
  temporary directory and are deleted with the import.
- **The review has the box** "Install the fonts and pictures of the templates", ticked. A new stage `assets` runs before the templates: fonts
  go to the Font Library the way its Upload tab does (`Fonts\install_face()`: the family post, the file in the fonts folder, the face post;
  a weight the family has is left as it is), pictures to the Media Library (`media_handle_sideload`), **once**: the SHA-256 stays in the
  attachment (`_evcert_asset_sha256`) and a second import uses it. The templates are then written with the pictures' new address and ID
  (`Bundle\rewrite_images()`).
- **Permissions are the ones WordPress asks**: `edit_theme_options` for the Font Library, `upload_files` for the Media Library. Whoever lacks
  one gets the templates and a note that the fonts or pictures were not installed.

## Consequences

- A template moved with its ZIP looks the same on the other site, in the editor, on the page and in the PDF.
- Whoever exports a font shares the file: the screen reminds to check its license.

## Addendum (0.72): the ZIP carries every picture

`assets.images[]` now also lists the pictures of events, certificates and the signature of Settings, each with a `ref`
(`picture:<sha256>`) beside the template pictures' `url`. The import makes one attachment per file and maps each reference to its ID
(ADR 0044). The safety checks of this ADR apply to all of them.
