# 0049 — The zoom of the canvas has three fits, and the Settings aside starts closed

Status: accepted (0.72). Complements ADR 0046 (which only mentions the zoom).

## Context

The editor opened with one fit, "Zoom to fit", that showed the whole page with about 64px at the sides and 100px above and below (the JS
subtracted 72px more for a toolbar that no longer decides anything), and it fitted again only when the window was resized: opening or closing the
Settings aside or the left panel did not. The aside started open, and a certificate is wider than tall, so most of the canvas was the aside.

## Decision

- The zoom is a **mode**: a level (50%, 100%, 200%, what ± and the wheel set) or one of three **fits** of the page in the visible area:
  **Fit horizontal** (the width of the page; a taller page scrolls vertically), **Fit vertical** (its height) and **Fit view** (the whole page).
  The editor opens in **Fit view** (the whole page, with its room around), and ⌘/Ctrl+0 goes back to it; **Fit horizontal** and **Fit vertical** are
  chosen in the menu (the page seen from its top).
- The footer shows the real percentage in a fit too, and the menu marks the mode (`menuitemradio`). The palette has the three fits.
- A fit **follows the area**: the scale is computed from the stage's size (a `ResizeObserver`), so the window, and the Settings aside and the left
  panel opening and closing, fit the page again by themselves. A level stays what it is. `fitScale()` (`src/editor/zoom.ts`) is pure and tested;
  the bleed is part of the page it fits.
- **Padding**: only **Fit view** (and a zoom level) leaves room around the page: 24px (12px on small screens), the padding of `.evcert-stage` in
  `style.scss`, read by the editor, one place. **Fit horizontal** and **Fit vertical** use all the area, with no room and no shadow around the page
  (`.is-fit-full`). The line of the page's edge (`.evcert-trim`) is drawn **per side**: not the sides that lie over a border of the stage (the top and
  bottom ones in Fit vertical, the left and right ones in Fit horizontal), which it would double; a side with a gap keeps its line and the page its
  shadow (the shadow goes only when the page fills the area both ways, `.is-fit-flush`). With a bleed the line is the cut line: it is drawn whole.
- The dashed safe margin is drawn only when the page has one (a safe margin of 0 shows nothing).
- The stage is not seen (`.is-waiting`: invisible but laid out) until the design is loaded and the area measured, so the page is drawn once,
  already fitted, and the Base design that the editor holds meanwhile is never shown.
- Entering a fit shows the page from its top, centred; when the area changes inside a fit the same part of the page stays in view.
- The **Settings aside starts closed** and is not remembered; selecting an element still opens it (on its Element tab), so a fit refits then.
