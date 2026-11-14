# Edvora API status

No public writable API exists in Edvora v0.0.1. Candidate future contract:
/api/v1 with scoped tokens, institution/campus boundaries, rate limiting,
audit trails, safe pagination and idempotent financial mutations.

Current browser routes use session authentication and CSRF. Invitations
use confidential, one-use link tokens and are not REST APIs.
