---
title: "Group elements"
description: "Hold elements in a box that places them in a row, a stack or a grid, in the template editor."
---

A **group** is a box that holds other elements and decides where they go, like WordPress's Group block with its Row, Stack and Grid variations. It is useful when a few things belong together (a logo and a name, three columns of signatures): move or resize the group and what is inside follows.

## Make one

Select two or more elements (click, then **Shift**+click for a range or **Cmd**/**Ctrl**+click to add one; or drag a rectangle on the empty page) and choose a layout in the bar that appears over them, or press **Cmd+G**. **Shift+Cmd+G** ungroups. The group is as big as the elements are together and they stay where they were.

The layouts, in WordPress's order:

| Layout | What it does |
|---|---|
| **Group** | Holds the elements where you put them. |
| **Row** | Places them one after the other, side by side. |
| **Stack** | Places them one after the other, top to bottom. |
| **Grid** | Places them in columns of one width. |

## Layout settings

The **Layout** panel has the type, the **gap**, the **padding**, how the elements are **justified** along the line (start, center, end, between) and **aligned** across it, the number of **columns** of a grid and **Clip content** (off by default: what goes out of the box is shown). An element set to *grow* shares the room that is left.

What a row, stack or grid places cannot be dragged or resized by hand: change the settings.

## Rules

- What is in a group is placed from the group's top-left corner, and percentages are of the group's box, not the page's.
- Up to **3 groups deep** and **300 elements** in all.
- The authenticity code and the verification part belong to the certificate and cannot be in a group. A signature may be.
- In the **Layers** panel a group is a node with its children; drag an element into or out of it and it keeps its place on the page.

The file of a group is the block `certificate-group`: see [Template files and blocks](../template-files/).
