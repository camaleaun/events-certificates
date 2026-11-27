# 0038 — The palette is a list of swatches; the colors are the template's own roles with a Light and a Dark value

**Status:** Accepted (2026-11-26). Replaces the "one list" part of [0037](0037-palette-with-fixed-entries.md); the rest of 0037 stands.

## Context

0037 made one list that was both the palette and the colors elements use. The owner's mock (Site Editor's Styles > Colors) separates
them: a Palette of colors to choose from, and the places those colors are used.

## Decision

- **`design.colors` stays the template's colors** (Text and Background fixed, the rest the template's own): each has its own `light`
  and optional `dark`, and elements keep pointing at them (`"$text"`). Nothing in rendering changes.
- **`design.palette` is new**: `[{ name, color }]`, one value each. A color's picker offers it as the "Theme" list; picking one copies
  the value into the Light or the Dark of that color. Changing the palette later changes no color.
- **A design without a palette gets one made from its colors** (Base and Contrast, then each color's Light value once; see the addendum), when it is read:
  `defaultPalette()` in `src/editor/model.ts` and `default_palette()` in `includes/render.php` agree, tested with one set of cases
  (`tests/fixtures/palette-cases.json`). Nothing is written back to older designs or bundles; an empty palette stays empty.
- **The sidebar's Colors section** has the dark-mode toggle (shared with the top bar's), the Palette row that opens a collapse (no
  screen to slide in), the Document group (Text, Background) and the Elements group, each color shown as a pair of circles.
- Inspector and SVG pickers choose a color (not a swatch), in the same two groups.

## Consequences

- A color with no Light value is "none"; Clear on a fixed color goes back to its default.
- The Base design has a Brand color and a palette of its own; its Soft text is the old Muted text.

## Addendum (2026-11-26): Base and Contrast by slug, one swatch per color, collapsed sections

- **The theme's first two swatches are Base and Contrast**, stored with the slugs `base` and `contrast` and **no name**: their labels are
  translated when shown (`swatchName()`), so a design made on one site reads in the language of the site that edits it. They can't be
  removed or renamed; their values can change ("Reset colors" puts them back to white and black). A swatch is
  `{ slug, name?, color }`; the person's own swatches are `custom-N` with a name.
- **A derived theme is smaller**: Base, Contrast, then one swatch per color from its **Light** value only (each hex once). Dark values
  are set on the color itself, from its popover, and never enter the theme; the person can still add any swatch of their own, such as
  "Brand" and "Brand dark".
- **Old themes are folded**: with no slugs, the first white becomes Base and the first black Contrast, by value; everything else is the
  person's own, kept (including "(Dark)" swatches saved earlier). `cleanPalette()` (TS) and `clean_palette()` (PHP) agree on the shared cases.
- **The sidebar**: Colors and Preview data are collapses, closed every time the panel is shown; the Theme has no collapse of its own
  (its title, + and a ⋮ menu head the swatches; "Show details" lists their names as text to edit). A color's popover is the block
  editor's: Light and Dark tabs with its color picker, the Theme and Clear. Elements are renamed and removed in their "Show details".

> Superseded in part by [0051](0051-colors-without-fixed-entries.md): picking a swatch for a color now keeps a pointer to it (`$slug`), no longer a copy of its value.
