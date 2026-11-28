=== Events Certificates ===
Contributors: camaleaun, pamprn, deboravenancio, san-prs, gugaalves, claudiosanches, vmassuchetto, donini, valeriosouza, rodrigo-brito, leobaiano
Tags: certificate, events, attendance, meetup, qr code
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.71
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attendance certificates for meetups, courses and WordCamps: paste names or import a Meetup CSV, GatherPress or CampTix. Optional QR code check.

== Description ==

Events Certificates issues attendance certificates for meetups, courses and WordCamps. No PDF files are kept on the server: certificates are records, drawn again whenever someone opens or downloads one. Verification is optional and off by default: turn it on and each certificate gets a unique code and a QR code that open its page on your site, where its name, event and status are shown.

Docs and a demo: read the [documentation](https://camaleaun.github.io/events-certificates/) or [try the plugin in your browser](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/camaleaun/events-certificates/trunk/.wordpress-org/blueprints/blueprint.json) (WordPress Playground, nothing to install).

**Issue certificates**

* **Events and participants**: add an event (name, dates, place, hours), then its participants by typing or pasting names (with e-mail and role, if you have them) or by sending a CSV, such as the attendee export of a Meetup.com event. Mark who attended or didn't come, one by one or in bulk, and issue certificates for those who attended.
* **Quick generator**: fill in the event and paste the list of who came (or send the Meetup CSV or a spreadsheet) and download the certificates as a ZIP (one PDF each) or a single PDF, with nothing saved. It is a page on your site (`/certificate-generator/`) that works in the browser and a screen of the same fields in the admin (Certificates > Quick generator). Its blocks can also be used in any page.
* **Generator**: pick a registered event and see each participant's certificate as it will be; "Issue and download" issues the certificates of who attended (the same ones if you do it again) and sends them as a ZIP or a PDF.
* **GatherPress**: with GatherPress active, import an event: RSVPs "attending" come in as registered and checked-in people as attended. Sync again after the event. Tested with GatherPress 0.37.0.
* **WordCamp.org's CampTix**: on a WordCamp site, attendees get their own certificate from their ticket, with the days they attended and, optionally, a declaration page with the talks they chose.
* **Get my certificate**: a block, the `[evcert_mine]` shortcode and a page (`/certificates/`) where attendees type the e-mail they registered with and get their certificate if they were marked as present. You choose how the e-mail is proven: a simplified view first and a code to get the full certificate (the default), no proof at all, a 4 or 6-digit code or a one-use link sent by e-mail.
* **Export and import**: templates, events, participants, certificates and settings in one JSON file, or events and participants as a CSV with its columns mapped, in a wizard with a progress bar. A certificate is found by its code and never written over; importing twice changes nothing.
* **Trash**: participants and certificates go to a Trash before they are deleted, with restore.

**Design the certificate**

* A visual editor for the certificate template: texts with fields (name, event, date, hours, role…), images, shapes, SVG drawings and the QR code; light, with a dark version as a choice in Settings.
* Several templates: one for speakers, another for volunteers, a default for everyone else.
* Who signs: a name, a role and a signature drawn as SVG, for the whole site or per event.
* Languages: choose the languages certificates are shown in (Settings), and give a text its own words for each one; a language without them shows the default text.

**Share and, optionally, verify**

* Each certificate has its own page, in the site's languages, with PDF download and share buttons (LinkedIn, including "Add to profile", X, Facebook, WhatsApp, e-mail).
* **Verification** (Settings, under "Features"): a code and a QR code on the certificate, and a "Check a certificate" block (and the `[evcert_verify]` shortcode) where anyone types a code. Certificates can then be revoked, or replaced by a correction; the page says so. Without verification a correction simply edits the certificate.
* An optional identification document on the declaration page (a custom of some countries, such as Brazil's CPF) is stored like a password: the site keeps only a way to check a number, never the number itself.

**For AI agents and scripts**

Everything above is also available through the WordPress Abilities API (`/wp-json/wp-abilities/v1`) and, with the MCP adapter, as tools for AI assistants: get an overview, list events, participants and certificates, create and update events, add participants (a list or a Meetup CSV), mark who attended, issue and revoke certificates, quick issue, and the settings. Each ability describes when to use it, its inputs and outputs, and whether it changes or deletes anything; they need the same permission as the screens.

== Installation ==

1. Install and activate the plugin.
2. In **Certificates > Settings**, set who signs and turn on what your event needs (verification, the declaration page).
3. Go to **Certificates > Events**, add an event and its participants, mark who attended and issue their certificates. Or use **Certificates > Generator** (an event you registered) or the **Quick** side of the Generator (a one-off, nothing is saved).
4. Optionally, edit the certificate template with the **Templates** button on the Certificates screen.

== Frequently Asked Questions ==

= Can I move a template with its fonts and pictures to another site? =

Yes. In Certificates, Export, tick Templates: when a template uses a font installed in WordPress or a picture from the Media Library, a box to include them appears, ticked, and the file is a ZIP with the JSON and the folders fonts and images. The Import of the other site installs the fonts in its Font Library and the pictures in its Media Library, and the templates point to them. Check that the licenses of the fonts let you share them.

= Which fonts can a template use? =

The two that come with the plugin (IBM Plex Sans and Instrument Serif) and any font installed in WordPress: Site Editor, Styles, Typography, Fonts. For the PDF to use an installed font, install it as TrueType, OpenType or WOFF (the Upload tab): fonts installed from Google Fonts come as WOFF2, which shows on the page and in the editor but not in the PDF, which then uses IBM Plex Sans or Instrument Serif. A template that names a font the site does not have still works, with the one that stands in for it.

= Can certificates have a dark version? =

Yes: in Settings, Certificate colors, choose Light and dark. The page that checks a certificate then offers both and the template editor shows the dark colors. The PDF is always light.

= How do I make the generator page, the check page or the starter templates again? =

In Certificates, Settings, the Tools tab has one button for each: a page that is there goes to the Trash first, and a template that is there stays as it is. The same actions are in WP-CLI: `wp --user=admin evcert tools list` and `wp --user=admin evcert tools run <id>`.

= How do I get the attendee list from Meetup? =

On the group's page, open the past event, then Attendees, Manage (the gear icon) and "Attendee details": Meetup downloads a CSV file. Send it in the event's "Add participants" form or in the Generator. People who answered "No" are left out, and those Meetup marks as absent come in as no-shows. Only group organizers see the export.

= Why do the step-by-step pictures show New York, and Curitiba in Brazilian Portuguese? =

The four pictures of the Generator's step by step (how to download the attendee list from Meetup) are drawn by the plugin as blocks, with made-up sample data, in your language: Meetup's own words (Manage, Attendees...) are translated into every language. The sample is the WordPress NYC group because it is the largest WordPress meetup in the world, so it is a recognizable example for everyone. Only Brazilian Portuguese changes it, to the WordPress Curitiba group: the Generator was built at WordCamp Brasil 2026, in the Communities table, with that community. A translation into any other language does not change the sample (translating "New York" into another city keeps showing New York), and nothing is requested from Meetup: the pictures are files of the plugin.

= Can I use a spreadsheet? =

Yes: save it as CSV, with a column named "Name" (and optionally "Email"), or copy the rows and paste them in the participants box.

= Which identification document does the declaration page ask for? =

None by default: the declaration page (a second page where attendees declare the days and talks they attended, with an identification document if you ask for one) is a custom of some countries, such as Brazil, and is off until you turn it on in Settings, under "Features". Then choose the country whose document you ask for (Brazil: the CPF, with its check digits), or none, and optionally let attendees say they are not from that country: they give a passport, or nothing. Documents of other countries are in the code but off; a developer turns them on with the `evcert_more_document_countries` filter.

= Can one person have several certificates, as a speaker and an organizer? =

Yes: a person can have several roles (attendee, speaker, organizer, volunteer, sponsor) and each role gets its own certificate, with its own code, text, template and hours. In a pasted list write the roles in the same line ("Ana Lima, ana@example.com, Speaker / Organizer") or repeat the name; in the people list click "+ Role". Each role says its own words ("gave a talk at", "organized"…), and an event can give each role its own hours. Roles come from the lists, from GatherPress (its organizers), from WordCamp.org's speakers, organizers and volunteers, and from the role you set on a CampTix ticket.

= Why does my certificate show a placeholder instead of a QR code? =

Verification (a code and a QR code on the certificate) is off by default: turn it on in Settings, under "Verification". Even then the QR code and the verification link only make sense when anyone can open your site. On WordPress Playground, localhost, .local or .test addresses and private networks the plugin shows a placeholder of the same size instead. An administrator can treat a public staging site as public in Settings.

= How do attendees get their certificate by themselves? =

With the "Get my certificate" page (`/certificates/`, made when you first open the admin; or the block or `[evcert_mine]` shortcode in any page): the attendee types the e-mail they registered with and gets their certificate, if they were marked as present; otherwise they are told so. In Settings, under "Get my certificate", choose how the e-mail is proven: **Simple** (the default: the e-mail shows a simplified certificate, and the Dock's "Authenticate" sends a 6-digit code and a link), **no authentication**, a **4 or 6-digit code** by e-mail (optionally with a link too) or a **one-use link**. Codes are kept only as a hash, last 15 minutes and burn after 5 wrong tries; links work once, for an hour; requests are limited. With CampTix, the link of the ticket e-mail still works.

= Can certificates be in more than one language? =

Yes. A certificate page, its PDF and the generator use the languages installed on the site (English always); in Settings, under "Languages", you can limit them to the ones you want (the site's language is always there). In the template editor, a text can have its own words for each language; one without them shows the default text.

= Does deleting the plugin delete the certificates? =

No, unless an administrator chose to delete all data. Printed certificates point to their verification page, so they are kept by default. Cached share images and font files are always removed.

= Does it send data anywhere? =

Not by itself: certificates, participants and PDFs are made on your site, with fonts and libraries bundled in the plugin. See "External services" below for what the administrator's or visitor's browser loads, and when.

= Does the generator work with classic themes and the Classic Editor? =

Yes. The generator page is made of blocks, and the block editor works with a classic theme too; the page has its own template, "Certificate generator (blank canvas)" (a block template in a block theme, a page template in a classic one), which shows the page with no header or footer. For the Classic Editor or a page builder use the shortcode `[evcert_quick_generator]`: it draws the same blocks. It takes the settings of the blocks as attributes: `community`, `title`, `venue`, `city`, `speakers`, `link`, `signName`, `signRole`, `showHours` and `accent`. A theme can have its own page template by adding `evcert-quick-generator.php`.

= Where is the source code? =

The PHP is in the plugin, readable. The editor's JavaScript in `build/`, `build-signer/` and `build-blocks/` is compiled; its source (TypeScript, in `src/` and `routes/`) is at https://github.com/wpbrasil/events-certificates. The scripts and styles in `assets/js/` and `assets/css/` come with a readable copy next to each `.min` file; their source is in `assets/es/` and `assets/scss/` in the same repository. Build it all with `npm ci && npm run build`.

The libraries in `assets/third-party/` are the ones their authors publish, copied from npm by that build: jsPDF (https://github.com/parallax/jsPDF), JSZip (https://github.com/Stuk/jszip) and SheetJS (https://github.com/SheetJS/sheetjs). The PHP libraries in `includes/third-party/` are readable and come from Composer (dompdf, php-font-lib, php-svg-lib, html5-php, PHP CSS Parser, php-qrcode, php-settings-container and camaleaun/draw, whose TypeScript source, compiled into `build/`, is at https://github.com/camaleaun/draw).

== Credits ==

The certificate generator comes from the Meetup certificate generator by Pamela Ribeiro ([@pamprn](https://profiles.wordpress.org/pamprn/)), built with Débora Venâncio de Oliveira ([@deboravenancio](https://profiles.wordpress.org/deboravenancio/)) and Sandra Peres ([@san-prs](https://profiles.wordpress.org/san-prs/)), started at the Contributor Day of WordCamp Brasil 2026. The original is at https://github.com/pamprn09/gerador-certificados-meetup, used here with the author's authorization; its layout and tutorial were kept (the tutorial's pictures are redrawn as blocks).

Ideas for this version also came from [gugaalves](https://github.com/gugaalves).

The plugin also learns from earlier certificate projects of the WordPress community in Brazil:

* [wpbrasil/certificates-for-wordcamp](https://github.com/wpbrasil/certificates-for-wordcamp) (MIT) and [wpbrasil/camp-certificates](https://github.com/wpbrasil/camp-certificates) (GPL-2.0), by the WP Brasil community, with contributions by [claudiosanches](https://github.com/claudiosanches), [rodrigo-brito](https://github.com/rodrigo-brito) and [leobaiano](https://github.com/leobaiano).
* [vmassuchetto/certificate-generator](https://github.com/vmassuchetto/certificate-generator), later [wpbrasil/gerador-de-certificados](https://github.com/wpbrasil/gerador-de-certificados), used at the WordCamps in Brazil, by [vmassuchetto](https://github.com/vmassuchetto) and others.

With thanks to the people of these projects: [vmassuchetto](https://github.com/vmassuchetto), [claudiosanches](https://github.com/claudiosanches), [donini](https://github.com/donini), [valeriosouza](https://github.com/valeriosouza), [rodrigo-brito](https://github.com/rodrigo-brito) and [leobaiano](https://github.com/leobaiano).

Sensei's certificates extension, [Sensei Certificates](https://github.com/woocommerce/sensei-certificates) (GPL-2.0), was also a reference.

== External services ==

**WordPress.org (language names).** To name the site's languages the way WordPress's language selector does ("Português do Brasil"), the plugin reads WordPress's list of translations with the core function wp_get_available_translations(), which asks the WordPress.org API (api.wordpress.org/translations/core/) for it. It runs in the admin, for administrators, only when a language of the site has no name stored yet, at most once a day; it sends the WordPress version and the site address WordPress sends on its own update checks. The names are stored in the site, so the public certificate page makes no request. WordPress.org privacy policy: https://wordpress.org/about/privacy/

**Gravatar (avatars in the admin).** The Certificates screen shows each participant's avatar as WordPress.org does: when a certificate has a WordPress.org username, the administrator's browser loads it from https://wordpress.org/grav-redirect.php (which redirects to Gravatar), sending that username; otherwise from Gravatar (https://gravatar.com), as WordPress does for any avatar. Gravatar terms: https://automattic.com/tos/ and privacy policy: https://automattic.com/privacy/

**Share buttons (certificate page).** The share buttons are plain links: nothing is sent until the visitor clicks one. Then their browser opens that service with the certificate's address and title (and, for "Add to LinkedIn profile", the event, the site's name, the issue date and the code).

* LinkedIn: terms https://www.linkedin.com/legal/user-agreement, privacy https://www.linkedin.com/legal/privacy-policy
* X: terms https://x.com/en/tos, privacy https://x.com/en/privacy
* Facebook: terms https://www.facebook.com/terms, privacy https://www.facebook.com/privacy/policy
* WhatsApp: terms https://www.whatsapp.com/legal/terms-of-service, privacy https://www.whatsapp.com/legal/privacy-policy

== Bundled libraries and fonts ==

* dompdf 3.1 (LGPL-2.1), php-font-lib (LGPL-2.1-or-later), php-svg-lib (LGPL-3.0-or-later), html5-php (MIT) and PHP CSS Parser (MIT): PDF output.
* php-qrcode and php-settings-container (MIT): QR codes.
* camaleaun/draw 0.14 (GPL-2.0-or-later, by the plugin's author, https://github.com/camaleaun/draw): reads and writes a certificate design as block markup, in PHP (`includes/third-party/camaleaun/draw/`) and in TypeScript (compiled into `build/`).
* IBM Plex Sans and Instrument Serif (SIL Open Font License 1.1, subsets in `fonts/`): the generator's certificate. The plugin ships no other font: the ones a template uses are installed in WordPress.
* jsPDF 2.5.1 (MIT), JSZip 3.10.1 (MIT or GPL-3.0-or-later) and SheetJS Community Edition 0.18.5 (Apache-2.0), in `assets/third-party/`: the generator's PDF, ZIP and spreadsheet reading in the browser. They are plugin files; nothing is loaded from a CDN.
* Lucide icons (ISC), redrawn as filled shapes, in `assets/images/tutorial-step-image/icons/`: the Generator's step by step.

The PHP libraries are copied into `includes/third-party/` with their own namespace (`EventsCertificate\Vendor\…`), so they don't clash with other plugins.

== Screenshots ==

1. The Generator: pick an event, preview each certificate, download.
2. The participants of an event, with the status, roles and certificates.
3. The Certificates screen: issued, replaced and the Trash.
4. The template editor.
5. Export to a file.
6. The settings and the certificate signature.

== Changelog ==

= 0.71 =

* First public release.
* Certificates are light by default; Settings has a Certificate colors choice for light and dark (the PDF is always light).
* Events and participants (typed, pasted, Meetup CSV), check-in, no-shows and certificates in bulk; downloads as a ZIP or a single PDF.
* Quick generator (admin screen and a front-end page), with a step by step drawn in the site's language, and a Generator of registered events.
* Community logo and signature picture, per event.
* GatherPress import and sync.
* WordCamp.org CampTix: certificates from the attendee's ticket, days attended and a declaration page.
* A Tools tab in Settings and `wp evcert tools` to make again the pages and the starter templates, repair the tables and the addresses and clear the caches.
* Two templates to start with: Basic (the default) and Meetup, which the Quick generator uses; the Templates list says which is which.
* A clearer form to add an event: examples from the Quick generator, the end from buttons (1 to 3 hours), the logo beside the community, and what you typed kept when the event is refused.
* Template editor (one element for the QR code, the code and the hint, a place for the community's logo, a command palette), templates by role, signer per site or event.
* Optional verification (off by default): code, QR code, "Check a certificate" block and shortcode; PDF and share image.
* Get my certificate: attendees type their e-mail and get their certificate if they were marked as present; proven by a code or a link (a simplified view by default).
* Languages: choose the languages certificates are shown in, and write a text in each.
* One certificate per role: a person can be an attendee, speaker, organizer, volunteer or sponsor of the same event, each with its own certificate, wording, template and hours. Roles from lists, GatherPress organizers, WordCamp.org people and CampTix tickets.
* Correct a certificate's name or workload and reissue it: the old one is marked as replaced.
* Abilities (WordPress Abilities API) for AI agents and MCP.

== Upgrade Notice ==

= 0.71 =

First public release.
