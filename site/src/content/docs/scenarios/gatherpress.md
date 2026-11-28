---
title: "GatherPress"
description: "Certificates for GatherPress events: RSVPs and check-ins become participants, from inside GatherPress."
---

With **GatherPress** active, your events are GatherPress events. The plugin reads who said yes and who checked in, and gives each person a certificate. It only **reads** GatherPress data; it never changes it. Tested with GatherPress 0.37.0.

## 1. Set up

The settings are a **Certificates** tab of **GatherPress › Settings** (`Events › Settings`). It is the same screen as in Standalone, with the same tabs: [Settings](../../guides/settings/).

## 2. Bring the people

Run your event in GatherPress: people RSVP, and you **check them in** on the day. Then, in the **Events** list, use the **Certificates** action of the event's row:

- people whose RSVP is **attending** arrive as **registered**;
- people who **checked in** arrive as **attended**;
- GatherPress organizers arrive with the **organizer** role.

In **Add participants**, **Sync with GatherPress** brings the changes made since. A sync never demotes anyone, and someone you moved to the Trash does not come back. You can also add people yourself: type names or choose a **CSV file** ([Meetup CSV](../../guides/meetup-csv/)).

## 3. Design

The **Templates** button on the **Certificates** screen opens the list and the editor: [Templates and the editor](../../guides/templates/).

## 4. Issue

In **Participants**, use **Issue certificates** (or **Issue the missing certificates** later). Each certificate has its own page and PDF, and people can ask for theirs on **Get my certificate** with the e-mail they registered.

## What is different here

- The events are GatherPress's: there is no Events screen of this plugin, no **Generator** and no Quick generator in the admin. The public page `/certificate-generator/` is not made; the block and the `[evcert_quick_generator]` shortcode still exist.
- **Export and import of lists** is off. A single template can still be exported and imported from the editor.
- Fewer abilities: events are GatherPress's ([Abilities](../../reference/abilities/)).
- It does not e-mail certificates.

See [a GatherPress meetup](../../use-cases/gatherpress-meetup/).
