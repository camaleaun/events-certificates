# 0032 — Third-party libraries are made, not committed: `includes/third-party/` and `assets/third-party/`

**Status:** Accepted (2026-11-19). Replaces [0003](0003-prefixed-dependencies-strauss.md).

## Context

dompdf and php-qrcode (and what they need) must not clash with other plugins that load their own versions, so
their namespaces are renamed. The first answer was Strauss, an 11 MB phar downloaded by hand, with a
hand-written patch for one library, and the result (`vendor-prefixed/`, 335 files, 3.9 MB) committed. The three
JavaScript libraries of the generator were committed in `assets/vendor/`, one of them edited by hand.

## Decision

- **The libraries are made, not committed.** `includes/third-party/` (PHP) and `assets/third-party/` (JS) are in
  `.gitignore`. Nothing in the plugin is called `vendor` or `vendor-prefixed`; Composer's own `vendor/` stays what
  it is, the development packages and the libraries' sources, never shipped.
- **PHP: `evcert deps`** (`bin/deps.php`, the work in `bin/deps-lib.php`, tested by `DepsTest`) copies the runtime
  packages of `composer.lock` and renames them under `EventsCertificate\Vendor\…`, the same names as before, so the
  plugin's code did not change. It reads the files' tokens, not their text: the namespace, the imports, fully
  qualified names and class names written as strings (`"Dompdf\\Adapter\\GD"`) are rewritten; relative and bare
  names follow their namespace. It writes a small autoloader (a map from class to file, and the files that must
  run first) instead of Composer's. The few changes a library needs besides its names (php-font-lib reads its
  font type from a namespace segment by position) are listed in `evcert_deps_patches()`, and stop the build if the
  line they change is gone: a library update is looked at, not silently broken.
- **JS: `bin/third-party-js.mjs`** copies jsPDF, JSZip and SheetJS from `node_modules` (versions pinned exactly in
  `package.json`, checked against the ones the plugin registers) with their licenses, and blanks the CDN address
  in jsPDF (the plugin loads nothing from a CDN). `npm run build` runs it.
- **When:** after cloning, `bin/install.sh` (composer, npm, `evcert deps`, build); `bin/package.sh` and the release
  workflow run the same steps, so the zip has both folders. A missing `includes/third-party/` shows an admin notice
  that says to run `bin/install.sh` instead of a fatal error.
- **Proof it is the same:** the first build was compared with the Strauss copy: the same 246 classes, and the same
  code in every file once comments are set aside (Strauss also rewrites names inside comments).

## Consequences

- The repository is 340 files and 4 MB lighter; the zip is the same size.
- A plain clone needs `bin/install.sh` before the plugin makes PDFs; the Playground blueprint and the PR previews
  install a built zip, so they are not affected.
- The tokenizer PHP extension is needed to run `evcert deps` (not to run the plugin).
- Updating a library is `composer update <package>` (or the pinned npm version), then the commands above; the
  tests (`DepsTest`, `RenderTest`) say whether it still works.
- `includes/third-party/` and `assets/third-party/` are left out of lint, PHPCS and Plugin Check (third-party code
  with its own licenses, kept next to each package).
