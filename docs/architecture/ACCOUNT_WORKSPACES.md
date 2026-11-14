# Shared dashboard and account workspace

## Delivery scope
- Single responsive Blade/Tailwind shell for public pages, platform administrator, all 14 institution-scoped account roles, MFA, and the personal account center.
- Same header, responsive/collapsible navigation, light/dark/system appearance, focus states, action feedback and typography.
- Institution director/administrator member list is tenant-scoped, paginated and searchable; grants reuse audited role-authorization service, with campus mandatory for non-leadership roles.
- Server-computed, real routes only: institution overview, permitted student records, assigned classrooms, academic catalog for authorized officers, campus selector, role switcher, profile, MFA and recent actions.
- Platform dashboard shows only role catalog count, current user's authorized institution count, and security/configuration status; it **does not** list other schools, students, or cross-tenant statistics.
- Account center permits name changes and password rotation with current-password verification. Verified email and role assignments stay administrator-controlled.
- Activity list uses only security events where authenticated account is actor. It excludes sensitive metadata, student IDs, subject identities, and other users' records.
- Public registration remains governed by `EDVORA_SELF_REGISTRATION`; no new payment, email dispatch, student transfer, or production migration behavior.

## Authorization
Every institution link is calculated with `InstitutionAccess` and server-side academic/registry/classroom services. Navigation visibility is guidance, not an authorization mechanism. Existing route-level policy and tenant checks remain authoritative.

## Acceptance
Automated smoke tests render all 14 institution roles in the same account shell and include the platform admin console. Run PHPUnit suite, PHP static syntax checks, npm build, and MySQL migrations on isolated staging. Verify all 15 accounts in Chrome/Safari/Firefox and narrow-screen mobile with keyboard navigation, profile save, wrong/current password, theme persistence, sidebar show/hide, campus/role switching and outsider permissions. No production deployment or main-branch merge before explicit acceptance.
