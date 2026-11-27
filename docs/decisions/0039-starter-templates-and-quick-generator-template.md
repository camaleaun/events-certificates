# 0039 — A site starts with two templates, Basic and Meetup, and the Quick generator draws its own

**Status:** Accepted (2026-11-27).

## Context

A site used to start with one template, "Certificate", made from the Base design, and the Quick generator (the public page and the
admin screen) always drew the default template. A meetup that only wants to hand out certificates from a list of names should not have
to edit the template everything else uses, and the neutral Base has nothing that says "meetup".

## Decision

- **A new site starts with two templates**, made the first time one is asked for (`Designs\seed()`, called by `site_design_id()`), in
  the site's language and not the editing user's: **Basic** (the default: events, issuing, CampTix and GatherPress, verify, mine) and
  **Meetup** (the Quick generator's). Basic is a plain certificate (`templates/basic-design.json`, `Designs\basic()`) as simple as makes sense, after the community's old generators (the 2012 certificate of WordCamp Curitiba): a background that looks like a certificate (one `svg` element of large filled shapes in the template's Lines color, with an opacity, so it follows the light and the dark mode like any color: two frames, a set of large circles (rings and a disc) in the middle and another in each corner reaching into its quadrant, so little of the page is empty and the band behind the name, which goes from edge to edge, all in the same drawing, no thin lines; `bin/make-basic-background.py` draws it, puts it in `templates/basic-design.json` and writes `templates/basic-background.svg`, the same drawing to look at, not shipped; it is our own artwork), the title (just "Certificate", the same for every role: what the person did is in the sentence), the name on the band across the page, one sentence (what the person did and the event) with the workload below in two small lines ("Workload:", the `hours_label` phrase, and "8 hours"), the signature with its name and role, and the date of issue. The signature place is empty: the template has no signature of its own, it takes the one that is defined (Settings or the event). It has no logo place and no verification block (the editor's Add element brings them), no date of the event and no texts of its own: every word is a phrase the plugin already translates. It has none of what the Quick generator asks for beyond that (community, venue, time, speakers, link): those are Meetup's. Meetup is the Base design with "WordPress Meetup" on its band
  (`Designs\meetup()`); the sentence under the name stays the role's own ("took part in", "gave a talk at"). Meetup has no roles, so
  no certificate picks it.
- **The Quick generator's template is a site option**, `evcert_quick_design` (with the filter `evcert_quick_design_id`), that starts
  pointing at Meetup. `Designs\quick_design_id()` gives it, or the default template when it is gone, in the Trash or not a template.
  The public block and shortcode (`QuickGenerator\config()`, `sheet()`) and the admin screen (its sample and the PDFs and ZIP it
  makes, for every role) draw it, so the download is the preview.
- **The Templates list** says which template is the default and which is the Quick generator's, and has a row action, "Use in the Quick
  generator". The Quick generator's template can go to the Trash (the default one can't): it falls back to the default.
- **The export bundle** says which template is the Quick generator's (`quick`), as it says which is the default; an import does not
  apply it, as it does not apply the default.
- **The Playground blueprint** no longer writes a filter for the meetup wording: the Meetup template has it. "Restore the Base design"
  on Meetup gives the neutral Base back.

## Left out

- A migration: this is the first version, so no site has the old single template.
- A setting to choose the Quick generator's template in Settings: the Templates list is where templates are.

## Consequences

- The Base design (the one with a band) stays neutral and is what Meetup is made from. "New template", a blank design's page and "Restore the Basic design" start from Basic. A network's `evcert_base_design` filter gets the design and its name (`base` or `basic`) and reaches both.
- `Certificates\design()` and the template by role are unchanged for everything that issues or verifies.

## Update (2026-11-27)

The Basic design's background also exists as a compressed grey-and-transparency PNG (`templates/basic-background.png`) beside the SVG,
the same drawing, kept in the plugin as a picture (0041). The design itself keeps the SVG.

The Quick generator draws the Meetup starter when no template was chosen for it (or the chosen one is gone), before falling back to the
default template: its band says "Meetup WordPress" along its side (turned 90 degrees, white, bold, as in the Meetup certificate generator
it comes from), which the neutral Base design leaves empty.

The Meetup template is titled **Meetup WordPress**, and so is the text on its band (the Meetup certificate generator it comes from says
"MEETUP WORDPRESS"): the same words in every language, translated where a language says them otherwise. A site that already has the
"Meetup" template keeps it: it is still found as the Meetup starter.
