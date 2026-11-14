# Local and staging installation

1. Use a fresh isolated QA environment and pin the approved source SHA.
2. Confirm PHP >= 8.3 and required extensions; run the read-only
   support/qa/preflight.sh script.
3. Set Nginx document root to the project's public directory, never to root.
4. Create dedicated least-privilege MySQL 8 credentials; do not reuse other
   sites' keys, Redis prefixes or private storage.
5. Install dependencies from reviewed lockfiles. Dependency lockfiles are committed; use composer install and npm ci.
6. Create a private .env with APP_ENV=staging, APP_DEBUG=false, QA APP_URL,
   HTTPS and mail delivery disabled or in a sandbox.
7. Generate a unique APP_KEY only for a new environment.
8. Run php artisan migrate --force and seed the RoleSeeder definitions.
9. Build assets; confirm the Vite manifest exists before browser testing.
10. Run php artisan edvora:bootstrap interactively on a new empty database.
    It refuses to bootstrap existing accounts or institutions.
11. Test login, password reset in a mail sandbox, role restrictions,
    failed campus access, themes, browser behavior and backup restoration.
12. Maintain staging/production separation and a rollback plan.

This repository has no production deployment workflow. Do not import real
student data, activate billing, or enable bulk notifications in QA.

See docs/operations/ACCEPTANCE.md.
