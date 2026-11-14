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

Outstanding independent acceptance:
- [ ] Current head CI all green for PHP 8.3/8.4/8.5, MySQL 8, frontend.
- [ ] MFA risk review, verified locked-out account recovery, user-session tests.
- [ ] Invitation revocation, audit logging and real SMTP sandbox validation.
- [ ] Isolated aaPanel/Nginx/PHP-FPM staging installation.
- [ ] Database/rollback/backup/restore and retention checks.
- [ ] Physical iPhone/Android/tablet and Safari/Chrome/Firefox testing.
- [ ] Verify real guardian authority, custody restrictions, retention and revocation processes.
- [ ] Independent campus cross-boundary and classroom roster read-denial review.
- [ ] School privacy and regional legal review.
- [ ] Owner signoff and a separate approved release plan.

Do not deploy to production, send live notices, import real school records,
or enable tuition payments. Student and classroom modules remain limited foundations: full teaching,
student admissions, grading, finance, HR, transport and library operations
require separately accepted milestones. Real student data is still prohibited.
