---
title: "Identification documents"
description: "Ask for the CPF (Brazil) or a passport on the declaration page, kept like a password."
locales: [ br ]
---

On the [declaration page](../declaration-page/) you can ask the attendee for an **identification document**. It is a custom of some countries, such as **Brazil's CPF**, and it is off until you choose a country in **Settings**, under **Features**.

- **Country:** choose the country whose document you ask for (Brazil: the CPF, with its check digits), or none.
- **Not from that country:** you can let attendees say so; they then give a passport, or nothing.
- **Other countries:** their documents are in the code but off; a developer turns them on with the `evcert_more_document_countries` filter.

## Privacy

The document is stored **like a password**: the site keeps only a way to check a number, never the number itself. The certificate can show that the document was checked without showing it.
