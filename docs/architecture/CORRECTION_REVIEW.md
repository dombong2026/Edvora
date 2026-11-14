# Controlled Academic Record Corrections

Status: implementation tested with synthetic accounts only. Independent
privacy, appeals and operational signoff remain required.

## Purpose and protected records

- Finalized classroom attendance statuses (approved registers)
- Published individual classroom assessment scores

The original attendance and grade entry endpoints remain locked after
submission. Corrections use a **separate, append-only application workflow**
which records the original value, proposed value, standardized reason code,
requester, reviewer, timestamps and final status.

No unrestricted grade editing, bulk overwrite, or silent field mutation is
provided to school users. The audit ledger deliberately records only
correction IDs and class IDs, never student names, marks or medical reasons.

## Workflow and authorization

1. The currently assigned MFA-enabled teacher requests a change for one
   finalized record within their class, using a limited reason code:
   recording_error, verified_excuse, reassessment or administrative_review.
2. The original published value is unchanged. The request is pending.
   Duplicate pending requests for the same record are blocked.
3. A **different** MFA-enabled authorized institution officer can approve
   or reject. The current source value and finalization status are
   rechecked under a row lock to avoid applying stale requests.
4. Approval records the reviewer and timestamp, updates only the targeted
   status/score and preserves the before/after snapshot. Rejection records
   the review but never updates the source.
5. Parent/student pages only read final values for their own linked students;
   they cannot retrieve the private correction queue or other families'
   before/after snapshots.
6. A previously approved/rejected request cannot be approved twice. A new
   correction, if needed, becomes a separate request with its own history.

Database composite foreign keys enforce class, campus and institution
agreement for correction targets, independently of HTTP validation.

## Limitations and mandatory release gates

This is append-only through current application endpoints, **not tamperproof
at the raw database level**. Administrators must restrict direct DB access,
apply retention, protect encrypted backups, and independently check audit
integrity. A future immutable/worm event store or signed audit exports
may be required under school policy.

Missing: student/guardian notification policy, formal appeals, correction
comment privacy, identity/custody evidence workflow, officer conflict-of-
interest rules beyond requester!=reviewer, bulk exports, policy-based
retention/deletion, and a verified operational restore and replay drill.

Use only synthetic test accounts until school data handling and independent
aaPanel/staging acceptance are signed off.
