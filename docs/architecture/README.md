# Edvora architecture

## Runtime

Modular Laravel 13 monolith with MySQL 8, server-rendered Blade/Tailwind and
vanilla JavaScript, web session authentication, and private media storage.
Redis/queues and additional ERP modules remain future extension points.

## Identity and permissions

One user identity may be a member of multiple institutions. Membership and
role assignment are institution-scoped, and optional campus assignment narrows
record access. Global platform admin designation is held separately in
platform_administrators, never as a tenant-level role.

Fifteen canonical role codes are defined by AccountRole enum; subdirector
means Deputy Director. Seeding creates role definitions, not fake accounts.

- InstitutionPolicy requires an active institution, membership and assigned role.
- CampusPolicy requires active campus and an assigned campus role OR an
  explicitly institution-wide leadership role.
- RoleAssignmentService allows director/administrator officers to grant
  institution roles, limits director grants to directors, and rejects
  platform-admin assignment through the institution service.
- Composite foreign keys prevent the membership/campus being assigned from
  an institution different from that of the role assignment.

The initial platform administrator is created interactively only on a
genuinely empty database, never from a default public password.

## Relational model

users -> institution_memberships -> institutions -> campuses
users -> institution_role_assignments -> roles (+ optional campus)
users -> platform_administrators (separate platform privileges)

Slugs do not grant access. Nested route bindings and Eloquent policy checks
must both apply; all future modules need server-side record ownership checks.
Initial accounts with no institution roles are restricted to a pending page.

## UI foundation

A shared Blade layout supplies the header, sidebar, theme switcher,
responsive cards, form controls and authorization-aware navigation.
The theme persists client-side and follows the OS when set to system.

## Planned domain modules

Admissions, students, guardians, academics, timetable, attendance,
assessments, fees, payroll, library, transport, facilities, welfare,
communication, documents, CMS, reporting and integrations are NOT included in
the foundation. Each future module requires its own acceptance tests.
