# 0057 — One settings screen, wherever the plugin is

Status: accepted (0.72); the look of the plugin's own screen amended 2026-11-28 (below). Amends 0026 (classic screens like core), 0040 (the Tools tab) and 0044 (restrictions).

## Context

With CampTix or GatherPress alone the settings were a tab of the other plugin's own settings (Tickets > Setup > Certificates, GatherPress > Settings > Certificates), saved by its form and built from the plugin's
fields one after the other. Those tabs were left as they were when the settings screen of the plugin grew (its tabs Features, Issuing, Verification, Declaration, Data and Tools, its Export and Import, its own form).

## Decision

- **The settings are the plugin's own screen wherever it is, and with CampTix or GatherPress it is still one tab, Certificates, in their settings.** Standalone it is `admin.php?page=evcert-options`
  (Certificates > Settings). With GatherPress it is the Certificates tab of GatherPress > Settings, and with CampTix the Certificates tab of Tickets > Setup. The tab shows the plugin's screen
  (`Admin\render_settings_body( true )`: its tabs as a list of links, Export and Import, its own form, group `evcert`) in place of the other plugin's form, as GatherPress's own Tools tab does; CampTix's page
  is drawn by the plugin (its title and tabs, as CampTix prints them) because CampTix wraps its tab in a form of its own and forms do not nest.
- **A short address**: `edit.php?post_type=gatherpress_event&page=certificates` and `edit.php?post_type=tix_ticket&page=certificates`, a page of that menu with no item (it is taken out of the menu, as
  GatherPress does with its tabs). The addresses of the tabs (`page=gatherpress_certificates`, `page=camptix_options&tix_section=certificates`) go to it, so the settings have one address.
  `Admin\settings_slug()` (filter `evcert_settings_slug`) and `Admin\settings_url()` say where it is, and every link to the settings (the editor, the generators, the Tools forms) uses them.
- Settings saved so far are the same option (`evcert_settings`): nothing is migrated.
- **Export and import of lists are off with CampTix or GatherPress, for now** (`Restrictions\defaults()`): only a single template travels, with the editor's own Export and Import. The option
  `evcert_restrictions`, the constant and the filters of ADR 0044 turn them on again.

## Where things are (so they are not fragmented)

- **`includes/admin-settings.php` is the settings screen, all of it**: the option and its sanitizing, the sections and fields (`add_settings_fields()`), the tabs (`settings_tabs()`, filter `evcert_settings_tabs`), the
  markup (`render_settings_body()`: Export and Import, the tabs as a list of links, the form) and the address (`settings_url()`). Its styles are `assets/scss/settings.scss` and its script `assets/js/settings.js`
  (both loaded by `add_settings_fields()`, so every mount has them).
- **The mounts only mount**: `Admin\render_settings_page()` (a site with no integration), `Compat\GatherPress\render_settings_tab()` and `Compat\CampTix\render_settings_page()` print a frame (a title, the tabs of
  the other plugin) and call `render_settings_body()`. `SettingsScreenTest` holds them to it: the same tabs and markup on every site, the body whole in every mount, a tab taken out by the filter
  taken out of all, and no field, section or `options.php` in a mount.
- **To add, change or remove** a field or a section: `add_settings_fields()` and its `render_*` functions; a tab: `settings_tabs()`; markup: `render_settings_body()`; a style: `settings.scss`. One edit, three mounts.

## Consequences

- A change to the settings screen is made once and shows up in the three places.
- With CampTix or GatherPress the Export and Import buttons are gone from the lists and the settings, and the export and import screens do not exist.

## Amendment (2026-11-28): the plugin's own screen keeps its own look

The content, the form and the tabs' list are still one (`settings_tabs()`, `add_settings_fields()`, the form in `render_settings_body()`), but how the tabs are drawn depends on the frame: **on the plugin's own screen**
(Certificates > Settings) they are WordPress's tabs (`nav-tab-wrapper`) and Export and Import are beside the page title, as on the other screens; **inside another plugin's settings tab** (GatherPress, CampTix),
which already has tabs and makes this one of them, `render_settings_body( true )` draws a list of links under it (sub-tabs) with Export and Import. `SettingsScreenTest` holds both.
