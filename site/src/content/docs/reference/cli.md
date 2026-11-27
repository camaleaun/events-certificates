---
title: "Command line"
description: "WP-CLI commands to make again what the plugin makes once."
---

The commands run as an administrator: add `--user=<login>`. On a network, run them once for each site (`--url=<site>`).

| Command | What it does |
|---|---|
| `wp evcert tools list` | The actions and the state each is in. `--format=json` for scripts. |
| `wp evcert tools run <id>` | Runs one action. |
| `wp evcert tools run all` | Runs the actions that do not touch pages (`templates`, `tables`, `addresses`, `cache`). |
| `wp evcert quick-page recreate` | The same as `tools run quick-page`. |

## The actions

| ID | What it does |
|---|---|
| `quick-page` | Moves the Quick generator's page to the Trash and makes it again, in the language of the site. |
| `verify-page` | Makes the check page when it is missing. `--trash-existing` moves the one that is there to the Trash and makes a new one. |
| `mine-page` | The same for the "Get my certificate" page. |
| `templates` | Makes Basic and Meetup when they are missing and leaves the ones you have as they are. `--make-default` also makes Basic the default template and Meetup the Quick generator's. |
| `tables` | Creates or updates the tables. No data is lost. |
| `addresses` | Makes WordPress learn the certificates' addresses again. |
| `cache` | Empties the share images, the PDFs' fonts and the language names. They are made again when needed. |

An action that moves a page to the Trash asks first; `--yes` skips the question. The documents' pepper is never changed.

```
wp --user=admin evcert tools list
wp --user=admin evcert tools run templates --make-default
wp --user=admin evcert tools run quick-page --yes
```
