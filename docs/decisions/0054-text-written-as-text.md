# 0054 — The text of an element, written as text

Status: accepted (0.72). Amends 0004 ("text is runs, never strings with braces"), builds on 0050 (what a file says).

## Context

A text element was kept in a file as its runs: `"runs":[{"text":"…"},{"field":"event"},{"field":"location","bold":true},…]`. It was exact and hard to read, and
copying the text in the editor lost the fields (a chip has no text of its own). The owner asked for something like markdown, with the fields in square brackets, and for a
pasted text of that kind to come out right.

## Decision

- **The model stays runs** (`{ text, b, i }`, `{ field, format, b, i }`): the editor, the cleaning, the page, the PDF and the quick generator do not change, and a field is still
  data in memory (a chip, with its format). The text is only how a **file** and the **clipboard** say them.
- **The grammar** (`runs.ts` and `Runs.php` of camaleaun/draw, the same and tested with the same fixtures): `[key]` and `[key:format]` are fields; `*…*` is bold and `_…_` italic
  (not inside a word, so `snake_case` is text; a run of asterisks is one switch, so the `**…**` of before is bold); a switch with no partner is text (`5 * 3`); a line break is a
  line break; a backslash before one of `\ * _ [ ] {` makes it text. `{key}` (what files wrote before) is read as a field.
- **Only a known key is a field**: the keys are `templates/fields.json`, read by the server (`Designs\field_keys()`) and by the editor (`src/editor/fieldKeys.ts`), and passed to the
  package as the `fields` option. Any other `[word]` is text, in a file and on a paste.
- **In a file**: `"text":"held in *[location]*"` (a block's attribute, where `runs` was), and each language of `variants` the same, a string. A file of before, with `runs` (and `bold` and
  `italic`, in a variant too) or with a `text` of the old braces, is read the same, and is written as text the next time it is saved; an older plugin does not read a new file.
- **The clipboard**: a copy or a cut in the text editor puts the selection as that text (plain text only); a paste reads text with the grammar (fields as chips, marks as marks), and
  rich text from another page keeps its marks, its line breaks and any `[field]` in it. A block (`div`, `p`) that Enter or a paste makes is a line break.

## Consequences

- A copy escapes what would be read as a mark or a field (`5 \* 3`); the editor reads it back as it was.
- A mark in the middle of a word cannot be said with `_` (`a_b_c` is text): bold can.
- The shipped templates (`templates/*-design.json`) stay in the model's form: they are not files.
