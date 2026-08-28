# Switch Form and Payload Audit

## Purpose

This audit prevents GridPBX forms from being declared complete after only
basic CRUD succeeds. It reconciles the current Switch schema, the legacy UI
workflow, actual create/edit traffic, and the new GridPBX contract for every
mutation-capable entity.

The current Switch schema and observed Crossbar behavior are authoritative.
Monster UI is evidence for workflow, conditional visibility, and established
defaults, but it may contain obsolete fields or omit newer schema fields.

## Evidence levels

| Evidence | Meaning |
| --- | --- |
| Schema | Current local Switch JSON schema and referenced schemas inspected |
| Legacy source | Monster templates, serializers, and conditional code inspected |
| Live options | Supporting Switch capability/options endpoint observed locally |
| Create | Actual create request, response `data`, and subsequent detail read captured |
| Edit | Actual edit request, response `data`, and subsequent detail read captured |
| Clear | Omit, replace, and explicit-clear behavior verified where supported |
| GridPBX | Switch client, Laravel API, Vue form, and focused tests implemented |
| Interactive | Legacy form behavior verified in an authenticated browser session |

Mocked tests prove GridPBX behavior but do not satisfy the Create, Edit, Clear,
or Interactive evidence levels.

## Capture rules

1. Use a dedicated local audit account and names prefixed with `GridPBX Audit`.
2. Capture the request `data`, mutation response `data`, and the immediate
   detail response `data` separately.
3. Remove auth tokens, API keys, passwords, PINs, hashes, signed URLs, storage
   credentials, and private CouchDB metadata before saving a fixture.
4. Preserve field presence, `null`, empty object/array, booleans, number types,
   array order, and unknown non-sensitive fields.
5. Record the entity type and every condition that changes visible controls.
6. Do not execute purchasing, porting, release, E911, CNAM, billing, carrier,
   or other chargeable/external actions during a form audit.
7. Store sanitized upstream fixtures under
   `grid-api-switch/tests/Fixtures/<Entity>/` and use them in boundary contract
   tests where practical.

## Per-field matrix

Every audited schema path records:

| Column | Required content |
| --- | --- |
| JSON path | Exact Switch path |
| Type/default | Schema types, enums, defaults, and accepted legacy variants |
| Form behavior | Create/edit visibility and controlling conditions |
| Write behavior | Omitted, supplied, and explicitly-cleared semantics |
| Relationship | Related entity lookup and public UUID mapping |
| Security | Editable, read-only, managed, write-only, conditional, or hidden |
| UI | GridPBX panel/tab and control type |
| Validation | Zod and Laravel rules |
| Switch boundary | DTO and resource-client mapping |
| Persistence | Normalized, application virtual, MySQL virtual, relationship, or streamed |
| Evidence | Fixture/test references and verification status |

## Persistence decision

- Normalize only identifiers, relationships, operational state, and fields
  required for SQL search, filtering, sorting, joins, aggregation, or reports.
- Keep the complete redacted response `data` object in `switch_json`.
- Use typed Laravel resource values/accessors as application virtual fields for
  nested display and edit properties.
- Add a MySQL virtual generated scalar only when a proven query/index use case
  exists. Do not mirror every JSON key into generated columns.
- Never expose raw `switch_json` to Vue and never send the stored snapshot
  wholesale back to Switch.

## Device pilot

### Form variants

The audit covers all eight supported types: `sip_device`, `cellphone`,
`smartphone`, `landline`, `softphone`, `fax`, `ata`, and `sip_uri`.

### Current evidence

| Area | Evidence | Status |
| --- | --- | --- |
| Type-dependent tabs and fields | Schema and legacy source | Implemented for all eight Device types with a focused rendered-tab matrix; interactive pending |
| Number classifiers | Live `GET /accounts/{id}/phone_numbers/classifiers` | Verified locally |
| Restrictions | `call_restriction.<classification>.action` schema plus legacy serializer | Implemented through typed Switch options, Laravel allowlist, Vue control, and focused tests; default live create verified, edit pending |
| Closed groups | Legacy `closed_groups` restriction mapping | Implemented as a bounded inherit/deny control; live create/edit pending |
| Existing nested configuration | Sanitized detail projection and focused contracts | Implemented; full live create/edit matrix pending |
| JSON-backed routing fields | Device, dialplans, metaflows, and custom SIP header schemas plus focused boundary contracts | Music-on-hold, outbound flags, SIP headers, dial-plan rules, and core metaflow settings implemented as validated application virtual fields; live matrix pending |
| Hotdesk state | Device schema and privacy boundary | Safe active-user count implemented; membership changes remain in the dedicated People & Extensions workflow |
| Secrets | Central redaction plus response assertions | Implemented |
| SIP Device create/edit/clear pilot | Sanitized local runtime capture | Verified for the audited fields; all temporary upstream records removed |

Basic and Advanced now follow the upstream workflow semantics: Basic hides the
detailed tab strip and shows the core form, while Advanced reveals a Basic tab
plus the tabs supported by the selected Device type. Recording and notification
controls remain available inside Options where supported, avoiding extra top-level
tabs that do not exist in the Kazoo workflow.

The live classifier response is captured in
`grid-api-switch/tests/Fixtures/PhoneNumbers/classifiers-response.json`. Only
the classifier key, friendly label, and emergency marker cross the Laravel API
boundary; matching regular expressions remain internal.

The first sanitized Device mutation capture is stored in
`grid-api-switch/tests/Fixtures/Devices/runtime-create-edit-clear.json`. It
confirmed that Device POST replaces omitted sections, `owner_id: null` is
invalid, unassignment requires removing `owner_id` from the preserved document,
and audited optional strings clear with an empty string rather than `null`.
Consequently, Device updates use a typed read-merge-write boundary that
preserves unknown fields and credentials in memory without exposing or storing
them unredacted.

### Remaining runtime matrix

For each Device type, capture a minimal create, a fully populated create, an
edit for every visible section, and explicit clearing of optional fields. Then
update `SWITCH_SCHEMA_PARITY.md` from the captured evidence and mark legacy
interactive behavior only after the authenticated browser pass succeeds.

## Delivery order

After Device, audit mutation-capable entities in dependency order:

1. User/Extension, Voicemail, Directory, and LineKey
2. Callflow, Group, Menu, Queue/Agent, Conference, and Temporal routing
3. Blacklist, Fax box, and Phone number management
4. Media and account configuration

CDRs, recordings, services, and system status use a read/display audit rather
than artificial create/edit operations.
