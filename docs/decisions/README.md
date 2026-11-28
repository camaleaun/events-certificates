# Decisions

Why Events Certificates is built the way it is. One file per decision: the
question, what was chosen, what was left out and why, and what follows from it.

| # | Decision | Status |
|---|---|---|
| [0001](0001-certificates-are-records.md) | Certificates are frozen records; PDFs and images are generated on demand | Accepted |
| [0002](0002-pdf-engine-dompdf.md) | PDF engine: dompdf, with php-qrcode for the QR code | Accepted |
| [0003](0003-prefixed-dependencies-strauss.md) | Bundled libraries are prefixed with Strauss and committed | Replaced by 0032 |
| [0004](0004-design-model.md) | One design JSON in points, rendered to SVG/HTML on screen and to PDF | Accepted |
| [0005](0005-restricted-svg.md) | SVG is kept as fields (viewBox + filled paths), never as markup | Accepted |
| [0006](0006-identity-documents-as-passwords.md) | Identity documents are stored like passwords and shown masked | Accepted |
| [0007](0007-admin-ui-gutenberg-components.md) | Admin: classic screens in CampTix; the canvas editor uses only public Gutenberg components | Accepted |
| [0008](0008-editor-translations.md) | The editor's translations are handed to wp.i18n by the plugin | Accepted |
| [0009](0009-screen-views.md) | Print page plus screen views (desktop, tablet, mobile) with per-screen overrides | Accepted |
| [0010](0010-non-public-sites.md) | Sites that are not public print no QR code, code, verification hint or address | Accepted |
| [0011](0011-on-record-not-authentic.md) | The certificate page says "on record" and asks to compare, never "authentic" | Accepted |
| [0012](0012-check-page-in-the-site.md) | "Check a certificate" is a block in a page of the site; only a certificate is full-screen | Accepted |
| [0013](0013-templates-by-condition.md) | Certificate templates chosen by role, a default for everyone else | Accepted |
| [0014](0014-declaration-page.md) | Declaration page with declared activities; the document in full only in the PDF when issuing | Accepted |
| [0015](0015-issuing-without-camptix.md) | Issuing without CampTix: events, participants, generator, GatherPress | Accepted |
| [0016](0016-abilities-for-agents.md) | Abilities for AI agents and MCP | Accepted |
| [0017](0017-generator-page-and-base-design.md) | The generator: one page in blocks, one certificate look (the Base design), libraries bundled | Accepted |
| [0018](0018-assets-source-and-build.md) | Stylesheets and scripts: SCSS and ES sources, built files in assets/css and assets/js | Accepted |
| [0019](0019-integrations-and-test-scenarios.md) | Integrations: one place decides, four scenarios are tested | Accepted |
| [0020](0020-certificate-lifecycle-rules.md) | A certificate's life: only a valid one is corrected, revoked is final, the PDF is for valid ones | Accepted |
| [0021](0021-certificate-language.md) | The language of a certificate: the one it was issued in, the visitor may switch | Accepted |
| [0022](0022-certificate-addresses.md) | The addresses of a certificate: the language and the PDF are path endpoints | Accepted |
| [0023](0023-base-design-filter-and-blueprint.md) | The Base design: flush, neutral, filterable; a Playground blueprint gives the meetup wording | Accepted |
| [0024](0024-certificate-page-dock.md) | The certificate page's Dock: welded to the edge, draggable, folds into a corner | Accepted |
| [0025](0025-one-certificate-per-role.md) | A person has roles, and a certificate for each | Accepted |
| [0026](0026-classic-screens-like-core.md) | Classic admin screens that look like core's | Accepted |
| [0027](0027-identification-documents-by-country.md) | Identification documents by country, chosen in Settings | Accepted |
| [0028](0028-verification-off-by-default.md) | Verification (code and QR code) is off by default | Accepted |
| [0029](0029-settings-by-feature.md) | The settings are organized by feature; the document is one country | Accepted |
| [0030](0030-get-by-email.md) | "Get my certificate": the e-mail, a proof of it and a page of its own | Accepted |
| [0031](0031-release-flow.md) | The release flow: `trunk` in development, `release/X.Y` by cherry-pick, changelog in files | Accepted |
| [0032](0032-third-party-libraries.md) | Third-party libraries are made, not committed: `includes/third-party/` and `assets/third-party/` | Accepted |
| [0033](0033-tutorial-step-image.md) | The tutorial's pictures are blocks inside an SVG (`tutorial-step-image`) | Accepted (2026-11-25) |
| [0037](0037-palette-with-fixed-entries.md) | The palette: two fixed entries, Light and optional Dark, a white page not painted in a PDF | Accepted, in part replaced by 0038; its fixed entries by 0051 |
| [0038](0038-palette-separate-from-colors.md) | The palette is a list of swatches; the template's colors keep their own Light and Dark values | Accepted (2026-11-26) |
| [0039](0039-starter-templates-and-quick-generator-template.md) | A site starts with Basic and Meetup; the Quick generator draws its own template | Accepted (2026-11-27) |
| [0040](0040-tools-tab-and-wp-cli.md) | A Tools tab and WP-CLI to make again what the plugin makes once | Accepted (2026-11-27) |
| [0041](0041-certificates-are-light-dark-is-a-choice.md) | Certificates are light; dark is a choice | Accepted (2026-11-27) |
| [0042](0042-fonts-installed-in-wordpress.md) | Fonts are installed in WordPress; the plugin ships only the two small ones | Accepted (2026-11-27) |
| [0043](0043-templates-travel-with-fonts-and-pictures.md) | Templates travel with the fonts and pictures they use: a ZIP | Accepted (2026-11-27) |
| [0044](0044-restrictions-pictures-and-security.md) | Restrictions for networks, pictures as attachments, a strict entry | Accepted (2026-11-27) |
| [0045](0045-designs-as-blocks.md) | A design is kept as blocks | Accepted (2026-11-27) |
| [0046](0046-canvas-on-the-block-editor-store.md) | The canvas on the block editor's store | Accepted (2026-11-27) |
| [0047](0047-certificate-blocks-and-single-svg-block.md) | A certificate with blocks of its own, and an SVG as one block | Accepted (2026-11-27) |
| [0048](0048-signature-is-a-place-of-its-own.md) | The signature is an element of its own, and an SVG's size is in its Size row | Accepted (2026-11-27) |
| [0049](0049-canvas-zoom-fits.md) | The zoom of the canvas has three fits, and the Settings aside starts closed | Accepted (2026-11-27) |
| [0050](0050-names-of-a-block.md) | An element is written as its block says it (width, height, viewBox, no generated id) | Accepted (2026-11-27) |
| [0051](0051-colors-without-fixed-entries.md) | Colors without fixed entries: any color may be renamed and removed, the code's own colors only in the certificate, no Reset colors | Accepted (2026-11-27) |
| [0052](0052-units-current-color-layers.md) | Units on every length, an empty color, layers listed back to front, close settings as the core's | Accepted (2026-11-27) |
| [0053](0053-the-group-element.md) | The group element: Group, Row, Stack and Grid, what is in it placed from its corner, grouped from a selection | Accepted (2026-11-27) |
| [0054](0054-text-written-as-text.md) | The text of an element written as text in a file and on the clipboard: `[fields]`, `*bold*`, `_italic_` | Accepted (2026-11-28) |
| [0055](0055-the-date-of-emission.md) | The date of emission: a text with its own block whose words are a date format; empty, the site's | Accepted (2026-11-28) |
| [0056](0056-selection-and-clipboard.md) | Selecting, grouping and copying several elements: the editor's list of ids, a box, the post editor's look and toolbar, copy and paste | Accepted (2026-11-28) |
| [0057](0057-one-settings-screen.md) | One settings screen, wherever the plugin is: the plugin's own, a short `certificates` page under Events or Tickets | Accepted (2026-11-28) |

New decisions get the next number. A decision that is replaced keeps its file,
with its status changed to "Replaced by NNNN".
