---
title: "Introduction"
description: "What Events Certificates does, what it needs, and where things are in each case: Standalone, GatherPress or WordCamp.org."
---

Events Certificates makes attendance certificates inside WordPress: you mark who came, and each person gets a **PDF** with their name, the event, the date and what they attended. A certificate can also carry a code that anyone checks on your site; that part is optional.

## Pick your case

The plugin adapts to the plugins active on your site. Three cases cover almost everyone:

| Your case | What it means | Guide |
|---|---|---|
| **Standalone** | No event plugin. The plugin keeps your events, people and attendance itself. | [Standalone](../../scenarios/standalone/) |
| **GatherPress** | Your events are GatherPress events; RSVPs and check-ins become participants. | [GatherPress](../../scenarios/gatherpress/) |
| **WordCamp.org (CampTix)** | Tickets are CampTix tickets; attendees issue their own certificate. | [WordCamp.org](../../scenarios/wordcamp/) |

[What works where](../../what-works-where/) lists every feature in each case, and [Both](../../scenarios/both/) says what changes when GatherPress and CampTix are active together.

## What you need

- WordPress 7.1 or later and PHP 7.4 or later.
- Nothing else. GatherPress and CampTix are optional.

## Where things are

The menu depends on the case. In **Standalone** you get a **Certificates** menu:

| Screen | What it is for |
|---|---|
| Certificates | The certificates issued: view, download, correct, revoke or send to the Trash. |
| Events | Your events and their participants. |
| Participants | The people of all your events in one list. |
| Templates | The look of a certificate, and its editor. |
| Generator | Pick an event and download the certificates of who attended. |
| Settings | Who signs, the languages, whether certificates can be checked. |

The **Quick generator** (paste names, download, nothing saved) opens from the Generator with **Switch to Quick**. With GatherPress or CampTix the screens live inside theirs: see each guide. In every case the **Templates** button on the Certificates screen opens the template editor.

## Try it without installing

The Playground link on the [home page](../../../) opens a WordPress in your browser with the plugin and a made-up event.
