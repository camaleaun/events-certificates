# Contributing to Events Certificates

How the branches, versions and releases work. The decision behind it is
[docs/decisions/0031-release-flow.md](../docs/decisions/0031-release-flow.md).

## Branches

| Branch | What goes in | Version in `events-certificates.php` |
|---|---|---|
| `trunk` | every change, squash-merged from a feature or fix branch | the next `X.Y-dev` (`0.71-dev` until the first release) |
| `feature/<n>-<name>`, `fix/<n>-<name>` | one change, branched from `trunk` (e.g. `feature/7-menus`) | — |
| `release/X.Y` | fixes only, after the release is cut | `X.Y-rc.N`, `X.Y`, then `X.Y.1`… |

A version is `X.Y`, with no patch until one is needed (`0.71`, then `1.0.0` once 0.71 is approved by the directory; a fix to a release is `0.71.1`).

`readme.txt`'s `Stable tag` is the last final release (until the first one, the
version the first release will have).

## After cloning

The third-party libraries are not in git (docs/decisions/0032): run `bin/install.sh` once (composer, npm, `evcert deps`, the
build; `WP="studio wp"` for WP-CLI on Studio). Then the plugin makes PDFs and the checks run. After `composer update` or
an npm version change, run it again (or `wp --require=bin/deps.php evcert deps`, `node bin/third-party-js.mjs`).

## Day to day

```sh
git switch trunk && git pull
git switch -c feature/7-menus
# work, add changelog/7-menus (below), and before each commit, from the plugin folder:
composer check && npm run check-types && npm run test:js
# after editing readme.txt (never README.md, which is made from it), and commit the result:
npm run readme
git push -u origin feature/7-menus
gh pr create --base trunk           # squash-merged: one commit per change
```

## Checks

- `composer check`: lint, PHPCS, the translation template (`bin/make-pot.sh --check`, after `npm run build`) and the tests in
  the four scenarios (none, CampTix, GatherPress, both) against the WordPress site the plugin is in (`WP_LOAD` picks another).
- CI (`.github/workflows/`): `ci.yml` (build, `.pot`, README.md, types, JS tests, lint, PHPCS, the third-party libraries),
  `tests.yml` (PHPUnit on PHP 7.4 (none, CampTix: GatherPress needs 8.1) and 8.3 (the four scenarios) and on a network, Plugin Check and the debug log, in `wp-env`: Docker,
  `.wp-env.json`), `pr-preview-*.yml` (a WordPress Playground link on each pull request) and `release.yml`.
- **Debug log:** every site used for the plugin (the development site, the test sites) runs with `studio config set --debug-log`
  (then restart the site). After a run and before each commit read `wp-content/debug.log` of the sites you touched
  (`grep -i "events-certificates\|PHP \(Warning\|Notice\|Deprecated\|Fatal\)"`): nothing of the plugin's may be there. No file
  means nothing was logged. With `WP_DEBUG` on, the tests turn notices (such as core's "incorrect usage") into errors.
- Plugin Check on what ships: `bin/plugin-check.sh studio wp` from the site root (or `wp`).

## Changelog entries

Every change to what ships (`includes/`, `src/`, `routes/`, `templates/`,
`blocks/`, `events-certificates.php`, `uninstall.php`, `readme.txt`) adds one
file to `changelog/`, named after its branch without the prefix
(`changelog/7-menus`); the CI checks it. Nobody edits `readme.txt`'s Changelog by
hand, so branches never conflict there.

```
Significance: minor
Type: add

Export navigation menus: classic menus with their theme locations.
```

- `Significance`: `patch` (a fix), `minor` (a feature) or `major` (a breaking change). The release command warns when the version is smaller than the entries need.
- `Type` (the label): `add`, `fix`, `update` (the readme's *Added:*, *Fixed:*, *Changed:*) or `dev` (tooling, tests, docs: left out of the readme).
- The entry: one short sentence for users, starting with a verb.

The release command adds the entries to the version's Changelog (a pre-release
adds them, the release adds them and deletes the files).

## Releasing

All from the plugin folder, with WP-CLI (`wp`, or `studio wp` from the site root).
Nothing is pushed unless you add `--push`: **a pushed tag publishes the release**,
so that waits for the owner's OK.

1. **Cut the release branch** when the version's changes are on `trunk`, and move
   `trunk` to the next dev version:
   ```sh
   git switch -c release/0.71 trunk
   git switch trunk
   wp --require=bin/release.php evcert release 1.0.0-dev --no-checks
   ```
2. **Fixes** land on `trunk` first, then on the release branch with
   `git cherry-pick -x <commit>` (it records the origin commit in the message).
   A fix that only makes sense on the release branch is committed there and
   cherry-picked to `trunk`. **Release branches are never merged back.** To see what
   `trunk` has that the release branch doesn't: `git cherry -v release/0.71 trunk`
   (a `+` is still to be picked).
3. **Optional pre-release** for testing, GitHub only (not the latest release, not
   for WordPress.org): write the version's Upgrade Notice in `readme.txt`, then
   `evcert release 0.71-rc.1` from `release/0.71`.
4. **The release**, from `release/0.71`: `evcert release patch` (from `0.71-dev` or
   an `-rc.N`; or `evcert release 0.71`). It bumps the version and `Stable tag`,
   compiles `changelog/`, needs the version's Changelog and Upgrade Notice, runs the
   checks, commits `chore: release 0.71` and tags `0.71`. The tag, once pushed, runs
   `.github/workflows/release.yml`: the zip and the GitHub release.
5. **Back to `trunk`:** `git cherry-pick -x` the `chore: release 0.71` commit, keeping
   `trunk`'s dev version and taking `readme.txt`'s Changelog, Upgrade Notice and
   Stable tag.

`bash bin/package.sh` makes the zip from a committed tree. `--no-checks` skips
`composer check`, the build, types, JS tests and Plugin Check (the checks need `php`
and `composer` on the PATH, and a Studio site for Plugin Check: run them by hand when
the command can't).

Patch releases (`0.71.1`) come from the same `release/0.71`, for bugs that break
something for users; everything else waits for the next minor.
