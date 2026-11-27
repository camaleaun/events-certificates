---
title: "Abilities"
description: "The WordPress Abilities API actions the plugin registers."
---

The plugin registers **abilities** (WordPress's Abilities API), so tools and agents can work with events and certificates. They need the management capability.

| Ability | What it does |
|---|---|
| `events-certificates/get-events` | Lists events. |
| `events-certificates/create-event`, `update-event` | Add or change an event. |
| `events-certificates/get-participants` | Lists an event's participants (not the Trash). |
| `events-certificates/add-participants` | Adds participants. |
| `events-certificates/set-participant-status` | Marks attended, no-show or registered. |
| `events-certificates/remove-participants` | Moves participants to the event's Trash. |
| `events-certificates/issue-certificates` | Issues the certificates of who attended. |
| `events-certificates/get-certificates` | Lists certificates (not the Trash). |
| `events-certificates/get-settings`, `update-settings` | Reads and changes settings. |

Run `wp-json/wp-abilities/v1` on your site for the exact list and schemas.
