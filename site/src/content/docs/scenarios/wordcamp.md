---
title: "WordCamp.org (CampTix)"
description: "Certificates for a WordCamp on CampTix: organizers mark presence by day, attendees issue their own certificate from their ticket."
---

With **CampTix** active, as WordCamp.org runs it, **attendees issue their own certificate** from the e-mail of their ticket. Organizers only mark who was present. CampTix is not in the WordPress.org plugin directory; the schedule (talks) comes from the *wc-post-types* plugin.

## 1. Set up

The settings are a **Certificates** tab of **Tickets › Setup**. Besides the usual tabs, it has:

- **Attendees get their own**: **When marked as present** (the default), **With any confirmed ticket**, or **No**.
- **Event days**: tables or sessions. Until you save them they are filled from the *wc-post-types* schedule.
- **Declaration**: the extra page the attendee fills in ([Declaration page](../../guides/declaration-page/)).

Set a **role** on each ticket in its role box: attendee, speaker, organizer, volunteer or sponsor.

## 2. Mark who was present

In **Tickets › Attendees** the **Days** column has a badge per day: click it to **Mark present on** that day (or at a contributor-day table).

## 3. The attendee issues

The ticket e-mail links to the ticket page, which now has a **Certificate** step: the attendee chooses the days or table they attended (and the talks, with *wc-post-types*), fills the declaration if the event asks for it, and uses **Issue certificate**. A mistake is fixed with **Correct and reissue**, and the old certificate is marked replaced.

## 4. Design

The **Templates** button on **Tickets › Certificates** opens the editor: [Templates and the editor](../../guides/templates/). Each role can have its own template.

## 5. Get and check

`/certificates/` finds tickets by e-mail ([Get my certificate](../../guides/get-my-certificate/)). In **Tickets › Certificates** an organizer can **Revoke** or correct a certificate. A revoked certificate is final.

## What is different here

- There is no participants screen for organizers, so **no bulk issue**: attendees issue their own. With **Attendees get their own** on **No**, nobody can issue.
- No Events screen, no Generator and no public generator page of this plugin; the WordCamp is the event.
- **Export and import of lists** is off; one template can still be exported.
- Only some abilities: overview, certificates and settings.
- It does not e-mail certificates (only login codes).

See [self-issue at a WordCamp](../../use-cases/wordcamp-self-issue/).
