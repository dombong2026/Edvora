# Independent aaPanel staging acceptance

Status: NOT EXECUTED on the user's aaPanel server. No host access was supplied.
Production deployment remains prohibited.

Prerequisites: approved feature commit SHA, isolated staging app root, dedicated
Nginx vhost, PHP-FPM pool, MySQL database, APP_KEY, private media, mail sandbox,
and independently verified HTTPS certificate.

Read-only staging preflight example (replace placeholders only after confirming
actual authorized paths):

    bash support/qa/aapanel-staging-readonly-audit.sh \
      /www/wwwroot/<actual-edvora-staging-qa-directory> \
      /www/server/php/84/bin/php

Read-only HTTPS probe from an independent client:

    bash support/qa/https-staging-smoke.sh https://<real-edvora-staging-qa-host>

Neither script installs, migrates, restarts, modifies DNS, or deploys.
The second script requires HTTP 200 and security headers on the login route.

Separate authorized staging installation acceptance must verify:
- Composer and npm installation using committed lockfiles.
- MySQL migrations, rollback, database FK checks and role seed.
- First-admin interactive bootstrap, MFA enrollment and recovery code usage.
- Authorized invitation issuance, acceptance and expired/replayed link denial.
- Campus and institutional boundaries for every supported role.
- Theme, dashboard, password reset sandbox and mobile/browser behavior.
- Synthetic recurring timetable and room/teacher conflict checks; verify school timezone.
- Attendance drafts, class roster completeness, separate officer approval and lock-after-approval.
- Assessment scoring (including zero and decimal points), max-score validation,
  independent publication and lock-after-publication.
- Student/guardian visibility denial before publication and after guardian revocation.
- Storage permissions, HTTPS, Nginx/PHP-FPM, backup/restore and audit evidence.

The independent operator must record hostname, source SHA, vhost fingerprint,
PHP version, timestamp, and signed PASS/FAIL evidence. Synthetic CI passing
does not establish aaPanel acceptance.
