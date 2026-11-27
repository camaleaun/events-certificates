# 0034 — Certificates have a Trash, and the Trash is "off the record"

**Status:** Accepted (2026-11-26).

## Context

A certificate could only be revoked, and revoking is final: the code keeps answering, saying it was revoked. There was no
way to take out a certificate made by mistake (a test, a duplicate), and the lists of events and templates already had a
Trash.

## Decision

- **A new status, `trash`**, with `trashed_from` (the status it had) and `trashed_at` (schema 5). Trash is for
  `issued`, `replaced` and `revoked`; drafts are never listed.
- **In the Trash a certificate is off the record**: its code answers nowhere (`Certificates\get_by_code()` leaves it
  out, so the page, the PDF, the share image and the check form behave as for a code that does not exist), it is not
  listed or counted, a replaced one stops linking to it, and the person's role stops pointing at it, so they have none
  and Issue makes a new one. A code stays reserved: a new certificate never reuses it.
- **Restore** goes back to the status it had. A valid certificate **does not come back over a newer valid one** issued to
  the same person in the same role meanwhile (decision 0020: never two valid ones); it stays in the Trash and the screen
  says so.
- **Delete permanently** only from the Trash: it removes the share images, the links of roles to it and the
  "replaced by" of the one it replaced (that one stays `replaced`). Design snapshots are shared and stay. **Empty Trash**
  does it for all of them.
- **The screen** has the views All | Issued | Replaced | Revoked | Trash, a checkbox on every row (with or without
  verification), Move to Trash for a row and in bulk, and Restore / Delete permanently in the Trash.

## Consequences

- Moving to the Trash is the way to undo an issue; revoking is the way to say a certificate is no longer valid and keep
  saying it.
- A trashed certificate is never an obstacle to issuing again, which is why Restore has to check.
