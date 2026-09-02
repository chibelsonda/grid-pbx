# GridPBX API endpoint contract

This document defines what “covered and validated” means for a GridPBX API endpoint. Endpoint behavior belongs in Laravel feature tests; unit tests cover isolated services, policies, rules, DTO mapping, and validators.

## Required endpoint coverage

Every endpoint must have behavioral coverage for the cases that apply:

- unauthenticated access;
- insufficient organization role or policy ability;
- cross-account and cross-organization access;
- invalid route scope or missing projected resource;
- missing, malformed, out-of-range, prohibited, and incompatible input;
- successful response shape;
- database projection changes;
- Switch gateway calls, audit records, jobs, notifications, or other side effects;
- omission of numeric primary keys, private Switch identifiers, secrets, and raw `switch_json`.

Consolidated domain suites are allowed. A controller does not need a same-named test file when its behavior is clearly covered in another domain feature suite.

## Request-field contract

- Mutation payloads use a domain FormRequest and pass only `validated()` or `safe()` data onward.
- Query parameters that affect result size, filtering, streaming, or authorization have explicit validation.
- Bodyless commands and signed provider webhooks are documented exceptions.
- Unexpected fields are not forwarded to services or Switch clients.
- Cross-field and projection-aware rules belong in a FormRequest `after()` validator or a dedicated rule/service with behavioral tests.

The executable architecture checks are in `grid-api/tests/Feature/Support/ApiEndpointContractTest.php`.
The live, machine-readable route and field inventory is available with:

```bash
php artisan api:contract --json
php artisan api:contract --domain=Devices --domain=Extensions --json
php artisan api:contract --write=../docs/api-field-contract.json
```

The exporter reads Laravel's actual route collection and each routed FormRequest. It records public
field rules, middleware, declared response types, and API Resource serializers. An inspection error
makes the command fail instead of silently publishing an incomplete contract.

## Switch field completeness

GridPBX does not expose every Switch payload key directly. Supported public fields are validated and mapped explicitly; the complete sanitized source object remains in `switch_json`. A field should be promoted from JSON only when it is searchable, relational, operationally important, or required by a public API/UI workflow.

Field parity must therefore be evaluated per entity against:

1. the connected Switch schema/version;
2. the Switch/Kazoo workflow, including Basic and Advanced controls;
3. the GridPBX FormRequest and Switch DTO;
4. the API Resource response;
5. behavioral create, edit, clear, synchronization, and preservation tests.

Unsupported or version-specific fields must be preserved in `switch_json` and must not be silently deleted during an update.

## Change checklist

When adding or changing an endpoint:

1. Add or update its FormRequest and API Resource.
2. Add focused feature tests for the applicable coverage cases above.
3. Test every new validation decision through an HTTP request.
4. Assert persistence and external side effects for mutations.
5. Run `php artisan test --compact` for only the affected feature and unit suites.
6. Run Pint on the changed PHP files.

## Certification status and remaining work

The validation boundary and one-to-one live route/test registry are automated. The current route
inventory is documented in `docs/API_ROUTE_TEST_COVERAGE.md`.

Complete external field parity remains a per-entity activity because Switch schemas vary by
deployment and version. The contract exporter now publishes the supported public request fields and
response serialization boundaries. A generated OpenAPI document remains a later presentation layer;
full Switch-schema completeness must not be inferred from either route coverage or the public API
contract alone.
