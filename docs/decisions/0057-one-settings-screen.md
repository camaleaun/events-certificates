# 0057 — One settings screen, wherever the plugin is

Status: accepted (0.72). Amends 0026 (classic screens like core) and 0040 (the Tools tab).

## Context

With CampTix or GatherPress alone the settings were a tab of the other plugin's own settings (Tickets > Setup > Certificates, GatherPress > Settings > Certificates), saved by its form. Those tabs
were left as they were when the settings screen of the plugin grew (its tabs Features, Issuing, Verification, Declaration, Data and Tools, with its own form): the integrations still had one long list of
fields, with no Tools tab (export, import, restore) and none of the later work.

## Decision

- **The settings screen is the plugin's own wherever it is.** Standalone it is `admin.php?page=evcert-options` (Certificates > Settings); with GatherPress alone it is `edit.php?post_type=gatherpress_event&page=certificates`
  and with CampTix alone `edit.php?post_type=tix_ticket&page=certificates`, a *Certificate settings* submenu next to *Certificates* (the short slug is the same in both menus). It is saved by the plugin's own form
  (group `evcert`), not by the form of the other plugin.
- `Admin\settings_slug()` (filter `evcert_settings_slug`) and `Admin\settings_url()` say where it is, and every link to the settings (the editor, the generators, the Tools forms) uses them.
- The old tabs are gone, and their addresses (`gatherpress_certificates`, `camptix_options&tix_section=certificates`) redirect to the new screen.
- Settings saved so far are the same option (`evcert_settings`): nothing is migrated.

## Consequences

- A change to the settings screen is made once and shows up in the three places.
- The other plugin's settings no longer list ours: an organizer looks for them in the Events or Tickets menu, next to Certificates.
