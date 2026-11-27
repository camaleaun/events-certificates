# 0040 — A Tools tab in Settings, and WP-CLI, to make again what the plugin makes once

**Status:** Accepted (2026-11-27).

## Context

The plugin makes things once and lazily, with no activation hook: the generator page, the check page, the "Get my certificate" page and
the two starter templates (0039). When one is deleted or made in another language, the only way back was a link on the plugin's row for
the generator page, which deleted it for good with what was edited in it.

## Decision

- **A Tools tab** in Settings (the last one) and **`wp evcert tools list|run <id>`** do the same actions. Each action is one function in
  `includes/tools.php` (`Tools\run()`); the tab (an `admin-post` form with a nonce for each action), WP-CLI (`includes/cli.php`) and the
  link on the plugin's row all call it. The tab has no settings to save, so it is printed apart from the options' form.
- **The actions**: `quick-page`, `verify-page`, `mine-page` (the pages), `templates` (Basic and Meetup), `tables` (dbDelta), `addresses`
  (rewrite rules) and `cache` (share images, the PDFs' fonts, the language names).
- **Recreating never deletes what a person made.** A page that is there goes to the Trash (WordPress frees its address) and a new one is
  made; the check and "Get my certificate" pages are only made when missing unless asked (`--trash-existing`, a checkbox). A template
  that is there stays as it is: only the missing ones are made, and the default and the Quick generator's template change only when
  asked (`--make-default`, a checkbox). The generator page's old recreate now sends the old page to the Trash too.
- **The documents' pepper is never touched**: the hashes of the documents depend on it.
- WP-CLI runs as an administrator (`--user=<login>`); on a network, once for each site (`--url=`). `all` runs the actions that do not
  touch pages. An action that moves a page to the Trash asks first (`--yes` skips it).

## Left out

- Abilities for the actions, a network screen, and resetting the Site Editor's changes to the generator's block template.
- With CampTix or GatherPress the settings are in their own tab, so the Tools tab is not there: WP-CLI is the way.

## Consequences

- Uninstall also trashes the "Get my certificate" page and deletes its option, which it left behind.
