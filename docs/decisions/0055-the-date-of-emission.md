# 0055 — The date of emission: a text whose words are a date format

Status: accepted (0.72). Builds on 0047 (the elements are blocks of this plugin), 0048 (an element of its own), 0050 (what a file says) and 0054 (the text of an element).

## Context

The date a certificate is issued is a field (`issued_date`) in a text, with a Format select (site, long, medium…). The owner wanted an element of its own for it: its block is
`events-certificates/certificate-date-of-emission`, it shows the date in the **system's date format** (Settings → General) and, when edited, takes **a format written as if it were
a text** (`d/m/Y`, `j \d\e F \d\e Y`), as the Custom field of Settings → General does.

## Decision

- **It is a text with the slot `date-of-emission`.** No new element type: the cleaning, the layers, the toolbar and the quick generator already know a text. `camaleaun/draw` 0.24
  writes `type:slot` as a block of its own (`text:date-of-emission` → `certificate-date-of-emission`), with no `slot` and no default name (`Date of emission`) in the file, and reads it
  back as a text with that slot.
- **Its words are the format.** Empty, it is the date in the format of the site; with words, each of them is the format of the date (the letters of PHP's `date()`, a backslash before a
  letter that is a word). Bold, italic and a text by language work as in any text (the sidebar panel that edited the text by language left on 2026-11-28; the texts already written are kept and printed, and a language switcher at the top of the editor will edit them). An empty one writes no `text` at all (`<!-- wp:events-certificates/certificate-date-of-emission {"x":…} /-->`):
  what it shows when empty is derived from the slot every time, not kept by the editor, so emptying it again brings the site's format back (what a text made only of fields could not do
  after being closed and opened).
- **Drawing it** is converted when it is drawn and never saved (`date_of_emission_runs()` in `includes/render.php`, `datedRuns()` in `src/editor/model.ts`): no words are
  `[ issued_date ]`; each word is the field `issued_date` with the words as its format. A format that is not one of the named ones (site, long, medium, short, month, iso) is a date pattern
  for every date field (`format_date()`, `fieldValue()`, `dateText()`); the pattern is read with `phpDate()` (`src/editor/dates.ts`) in the editor and the quick generators.
- **The date** is the one of the plugin's *date of issue* setting (when it was issued or the event's end), as `issued_date` always was; the editor's sample gives it in the site's time
  zone and the site's date format (`date_local`, `date_format`).
- **The editor**: the inserter makes it (calendar icon); on the canvas and in a language the empty box shows the site's format as its placeholder; the Inspector has a *Date format*
  field with the date as it is shown and a link to the documentation of the letters, and no Format select or *Insert a field* menu. Duplicate keeps it a date of emission (the slot says
  what an element is, not where: `KIND_SLOTS` in `tree.ts`), and there may be more than one.

## Consequences

- A `[[issued_date:Y]]` written by hand in a file is a pattern now (it was the long format, an unknown name falling back to it).
- A letter in the editor's preview comes from `phpDate()` (English ordinals, the language's names from `Intl`), so a rare letter may differ from the PDF's.
- A plugin before 0.72 (and a `camaleaun/draw` before 0.24) does not know the block.
- The shipped templates keep their `issued-on` text ("Issued on …", a translated sentence); the element is for a template that wants the bare date.
