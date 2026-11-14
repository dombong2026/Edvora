# Edvora API status

v0.0.1 does not implement a public writable API. This is a reserved future
contract, not a claim about available endpoints.

Future candidate: /api/v1 with scoped bearer tokens, explicit institution
and campus claims, request validation, audit trails, rate limits, safe
pagination and idempotency for financial mutations.

Current browser routes use Laravel session authentication and CSRF. No API
credentials, third-party webhook delivery or token issuance are enabled.
