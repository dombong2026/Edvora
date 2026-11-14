# Physical-device acceptance matrix

Status: PENDING: device access and test evidence required.

| Device | Browser | Core scenarios | Status |
|---|---|---|---|
| iPhone recent | Safari | Login, MFA, invitation, touch, themes | Pending |
| iPhone older supported | Safari | Forms, keyboard, accessible design | Pending |
| Android phone | Chrome | Login, MFA, campus denial, themes | Pending |
| Android phone | Firefox | Forms, session behavior, accessibility | Pending |
| iPad | Safari | Dashboard, portrait and landscape | Pending |
| Android tablet | Chrome | Navigation, inputs, responsiveness | Pending |
| macOS desktop | Safari, Chrome, Firefox | Navigation, focus, reduced motion | Pending |
| Windows desktop | Chrome, Edge | Authentication, themes, form validation | Pending |

Also test screen readers, keyboard-only navigation, 200% zoom, network
interruption, expired links, replayed MFA codes, logout/back-navigation, and
cross-institution denial.

For each scenario collect synthetic-account evidence: device, OS/browser
version, build SHA, date/time, tester, sanitized screenshot, expected/actual,
defect reference and signoff. Never capture real student records, passwords,
MFA seeds or backup recovery codes. No pass is inferred without physical QA.


Additional student/classroom scenarios to execute on every supported device:
- Institution officer: create synthetic student; link a verified student account.
- School officer: verify and revoke a synthetic guardian relationship.
- Parent/student: only explicitly linked student and enrolled class visible.
- Assigned teacher: authenticator setup and assigned class roster only.
- Unassigned teacher: class roster and student listing denied.
- Enrollment capacity rejection, duplicate enrollment, invalid campus/year.
- Portrait/landscape, narrow sidebar, 200% zoom, accessible validation errors.


Further academic module physical-device acceptance scenarios:
- Officer creates a weekly timetable; overlapping teacher or room times are rejected
- Teacher records draft attendance on phone; incomplete register cannot be submitted
- Independent officer approves attendance; parent sees only linked child's approved mark
- Assigned teacher records zero, decimal, and maximum assessment scores
- Publishing scores requires separate verified officer approval
- Student/guardian never sees classmates' attendance or score records
- Revoked parent permissions immediately deny previously published scores
- School timezone date boundary and time selector usability are manually checked


Correction review scenarios:
- Assigned teacher requests a corrected attendance mark after officer approval
- Officer sees the pending change and before/after snapshot and may approve or reject
- Student/guardian does not see pending corrections, only current approved value
- Assigned teacher requests a published score correction within the marking maximum
- Other staff/campus, suspended accounts and self-approval cannot change results
- On iPhone/Android, form labels, validation errors and independent decision actions are usable
