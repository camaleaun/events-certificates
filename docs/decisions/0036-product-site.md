# 0036 — The product site lives in the repo (`site/`), is published on a release, and its pictures are taken by script

**Status:** Accepted (2026-11-26).

## Context

The plugin needs a home outside the WordPress.org page: a landing, the docs, a way for agents to read them, and screenshots that
do not go stale or show someone's real name.

## Decision

- **`site/` is an Astro site** (landing page, Markdown docs under `src/content/docs/<group>/<page>.md`, ordered by
  `src/data/sidebar.ts`, which the build checks both ways), built into the repo and kept out of the release zip, PHPCS and
  Plugin Check (`.distignore`, `.gitattributes`, `phpcs.xml.dist`).
- **What the site says of the plugin is read from `readme.txt`** (version, requirements), never written twice. Every page is also
  Markdown (`/docs/<group>/<page>.md`, `/index.md`), with `/llms.txt` and `/llms-full.txt`.
- **It is published when a version is, not on every push**: the Site workflow runs on a version tag and by hand with a ref.
  `SITE` and `BASE` (repository variables) set the address, so the same build serves the repository's Pages now and the
  organization's later.
- **The brand is drawn by `npm run brand`** from `site/brand/logo.svg`: favicon, touch icon, social image and the WordPress.org
  icons and banners (`.wordpress-org/`). Inter's wordmark becomes paths, so no font has to be installed.
- **Screenshots are taken by `npm run screenshots`** on a throwaway Studio site seeded with made-up data
  (`bin/screenshots-seed.php`), never on a working site. `site/src/data/screenshots.ts` is the manifest (id, caption, address,
  where each is shown); `npm run check` fails when a page shows a picture the manifest does not list, a listed one is not
  shown or missing, or the readme's `== Screenshots ==` list is not the manifest's captions in order. The same run writes the
  numbered PNGs of the directory.

## Consequences

- A change to a screen is followed by `npm run screenshots` on a seeded throwaway site; the check says what is out of step.
- The docs are reviewed like code: a Markdown file in a pull request.
