# Edvora institution communication and public frontend

## Built in this milestone

- `/features`, `/roles`, `/security`, `/help`: guest-accessible, mobile-responsive, light/dark/system-styled informational pages. Public Help uses native disclosure widgets. Public registration remains invite-only by default.
- `/dashboard/institutions/{institution}/helpdesk`: private member ticket creation and conversations, status filtering, title search, pagination and thread close/reopen. Any authenticated active member with a campus assignment can create a ticket. Only its author and MFA-verified, institution-wide director/administrator can read/reply/close/reopen.
- `/dashboard/institutions/{institution}/announcements`: campus-specific or all-institution notices; only verified director/administrator can draft; a **different** eligible officer must publish. Only published notices are visible to non-officers in permitted campuses. Published notices may be archived (not re-published).
- Shared institution dashboard shows links to announcements and support for all roles, a scoped published-notice feed, and the current user's open-ticket count. Existing admin and role-specific modules remain unchanged.
- New records: `institution_help_tickets`, `institution_help_replies`, `institution_announcements`. Composite FK constraints prevent cross-campus ticket reply relationships, and each institution/campus query is scoped server side.
- Audit events record only IDs and the authorized campus; they do not log ticket bodies, phone numbers, messages or announcement content. No external mail, push/SMS, financial posting or student account notification is triggered.

## Security and release controls
1. Tenant membership **and** role assignment required to view any institution page. Account-specific campus access is checked before creating a ticket; staff-wide access requires both MFA and an institution-wide director/administrator role.
2. Non-officers never see draft/archived announcements. Campus-restricted announcements are not shown outside their authorized campus.
3. Ticket replies are rejected once closed; reopening and every state transition are recorded; normal member accounts cannot read tickets owned by another member.
4. Limits on subjects, message lengths and form throttles protect against accidental overcollection and abuse. Rendering uses escaped Blade output, not unrestricted HTML.
5. No external dispatch from the new support or notice workflows. Operators must approve privacy notices, retention/deletion policy, user notices, subject-access process and escalation responsibilities before using real school data.
6. **Remain DRAFT; do not deploy.** Isolated aaPanel/Nginx/PHP-FPM/MySQL installation, migrations and rollback, actual encrypted backup restoration, physical-device accessibility review, security/operational acceptance and owner sign-off still gate release.

## Still missing from a complete ERP
Live installation and service operations, enrollment lifecycle (transfers and promotions), exam/transcript generation, billing/payment ledger with reconciliation, account-specific advanced learning experiences, staff HR/payroll, library fines/acquisitions and retention workflows, institution-level reporting and production notification controls. Do not represent these as finished.
