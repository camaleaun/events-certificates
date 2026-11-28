---
title: "Standalone"
description: "Meetups, courses and workshops with no event plugin: set up, add people, design, issue and share, with the exact names on screen."
---

**Standalone** is the plugin on its own, with no GatherPress or CampTix. It keeps your **events**, **participants** and **attendance**, and it has everything: two generators, the template editor and, if you want it, certificates that anyone can check.

## 1. Set up

Open **Certificates › Settings**. The tabs are Features, Issuing, Verification, Declaration, Data and Tools ([Settings](../../guides/settings/)).

- In **Issuing**, fill **Signed by** and, if you like, the **Certificate signature**.
- In **Features**, turn on **Verification** if each certificate should carry a code and a QR that can be checked. It is off by default, and it needs a site that is public on the internet.

The plugin also makes, the first time they are needed, the **Basic** and **Meetup WordPress** templates, a **Get my certificate** page, a **Certificate** check page and a **Certificates of participation** page ([the public generator](../../guides/generators/)).

## 2. Add the people

1. **Certificates › Events › Add event**, with a name and a date.
2. **Add participants**: type or paste one name per line (a line can be `name, e-mail, Speaker`), choose a **CSV file**, such as the attendee export of Meetup ([Meetup CSV](../../guides/meetup-csv/)), and tick that they all attended.
3. If the *wc-post-types* plugin is active, **Import speakers, organizers and volunteers** brings the schedule's people with their roles.

Roles (attendee, speaker, organizer, volunteer, sponsor) give each person the wording and template that fits: [Roles and languages](../../guides/roles-and-languages/).

## 3. Design

**Certificates › Templates** opens the list and the editor: [Templates and the editor](../../guides/templates/). Start from **Basic** or **Meetup WordPress**, change colors, text and logo, and add one template per role if you want.

## 4. Issue

In **Participants** of the event, use **Mark as attended**, then **Issue certificates**. Or open **Certificates › Generator**, choose the event, preview each person's certificate and use **Download current**, **Download PDF** or **Download ZIP**.

For a one-off with no records, **Switch to Quick** (the Quick generator in the admin) or put the public page `/certificate-generator/` in front of your attendees: both save nothing, so those certificates have no code.

## 5. Share and check

Each issued certificate has its own page and PDF, in the language it was issued in, with share buttons. People can ask for theirs on **Get my certificate** with the e-mail they registered ([Get my certificate](../../guides/get-my-certificate/)), and, with Verification on, anyone can check a code ([Verification](../../guides/verification/)).

## Also in Standalone

- **Export and import** of lists, to move everything to another site ([Export and import](../../guides/export-and-import/)).
- All **abilities** and the WP-CLI tools ([Abilities](../../reference/abilities/), [Command line](../../reference/cli/)).

## What it does not do

It does not e-mail certificates. One PDF download holds up to 400 certificates.

See the examples: [a WordPress meetup with a public page](../../use-cases/wordpress-meetup-public-generator/), [a general meetup with the Quick generator](../../use-cases/meetup-quick-generator/) and [a course with classes](../../use-cases/course-with-classes/).
