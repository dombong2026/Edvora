# Edvora — School Management & Education ERP

**Version:** 0.0.1 (foundation, not production-ready)
**Author:** Ton Samith — https://tonsamith.com
**Project URL:** https://edvora.bambodia.com
**License:** Proprietary Commercial License; see [LICENSE](LICENSE).

Edvora is a modular Laravel 13 school-management foundation with shared login, 15 account roles, institution and campus boundaries, initial administrative dashboards, and persistent light/dark/system appearance controls.

> **Release safeguard:** This feature branch requires independent review and acceptance before merge or production deployment. The source does not contain real school records, live payment integrations, or deployment credentials.

## Requirements

PHP 8.3+ with Laravel-required extensions, MySQL 8, Composer 2, Node.js 22+, npm, HTTPS and Nginx for hosting. SQLite is used by isolated CI tests.

## Local / disposable QA setup only

1. Install exact PHP dependencies using composer install; do not update dependencies implicitly.
2. Copy .env.example to .env; configure a **disposable** local MySQL database and never commit .env.
3. Run php artisan key:generate.
4. Run php artisan migrate and php artisan db:seed --class=RoleSeeder.
5. Run npm ci --ignore-scripts, then npm run build.
6. Run php artisan serve for local evaluation.
7. On a **fresh, empty** installation only, run php artisan edvora:bootstrap interactively to create the initial institution and privileged administrator.

Do not execute installation commands against production school records without an approved migration and rollback plan.

## Implemented in v0.0.1 foundation

- Laravel session authentication, sign-out, password reset, enforced privileged MFA and single-use recovery codes, and registration disabled by default.
- Fifteen role codes: admin, teacher, student, librarian, parent, supervisor, manager, dean, professor, administrator, receptionist, cashier, director, subdirector and salesperson.
- Separate platform administrator designation, institution memberships and role assignments.
- Institution/campus boundaries, composite foreign keys, policies, audited privileged grants, and a secure role-assignment service.
- Secure email-bound institution invitations with one-time acceptance, revocation and audit events.
- Institution-scoped academic years and subject catalog with leadership-only creation and validation.
- Guarded aaPanel staging preflight, HTTPS checks and AGE-encrypted QA backup/restore rehearsal scripts.
- Responsive premium dashboard, with persistent light, dark and system appearance.
- Empty states and real authorized dashboard data; no fake records.

**Not yet shipped:** full admissions, learning, grades, finance, HR, libraries, account lockout recovery, approved mail delivery, and production integrations. This is not yet a complete school ERP.

## Documentation

- [Architecture](docs/architecture/README.md)
- [Installation and QA](docs/installation/README.md)
- [Security](docs/security/README.md)
- [API status](docs/api/README.md)
- [Acceptance gates](docs/operations/ACCEPTANCE.md)
- [Independent staging QA](docs/operations/STAGING_ACCEPTANCE.md)
- [Physical-device QA](docs/operations/DEVICE_ACCEPTANCE.md)
- [Encrypted backup/restore](docs/operations/RECOVERY.md)
- [MFA and invitation risk controls](docs/security/MFA_AND_INVITATIONS.md)
- [Third-party notices](THIRD_PARTY_NOTICES.md)

## Development process

Feature branches and draft pull requests target main. CI only tests; it does not deploy. Production use requires aaPanel, Nginx, PHP-FPM, MySQL, mobile browser and independent authorization/security acceptance.
