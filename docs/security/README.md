# Edvora security baseline

School records can include sensitive child, guardian, staff and financial data.
Do not import real school records into CI or disposable QA environments.

## Implemented safeguards

- Hashed passwords and session regeneration on login/logout.
- CSRF middleware and throttling of sign-in, password reset and registration.
- Generic reset-link acknowledgement to prevent account enumeration.
- Public registration disabled by default; no role/membership auto-grants.
- Institution membership plus scoped campus authorization and composite FKs.
- No hard-coded admin account or default password; one-time interactive setup.
- Encrypted TOTP enrollment, privileged-account MFA gate and one-time recovery codes.
- Scoped institution invitations with 48-hour expiration and revocation.
- Allowlisted metadata in an internal security-event audit ledger.
- Separated platform admin rights: not a cross-tenant data bypass.
- Deployment secrets and application keys are environment-specific.

## Outstanding production blockers

- Independent administrator MFA and recovery review, verified locked-out
  identity-reset procedure, and review of sensitive data access audit coverage.
- Independent real-world custody/guardian identity and teacher appointment evidence.
- Reviewer signoff for parent link validation and student transfer/withdrawal workflows.
- Security reviews for exports, jobs, media, webhooks and every new module.
- Local privacy and education compliance review, pen test, retention policy.
- Backup encryption, restore rehearsals, key rotation and alerting.
- Independent hosting and mobile-device browser acceptance.

Access control MUST be checked on the server even if a UI link is hidden.
