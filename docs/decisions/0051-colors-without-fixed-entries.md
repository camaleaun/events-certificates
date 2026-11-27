# 0051 — Colors without fixed entries

Status: accepted (0.72). Replaces the "fixed entries" of ADR 0037 (and the addendum of 0038 that kept them).

## Context

Base and Contrast (the Theme) and Background and Text (the Elements) were always there: they could not be removed, their names could not be
edited, and the editor and the server added them again when they were missing. A person who wants a template with no theme, or with other names, had
no way to say so. And the Colors section had a "Reset colors" to undo what could not be broken.

## Decision

- **Nothing is fixed.** A theme may have any number of swatches, none included, and the Elements any number of colors. Each one is renamed and removed
  like any other. A swatch with no stored name still shows Base or Contrast in the site's language.
- **The slug follows the name**, as in the Styles: it is made from the name a color has (lowercase, no accents, dashes: "Primária" is `primaria`), when it is
  added (+ makes "Color 1", `color-1`) and every time the name is changed, keeping its place. What used the old slug (the elements, both sheets, and
  the page's background) is pointed at the new one. A name with nothing to make a slug of keeps the slug it had. Defaults a template ships
  (`background`, `text`, `base`, `contrast`) are slugs of the names they ship with, so they change when those are renamed.
- **Nothing is added again.** `normalizeColors()` (editor) and `with_palette()` / `clean_palette()` (server) no longer create, reorder or force a
  color. A stored `"palette":[]` stays empty (the theme made from the colors is only for a design with no `palette` at all).
- **The code's own colors are the certificate's, not the package's.** When a design lacks the color an element refers to, the plugin reads a built-in
  one (`builtinColor()` in `src/editor/model.ts`, `builtin_color()` in `includes/render.php`, the same): `$text`, `$muted` and `$line` are black
  (white in dark), `$background` transparent (the page row says "Transparent"; the paper is white, black in dark), `$primary` `#3858e9`; any other name is none. `camaleaun/draw` has no default colors: the Draw
  plugin uses the site theme's. The only change in the package (0.11.0) is that the cleaning accepts a color with no Light value.
- **One name for a color: a slug is never in both the Theme and the Elements.** A reference (`$background`, `$base`) therefore says which
  color it is, and it reads a swatch (one value) or an element color (its Light and Dark values). A slug that the other list has gets a number
  (`color-1-2`); a swatch that has one on an import gets another (`custom-N`). In the file a reference is written as a block says a preset:
  `"backgroundColor":"var:preset|color|background"` (`var:preset|color|element|background`, the first form, is read too), never `$background`.
- **The page's color is its own choice**, in the Certificate panel (the "Background" row), as it is not an element: its popover is the one of a color
  of the Colors section (the preview with its name, the groups at the base, Clear) with the Theme and the Elements as its two groups. It is the
  Background by default; with none, or one with no value, the paper is white (black in dark): `pageColor()` / `page_color()`. A color that is
  removed leaves its Light value on the page that used it.
- **Clear on a Light value means none**, not "back to the default". The Dark one still means "use the Light one".
- **No "Reset colors".** Removing a color puts its Light value in what used it, so nothing changes on paper. The **+** of each group adds "Color N" (Color 1, Color 2…): a swatch of the Theme in black (`#000000`), a color of the Elements with no value yet (an empty circle with a slash) and opens the group's details (the name field and Done), as the Styles do.
- **The page is written in the unit that makes it round**: an A4 is `297mm` by `210mm`, a letter `11in` by `8.5in`; an A4 in whole points (842 by 595) is
  read as the A4 (`snapPaper()` / `snap_paper()`), never kept as `842pt`.
- **A color of the Elements is written as CSS says it.** In `color.elements` an entry is `{"slug":"fundo","color":"light-dark(var:preset|color|branco,var:preset|color|preto)","name":"Fundo"}`
  when it has a Dark value, else `"color":"var:preset|color|branco"` (or a literal). A color picked from the Theme is kept as a pointer to it (`$branco` in the model,
  `var:preset|color|branco` in the file), so the Theme's color changes what points at it; a swatch that is removed leaves its color in what pointed at it, and a swatch
  that is renamed is followed. `camaleaun/draw` 0.12.0 lets the Light and Dark value of a color point at another color.
- **A name that is the default of its type is not written** (`"name":"SVG"` is the SVG's): `defaultName()` / `default_name()`.
- **A new template starts filled** (Base design, Basic, Meetup): Base/Contrast and Background/Text. **Clear** and the **Empty** template start with no
  colors and no theme.
- **One list of colors for an element.** The pickers of an element and of a fill show all the colors under "Elements", in the order they are kept (no
  "Document" group).
- **The palette color picker** is padded like the Styles' one (260 px wide, 16 px around, "Clear" only when there is a value); the Light/Dark one is
  unchanged.

## Consequences

- A design file may have no `colors` and no `palette`; a design written before this reads the same (its colors are all there).
- `tests/fixtures/palette-cases.json` is shared by the editor and the server and now says what is *not* done (no Text added, an empty palette stays empty).

## Addendum (0052)

A new rectangle starts with a `#000000` fill and no stroke; "empty" is no value. `currentColor` (the Text color) and `#00000000` (nothing painted) are understood but are not defaults.
