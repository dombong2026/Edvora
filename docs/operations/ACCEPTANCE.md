# v0.0.1 independent release acceptance

Status: NOT ACCEPTED FOR PRODUCTION.

Required before merging or deploying:

- [ ] Review and commit reproducible composer.lock and package-lock.json.
- [ ] All PHP 8.3 / 8.4 / 8.5, MySQL 8 and frontend GitHub CI jobs pass.
- [ ] Independent role, tenant isolation and authorization denial QA passes.
- [ ] MySQL 8 FK integrity, forward/backward migration and restore tests pass.
- [ ] Privileged MFA and invited-account recovery plan accepted.
- [ ] Password reset email sandbox tested without account enumeration.
- [ ] Physical aaPanel, Nginx, PHP-FPM and HTTPS staging tests accepted.
- [ ] iPhone, Android, tablet, Safari, Chrome and Firefox tests accepted.
- [ ] Backups, key handling, upgrades and rollback independently tested.
- [ ] Privacy, education and applicable legal requirements reviewed.
- [ ] Owner explicitly authorizes any production release.

Foundation scope excludes student records, admissions, fee operations,
gradebooks, HR, library circulation and parent messaging. Do not interpret
a draft PR or a passing synthetic test as production acceptance.
