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
