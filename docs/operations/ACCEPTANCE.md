# Edvora v0.0.1 independent release acceptance

Status: NOT ACCEPTED FOR PRODUCTION.

Implemented source code:
- [x] Composer and npm dependency lockfiles.
- [x] TOTP MFA enrollment, second-factor challenge, recovery-code mechanisms.
- [x] Institution invitation issue and one-use redemption.
- [x] Read-only staging preflight and encrypted QA backup/restore scripts.
- [x] Automated PHP/MySQL/frontend test workflows.
- [x] Initial academic-year and subject catalog with institution authorization tests.
- [x] Student profile creation, student-user linking, guardian verification/revocation and scope tests.
- [x] Initial sections, teacher assignment, class activation and capacity-enforced enrollments.
- [x] Timezone-aware class timetable; room/teacher overlap checks on initial creation.
- [x] Teacher-only attendance drafts, independent approval, family-specific published reads.
- [x] Assessment drafts and decimal scoring, independent publication, family-specific published results.
- [x] Controlled timetable revision requests, independent officer review,
      conflict recheck on approval, and teacher reassignment overlap rejection.
- [x] Controlled attendance and grade correction requests, independent decision,
      original/new value snapshots, reason codes and scoped audit events.

Additional development implementation (not production acceptance):
- [x] Role-scoped library, cashier, reception, admissions and operations workspaces.
- [x] Private helpdesk and independently published campus announcements.
- [x] Student withdrawal, capacity-checked promotion and restricted unlinked transfer review.
- [x] Published academic results preview and private CSV download.
- [x] School-reviewed academic year statements with independent issue/revocation, immutable
      grade snapshots, SHA-256 integrity validation and private authorized downloads.
- [x] Campus exam scheduling, independent publication and room/time conflict guards.
- [x] Staff leave request/review, internal-only payroll planning, campus invoice register and
      manual bank-statement matching (NO payment transfer, bank receipt, or tax computation).
- [x] Internal private event inbox for academic statements and leave decisions;
      read/archive actions and idempotent recipient-scoped events.
- [x] Backup restore operator hardened to require checksum integrity and an existing
      disposable destination schema. This is CODE readiness, not restore acceptance.

Outstanding independent acceptance:
- [ ] Current head CI all green for PHP 8.3/8.4/8.5, MySQL 8, frontend.
- [ ] MFA risk review, verified locked-out account recovery, user-session tests.
- [ ] Invitation revocation, audit logging and real SMTP sandbox validation.
- [ ] Isolated aaPanel/Nginx/PHP-FPM staging installation.
- [ ] Database/rollback/backup/restore and retention checks (including checksum verification, full and incremental private media preservation, and restore evidence).
- [ ] Physical iPhone/Android/tablet and Safari/Chrome/Firefox testing.
- [ ] Verify real guardian authority, custody restrictions, retention and revocation processes.
- [ ] Independent campus cross-boundary and classroom roster read-denial review.
- [ ] Physical school attendance and score-review workflows and exception/appeal handling.
- [ ] Independent authorization/retention and correction-history privacy review.
- [ ] Real-world notification, custody and grievance handling for corrected records; external email/SMS/push remain disabled.
- [ ] Independent live-data teacher reassignment, concurrent timetable inserts,
      change-review conflicts and institutional timezone verification.
- [ ] School retention and privacy review for attendance, assessment scoring and audit metadata.
- [ ] School privacy and regional legal review.
- [ ] Owner signoff and a separate approved release plan.

Do not deploy to production, send live notices, import real school records,
or enable tuition payments. The attendance and grading features are narrowly scoped, not a complete SIS.
Full linked-campus transfers, legally certified academic transcripts, externally verified
payments/reconciliation, tax-compliant payroll, external messaging, HR contracts,
transport and end-to-end library administration remain separately accepted milestones. Real school/child data is still prohibited.
