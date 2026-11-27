---
title: "Hooks"
description: "Filters and actions to change what the plugin does."
---

The plugin's hooks are prefixed with `evcert_`. The ones you are most likely to use:

| Hook | Kind | What it does |
|---|---|---|
| `evcert_verification_enabled` | filter | Turns verification on or off in code. |
| `evcert_certificates_title_actions` | action | Add a link next to the title of the Certificates screen. |
| `evcert_events_title_actions` | action | The same for the Events screen. |
| `evcert_templates_title_actions` | action | The same for the Templates screen. |
| `evcert_settings_group` | filter | The settings group of the settings form (to host the settings in another screen). |
| `evcert_signature_chooser` | filter | `media` (default) or `react`: which dialog chooses the signature. |
| `evcert_bundle_max_bytes` | filter | The largest file the import reads (default: 20 MB or the upload limit). |

The option `evcert_signature_chooser` does the same as its filter and is not shown in Settings: it is a switch for development.
