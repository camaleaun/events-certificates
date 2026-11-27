# 0033 — The tutorial's pictures are blocks inside an SVG: `events-certificates/tutorial-step-image`

**Status:** Accepted (2026-11-25).

## Context

The Generator's step by step showed four screenshots of Meetup (JPG), one set in Portuguese and one in English.
They could not follow the site's language, weighed about 0.9 MB and showed real people's data.

## Decision

- **A drawing, not a photo.** Each step is the Meetup screen rebuilt with core blocks (Group, Paragraph, Heading,
  Image, Icon), run through `do_blocks()` and placed in an inline SVG `<foreignObject>`. The SVG's `viewBox` is the
  window of the old picture (its scale and place were fitted to the first generator's JPGs); the rest of the page
  overflows and is not shown. A dynamic block (`step` 1 to 4), the shortcode, the editor and the admin modal all
  print the same string, filtered by `wp_kses()` once, in `render()`.
- **Isolated from the theme.** WordPress layout classes are renamed to `evcert-tsi-…` after rendering, and the CSS
  they need (the blocks' attributes, `bin/tutorial-css.php`) is in our SCSS under `.evcert-tsi .evcert-tsi__fo`.
  The page's own CSS (`.evcert-app`) does not reach the drawing. The enlarged view is a copy of the SVG in the
  same wrapper, so the picture, the enlarged view, the editor and the admin modal are the same drawing.
- **The red frame is a box of the markup** (`evcert-tsi-frame`), absolute inside what it frames, so it follows the
  size of that thing in any language (a longer word, a wider menu).
- **Icons are filled outlines**, registered once with the Icon API (WordPress 7.1, hence `Requires at least: 7.1`):
  the API keeps only the paths, so strokes were converted to shapes. Every `svg` has its `xmlns`.
- **Sample data in two layers** (`includes/tutorial-step-image/sample.php`). Numbers, the pictures (a number: the
  file name without folder and extension), times (UTC, `20261027T2200`) and the time zone are in the code, two sets:
  the default (WordPress NYC, the largest WordPress meetup) and Brazilian Portuguese's (WordPress Curitiba, the
  community of the table where the Generator was built at WordCamp Brasil 2026). The texts (a name, a place, a
  description) go through `_x()`, but only Brazilian Portuguese is translated: `in_sample()` reads them in English
  for every other language, so a translation of "New York" into another city changes nothing. Meetup's own words
  and formats (`labels()`) are translated in every language. The form's examples (`E.g.: …`) use the same sample.
- **Words the original pictures show in English.** The pictures of steps 3 and 4 were taken with Meetup in
  English by mistake, and the drawings keep them so. Those words (the Attendees screen and its menu) have the
  context `Meetup (EN)`: Brazilian Portuguese does not translate them, every other language does.
- **The page can be made again in the site's language** (`QuickGenerator\recreate_page()`: a link on the plugin's
  row and `wp evcert quick-page recreate`), since the page is written once (ADR 0017).

## Consequences

- The package lost the JPGs and gained 52 small files (webp, svg), about 0.7 MB.
- A browser that does not draw `<foreignObject>` as Chromium does (Safari/WebKit) needs a check; the drawings avoid
  `mask`, `filter` and `opacity` for that, and Chromium is the one measured (0.02% to 0.07% of different pixels
  against Meetup, outside the pictures and a shadow).
- The pictures in the sample (events, people, Meetup's banner) must be free to use before the directory
  submission; they are tracked in the backlog.
- `make-pot` does not extract a `_nx()` whose number is an expression: use a variable.

## Addendum (2026-11-27)

The link on the plugin's row and `wp evcert quick-page recreate` now move the old generator page to the Trash instead of deleting it, and the same action is on the Tools tab of Settings (see [0040](0040-tools-tab-and-wp-cli.md)).
