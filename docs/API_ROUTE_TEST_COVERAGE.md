# API Route Test Coverage

GridPBX maintains a live route-to-feature-test registry in
`grid-api/tests/Feature/Support/ApiRouteFeatureCoverageTest.php`.

## Current inventory

- API routes: **218**
- Routes with direct or explicitly registered request-level evidence: **218**
- Reviewed feature-test gaps: **0**

The registry reads Laravel's actual route collection. It scans direct feature-test HTTP calls and
registers the small number of endpoints exercised through local endpoint helpers. A new route with
no test evidence fails the registry until it is tested or deliberately recorded as a reviewed gap.

`REVIEWED_GAPS` is currently empty. Every live API route has request-level feature-test evidence.
The registry fails when an untested route is introduced, or when a recorded test file is removed.

## Completion rule

A route leaves `REVIEWED_GAPS` only after a feature test calls the endpoint and verifies its
observable contract. Mutation tests must assert the response, persisted state, and external side
effects. Account-scoped routes must also cover authorization or tenant isolation in the owning
controller or policy suite.

This registry complements, rather than replaces, the request and field rules in
`docs/API_ENDPOINT_CONTRACT.md`.
