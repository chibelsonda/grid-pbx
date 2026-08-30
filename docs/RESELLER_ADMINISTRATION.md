# Reseller Administration

## Status

Accepted for phased implementation. The current phase is read-only projection.
Promotion and demotion remain unavailable until the command, authorization,
preflight, audit, and verification controls in this document are implemented.

## Decision

GridPBX treats Switch account hierarchy, Switch reseller state, GridPBX access
roles, and GridPBX-owned commercial profiles as separate concepts.

| Concern | Source of truth | GridPBX responsibility |
| --- | --- | --- |
| Account parent and descendants | Switch account tree | Project and reconcile it for navigation and policy checks |
| Reseller status | Switch account/services state | Project `is_reseller`; never infer it from a GridPBX role |
| Billing reseller | Switch services summary | Project the upstream reseller reference separately from parentage |
| Operator access | GridPBX organization membership | Authorize access with GridPBX roles and explicit account scope |
| CRM, branding, and commercial metadata | GridPBX | Keep in a separate business bounded context |

The local `reseller_administrator` role grants GridPBX permissions only. It
does not promote an account in Switch and is not sufficient for Switch reseller
administration.

## Switch contract

Switch exposes reseller status as derived response data. It must not be written
through the ordinary account settings payload.

- `PUT /v2/accounts/{ACCOUNT_ID}/reseller` promotes an account.
- `DELETE /v2/accounts/{ACCOUNT_ID}/reseller` demotes an account.
- Both operations require an upstream `superduper_admin` token.
- Promotion and demotion reject the master account.
- Promotion and demotion reject unsafe trees containing reseller descendants.
- Promotion changes the reseller assignment of descendants.
- Demotion reassigns descendants to the next upstream reseller.

An account's parent and its billing reseller are not interchangeable. A
reseller account can itself be billed by an upstream reseller while serving as
the reseller for its own descendants.

GridPBX must never update Switch CouchDB documents directly or execute Switch
maintenance commands from the Laravel application.

## Projection model

The managed projection stores queryable status for accounts already assigned
to a GridPBX organization:

- `switch_accounts` stores the internal named bigint parent relationship,
  upstream parent identifier, `is_reseller`, `billing_mode`, descendant count,
  and hierarchy synchronization time;
- `switch_service_summaries` stores the separate internal billing-reseller
  relationship and upstream billing-reseller identifier;
- the complete redacted upstream account response in `switch_json`.

Internal bigint keys and upstream Switch identifiers are never exposed to the
UI. API resources use the existing public UUID `id` and resolve related public
UUIDs only when those related accounts are safely projected and accessible.

The read model must tolerate partial hierarchy projection. A missing local
parent or billing-reseller relationship is reported as unresolved rather than
invented from organization membership.

## Read-only phase

The initial implementation provides:

1. typed account reseller fields in `grid-api-switch`;
2. typed parent, ancestor, child, and descendant reads;
3. local projection of the managed account's reseller status;
4. reconciliation of related accounts that are already mapped in GridPBX;
5. authenticated hierarchy and reseller-status API resources;
6. explicit capability output showing that mutation is not yet available.

The authenticated read endpoints are:

- `GET /api/v1/accounts/{account}/hierarchy`
- `GET /api/v1/accounts/{account}/reseller`

Both require the existing service-view permission. The existing account refresh
projects hierarchy for reseller accounts; the service synchronization projects
the billing reseller independently.

Read requests may query unprojected Switch hierarchy entries, but raw Switch
account identifiers must not be returned by GridPBX public APIs. Unmapped
entries receive request-scoped opaque references only if a future UI needs to
display them; they cannot be mutation targets.

## Future mutation workflow

Promotion and demotion will be implemented as audited operations rather than an
`is_reseller` checkbox:

1. authorize a GridPBX platform administrator;
2. verify the upstream credential is `superduper_admin`;
3. run a preflight against master status, reseller descendants, service plans,
   limits, billing standing, and bookkeeper capability;
4. require an explicit action, reason, and confirmation;
5. acquire an account/subtree lock and idempotency key;
6. call the dedicated Switch reseller endpoint through `grid-api-switch`;
7. resynchronize the target and affected descendants;
8. verify the resulting reseller assignments;
9. record an immutable success, failure, or verification-failed audit result.

Remote calls and MySQL writes are not wrapped in a false distributed database
transaction. The workflow uses a durable operation record and retry-safe state
transitions. Failure after a successful Switch mutation triggers reconciliation
and operator review, not an automatic inverse billing mutation.

## Old GridPBX assessment

The legacy CRM tree is reference material only. It correctly recognized that
resellers have descendants, but its `is_reseller` checkbox updated only local
CRM state. It did not reliably call the dedicated Switch reseller endpoints.
Its generic account serializer also mixed derived response fields and local
database properties into ordinary Switch writes. GridPBX must not reproduce
that behavior.

## Verification before enabling mutations

Use disposable accounts on the connected local Switch to verify:

- direct-account creation under an explicit parent;
- promotion and descendant reassignment;
- rejection when reseller descendants make the operation unsafe;
- demotion and reassignment to the upstream reseller;
- GridPBX authorization and raw-ID non-disclosure;
- idempotent retry behavior and post-operation reconciliation.

No reseller mutation is enabled against a client deployment until these tests
pass and the client's billing/bookkeeper ownership is confirmed.
