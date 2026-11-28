# 0059 — The file of a template says less: no id on a group, nothing for an empty one

Status: accepted (2026-11-28). Builds on 0050 (the names of a block) and 0051 (colors without fixed entries).

## Context

A file of a template (blocks, `certificate.<name>.html`) kept an `id` on a group when it was a uuid (what an earlier copy or paste made), and an empty template said `"backgroundColor":"var:preset|color|background"`
(pointing at a color it does not have) and `"color":{"palette":[],"elements":[]}`: things that say nothing.

## Decision

- **A group says no id in a file** (`Names::to_attrs()` of camaleaun/draw 0.24.2, TypeScript and PHP): nothing points at a group (what is in it is placed by `ref: "parent"`), and it is made again by position
  (`group-2`, `group-2-rect-1`) when the file is read. An element that means something keeps its id (`signature`, `title`).
- **An empty template is its page**: `{"page":{"width":"297mm","height":"210mm"}}`. With no colors and no theme there is no `color` at all, and a background that points at no color of the design is the default
  one, so it is not written (`colorsToFile()`, `colors_to_file()`). An empty theme is written only when there are colors (a design with no `palette` gets a theme made from its colors, 0051).
- **A file with no colors is read as a design with no colors and no theme** (`colorsFromFile()`, `colors_from_file()`), not as the base's, which an import would otherwise keep.
- **The preview data of a design** (Certificate tab) are saved, exported and imported with it as the `sample` attribute of the certificate block; the default Role of the preview is **Attendee**.
