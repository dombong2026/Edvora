# Operational role workspaces — staging-only development

Five implemented server-backed workspaces share the existing Edvora shell and tenant isolation controls:

| Workspace | Authorized roles | Transactions |
|---|---|---|
| Library circulation | Campus librarian, scoped director or administrator | Add library items; issue and return loans with inventory locking |
| Fee proposals | Campus cashier, scoped director or administrator | Create draft amount templates; independently review or void. Not invoices, customer balances, payment acceptance or receipts |
| Reception desk | Campus receptionist, scoped director or administrator | Check visitors in and out; timestamps are server controlled |
| Admissions enquiries | Campus salesperson, scoped director or administrator | Record prospect and program; advance through reviewed contact stages. No email/SMS integration |
| Operations board | Campus supervisor/manager/dean/deputy director, scoped director or administrator | Add priority/date tasks; move open → in progress → resolved |

## Permission rules
- Every action requires authenticated user, **MFA**, active institution membership, institution-scoped role assignment and active, authorized campus.
- The platform administrator designation never bypasses tenant membership.
- Institution-wide roles are honored only where explicitly assigned at institution scope. Specialist roles must have matching campus assignment.
- The campus and institution are verified before any mutation; tables have matching composite foreign keys.
- All reads and updates include institution_id **and** campus_id; paginated search and activity lists are scoped before querying.
- Fee-proposal review must come from a different director or administrator than the creator. Reviewed proposals are **not** billing ledger entries.
- Library stock is serialized under an inventory lock; duplicate active loans to the same member are rejected and returns are idempotency-protected.
- Audit events include record IDs and module codes but never amounts, contact details, names or borrower identity.

## Publication and acceptance gates
- Development code and synthetic CI fixtures only. Do not use real visitors, students, prospects, payments or financial statements.
- Complete isolated aaPanel install and migrations/rollback, independent backup/encrypted restore, browser/physical device and accessibility acceptance, reviewer segregation checks, institutional privacy/retention approval, and written sign-off before merging or deploying.
- Real money collection, billing, messaging and external data import are disabled and out of scope.
