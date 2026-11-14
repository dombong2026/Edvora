# Edvora ERP expansion: development branch delivery

**Branch:** `feature/edvora-v0.0.1-foundation`  
**PR:** https://github.com/dombong2026/Edvora/pull/1 (DRAFT)  
**Environment:** feature code and synthetic CI only. No production deploy, no real payments or messages.

## Delivered functions, not placeholders

| Module | Functional workflow | Security/transaction rules |
|---|---|---|
| Student lifecycle | Create and review promotions, withdrawals, and restricted campus transfers; automatic applied_at records; approved withdrawal deactivates enrolled classes; promotion inserts a real target enrollment under capacity lock | MFA officers, independent director/admin review, campus/tenant-scope FKs, no moving linked account/guardian/enrollment/invoice records |
| Academic results preview | Show and CSV-export only independently published assessment results for a verified student's own record | Authorized student/guardian or academic leadership only, no classmate information, CSV formula-injection prefix |
| Exam timetable | Teacher or academic officer creates draft, submits, independent school officer publishes or rejects; room/time conflicts checked before publication | Scoped class, active campus/year, institution lock for conflicts, published-only visibility to student/parent |
| Staff leave | Staff request future date range, independent leadership review or rejection, personal pending cancellation, overlap check | Staff campus assignment, private own/requester or director/admin view, MFA reviewer, school-scoped |
| Payroll planning | One staff + period draft, precise USD cents / KHR riel net amount, independent approve/void | Director/admin MFA only, staff membership/campus checks, no disbursement/filing/payslip |
| Internal billing | Draft from reviewed fee proposal and verified same-campus student; independent issue/void; manual reference claim reviewed by another officer | Cashier+MFA, leader review, exact integer minor money, duplicate reference/overmatch protection; **no payment processing or bank confirmation** |
| Internal notifications | Published school notices surfaced through authorized school feed, personal read/unread marker | POST authorization only, no viewing draft or cross-campus notices, no email/SMS/push |
| Shared UI | Consistent navigation, responsive light/dark, real forms, status transitions, audit events | No fake buttons, permission-checked server routes |

## Important limitations still outstanding

- **School transfer lifecycle**: Only unlinked students without guardian, enrollment or invoice records may transfer campuses automatically. Linked transfers require a dedicated permission/grade/history migration and independent sign-off.
- **Transcripts**: Result preview is not a certified transcript, award, diploma or signed PDF. Exam scheduling and assessment grades are distinct workspaces.
- **Financial operations**: Invoice register and independent manual statement review are not a bank ledger, a payment system, tax invoice, formal receipt, debtor aging engine or audited accounting reconciliation.
- **HR/payroll**: No salary disbursements, tax/NSSF rules, benefits, employee contracts, accrued leave entitlements or payslip issuance.
- **Communications**: No sending email/SMS/push or automated financial/customer notices; in-app published announcements and account read markers only.
- **Operational administration**: No automated school-year closeout, retention/deletion enforcement, institution-wide multi-campus transfers or institution-wide analytics warehouse.
- **Release acceptance**: No live installation or physical-device results were performed by this GitHub code change.

## Independent staging/production gate (MANDATORY)

1. Provision **isolated aaPanel staging** with dedicated MySQL database/user and PHP-FPM, env, queues, private files, TLS and backups. Never copy production secrets or user records to an insecure QA site. Verify dependency lockfiles and PHP extensions; use only signed reviewed source SHA.
2. Prove migrations up and down on disposable synthetic data, including composite campus FKs and unique index names. Record row and schema counts, make sure foreign-key constraints are active.
3. Complete independent encrypted backup and restore of isolated DB and private storage, checksum validation and disaster-recovery timing. No production storage overwrite.
4. Verify authorization matrix in browser and HTTP tests across **all 15 roles**, cross-institution/campus IDs, suspension, revocation, MFA, review independence, score visibility, stale forms, CSRF and rate limits.
5. Test keyboard/screen-reader, 320px/375px/768px/1280px breakpoints, iOS Safari, Android Chrome, desktop browsers, reduced motion, light/dark/system and translations.
6. Require school-specific PII retention policy, consent for guardian links and contacts, finance and HR confidentiality review, audit log access/retention and legal approval before using real student data.
7. Independently approve actual payment provider integration, reconciliation evidence, reversals and receipts, and independent notification transport before enabling them; all remain OFF.
8. Require signed acceptance, documented rollback and explicit deploy/merge permission. **Do not automatically merge PR #1 or deploy production.**

The security audit remains enabled in CI; transient advisory-provider timeouts must be rerun, not bypassed.
