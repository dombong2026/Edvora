# Privileged MFA and scoped invitations

Implemented:
- TOTP: RFC 6238 SHA-1, six digits, 30-second periods, +/- one clock step.
- Encrypted per-user setup and active TOTP secrets; 15-minute enrollment expiry.
- Current password and a valid fresh TOTP required to enable MFA.
- Eight one-time recovery codes, 96-bit random values, SHA-256 stored at rest.
- Replay prevention using last accepted counter inside transactional row lock.
- Two-stage login challenge for MFA-enabled accounts with session regeneration.
- Privileged role dashboard access requires MFA setup.
- Invitations: random 256-bit URL token, SHA-256 hash in database, 48-hour
  expiry, one-time acceptance, institution/campus/role binding.
- Existing invitees sign in as same email; new invitees register via token.
- Suspended memberships cannot be silently reactivated.

Pending for production:
- Independent MFA threat model, session security, full account lockout recovery.
- Two-officer verified resets and privileged administrator audit trail.
- Invitation revocation/resend UI and immutable audit events.
- Sandbox SMTP delivery, phishing training, account-linked rate limits.
- Physical device accessibility and cross-browser acceptance.

Never log or transmit MFA seeds, private backup identity keys, passwords or
recovery codes. The current flow intentionally has no self-service MFA disable.
