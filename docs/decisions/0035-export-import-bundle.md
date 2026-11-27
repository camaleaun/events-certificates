# 0035 — Export and import: one versioned JSON bundle

**Status:** Accepted (2026-11-26).

## Context

Templates, events, participants, certificates and settings live in posts, post meta, three tables and options. The core's Tools >
Export writes a WXR of posts only, so it cannot carry the tables, and ids change on import. The owner asked to move or keep all
of it, from the lists and per kind.

## Decision

- **One JSON file, `events-certificates/bundle/1`**, with the sections `templates`, `events`, `people`, `certificates` and
  `settings`. People need their events and certificates need their people, so asking for them brings those along.
- **Ids do not travel.** The file has its own keys; the import maps them to the new ids (events, participants, templates) and a
  certificate's "replaced by" and a participant's role link are set from the certificates' codes.
- **A certificate's code is its identity.** Importing twice changes nothing: the same code with the same content is skipped; a
  code that is already there with other content is reported as a conflict and never written over. Templates are matched by name,
  events by their GatherPress record or name and start, participants as when they are added (record, e-mail, name). "Update
  what is already here" rewrites templates, events and participants, never a certificate.
- **Nothing in the file is trusted.** It is read up to a size limit (20 MB by default, `evcert_bundle_max_bytes`), and every
  value goes through the plugin's own cleaning again: `Events\clean()`, `People\clean()`, the status and code of a certificate,
  its dates, the pictures and drawings in its data (`clean_image()`, `clean_vector()`, SVG decision 0005), text without markup,
  a limited depth. A design snapshot is only kept when its hash is the SHA-256 of its content.
- **The secret key (pepper) travels with the settings.** It is what lets a document number typed later be checked against the
  hash a certificate holds; without it those checks stop matching on the other site. The file is a secret and the screen says
  so. It is only taken by a site that has no certificate checked by its own key; otherwise the site keeps its key and says so.
- **Screens, as WooCommerce's exporter and importer**: two hidden screens, Export (`page=evcert-export`) and Import
  (`page=evcert-import`), each a card with a header, the fields and a footer with the buttons. Export / Import buttons next to
  the titles of the Certificates, Events, Templates and Participants lists open them with that section ticked.
- **The import is a wizard in four steps**: upload the file (and choose whether what is already here is updated), review what
  the file has and choose the sections, import with a progress bar, done with the counts and a log of what was left alone.
  The work goes in batches (`Bundle\begin()`, `Bundle\step()`): each request of the screen does a couple of seconds, so a big
  file does not hit a time limit, and the state lives in a private file between requests. The uploaded file and the state are
  kept as PHP files that stop at once (`<?php exit; ?>` on the first line), in the temporary folder, readable only by the
  server, and thrown away when the import ends or after a day. Everything needs the management capability and a nonce.
- **CSV too**, for a spreadsheet: Export can write one table (events, participants or certificates; a cell that starts like a
  formula is quoted, and the file is UTF-8 with a mark). Import reads a CSV of events or participants with the column mapping
  step of WooCommerce's importer (the headers are matched by name in English and Portuguese); the rows become a small bundle
  and go through the same cleaning, matching and batches. Certificates are not imported from a CSV: they have codes and
  history, so they travel only in the JSON bundle.

## Consequences

- A bundle is a backup of what the plugin keeps, and the way to start a site from another one.
- The data of a certificate that is not on the list of known shapes is cleaned as plain text, so a very new field needs the
  cleaning taught when it is added.

## Update (2026-11-27)

The bundle can travel as a ZIP with the fonts and pictures the templates use (0043); the JSON inside has the same schema, with an optional
key `assets`.

## Addendum (0.72): pictures are references

The JSON of a bundle never holds a picture's bytes. The logo and signature of events, certificates and Settings are `picture:<sha256>`
references to files in the ZIP's `images/` folder; a plain JSON has those places empty. A data URI or an ID found in an older file is left
out on import. See ADR 0044.

## Addendum (0.72): templates as blocks

In the file, each template's `design` is a string of block markup (ADR 0045), not an array; the importer takes only that form (a template with an array as its
design is left out). The schema name does not change.
