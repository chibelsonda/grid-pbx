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

### Legacy Grid Device source comparison (2026-08-29)

The previous client application under `gridpbx-old/grid-ui` remains a
workflow reference only. This pass inspected its Device components and the
corresponding Laravel provisioning and line-key services. It did not import,
run, or modify the legacy repository.

The comparison uses this precedence when sources disagree:

1. the schema exposed by the connected Switch deployment;
2. observed Switch create, edit, clear, and operational behavior;
3. current upstream public schemas for version-aware additions;
4. the legacy Grid UI for client-specific workflow and terminology.

The legacy UI is not a complete Device contract. Its type selector only
implemented `sip_device`, `offnet_destination`, `ata`, and `fax`. It declared
type-specific tabs, but `visibleTabs` returned the full tab list before the
filtering code could execute. GridPBX's eight Device types and capability-based
tab visibility are therefore intentional corrections, not regressions.

| Area | Legacy Grid behavior | Current GridPBX behavior | Decision / gap |
| --- | --- | --- | --- |
| Device types | Four client-defined workflows | Eight Switch-backed workflows | Keep the current eight-type capability matrix |
| Basic assignment | Name, owner, enabled, MAC or forwarded number | Same core relationships with public UUIDs and conditional fields | Keep current implementation |
| Notify when unregistered | Control is commented out even though the form initializes a local `extra.notify_unregister` value | Active positive-worded control inversely maps to `suppress_unregister_notifications` | Current behavior is the schema-backed correction |
| Tabs | General, Caller ID, SIP, Audio, Video, Options, Restrictions; filtering is unreachable | Basic/Advanced presentation with type-supported tabs | Keep current conditional tabs |
| Caller ID | External and E911-capable emergency selectors; internal/presence controls are commented out | Internal, external, emergency, and asserted identities with account-owned choices and E911 enforcement | Keep current schema-backed superset |
| SIP credentials | Server-generated username and password buttons plus password/IP auth | Write-only username/password fields with stronger validation, but no guided generate/rotate control | Add secure generate/rotate workflow; never return an existing password |
| Audio/video | Fixed codec lists | Ordered codec editor with the legacy values plus connected-schema compatibility | Keep current ordered controls |
| Options | Ringtones, T.38, forwarding flags, contact-list exclusion, and ignore-completed-elsewhere | Same behaviors plus current schema-backed routing, locale, recording, formatter, metaflow, and notification controls | Keep current superset and type conditions |
| Restrictions | Seven hard-coded classifiers with Inherit/Allow/Deny and a separate `extra.closed_groups` flag | Connected classifier discovery with current-schema Inherit/Deny and bounded closed-group behavior | Do not restore `allow` unless the connected schema advertises it |
| Provisioning catalog | MySQL `prov_brand`, family, and model records; family is implicit in the selected model | Authenticated external `/api/phones` catalog with explicit brand/family/model and Zod/Laravel branch validation | Support provider adapters; do not make legacy MySQL tables the universal contract |
| Provisioning model metadata | Model supplies main key count, expansion-module count, and keys per module | Catalog DTO and API now expose bounded optional capacities, supported key types, safe value-source identifiers, and manufacturer provider | Completed at the contract boundary; real-provider values still require client access |
| Provisioning selection on edit | Brand and model are locked after create | Values may be changed or cleared and followed by explicit sync/reprovision | Keep mutability, but require confirmation, audit, and provider-safe compensation |
| Vendor ZTP | Create calls manufacturer APIs for Polycom, Yealink, Grandstream, or Snom and creates local provisioner credentials | Catalog discovery and Switch sync/reprovision exist; manufacturer enrollment is not implemented | Real client provider/ZTP adapters remain required |
| Line keys | Model-sized main panel plus expansion-module panels; values come from model-specific metadata | Typed Switch replacement now groups main/expansion sections, enforces model capacity/types, and provides account-scoped suggestions through fixed API providers | Real provider metadata and live physical-phone verification remain |
| Validation/security | Several nested payloads receive shallow validation; line-key option sources can be stored as executable SQL; provisioning passwords are readable application data | Zod, Laravel, DTO allowlists, secret redaction, and public-ID boundaries | Do not copy the legacy validation, executable SQL, or plaintext-secret design |

#### Legacy provisioning behavior that must be preserved as requirements

The old application performed more than catalog discovery when a hardware
Device was created. It resolved public brand/model identifiers to local
provisioning records, created the Device in Switch, projected it into MySQL,
registered supported manufacturers with their ZTP provider, and created a
device-scoped provisioner identity. On failure it attempted to compensate by
removing the Switch and MySQL Device.

That workflow explains why the client modeled brand, family, model, MAC, line
capacities, and manufacturer integration separately. The goal was to drive
physical-phone enrollment and configuration, not merely decorate a Switch
Device record. GridPBX should preserve this business capability through typed
provider adapters and an observable orchestration service. It should not copy
the old implementation's plaintext provisioning password, create-only vendor
registration, or assumption that a database transaction can cover external
Switch and vendor calls.

#### Provisioning and Device parity delivery plan

1. **Confirm the provider contract.** Obtain the client's actual catalog,
   configuration-server, and manufacturer ZTP endpoints. Record authentication,
   tenant scope, supported manufacturers, request/response examples, retry
   rules, and whether OpenTelecom `/api/phones` or the legacy MySQL catalog is
   still authoritative.
2. **Expand the safe catalog DTO.** Add optional model capabilities such as
   `max_keys`, `max_expansion_modules`, `keys_per_expansion_module`, supported
   key types, safe value-source identifiers, template ID, and manufacturer
   provider. Unknown provider properties remain behind the adapter boundary.
3. **Enforce provisioning identity.** Canonicalize MAC addresses, add the
   provider-appropriate account/global uniqueness rule, and validate that the
   selected brand/family/model combination belongs to the same catalog tree.
4. **Add orchestration state.** Track provider, status, last attempt, last
   success, sanitized failure, and idempotency key. Device create/update/delete,
   vendor enrollment, credential rotation, sync, and reprovision use explicit
   compensating steps and retry-safe commands.
5. **Add provider adapters.** Implement only the client-enabled providers
   behind a common interface. Secrets stay server-side and encrypted or in the
   deployment secret store; logs and `switch_json` remain redacted.
6. **Complete the hardware UI.** Make line-key slots and expansion modules
   model-driven, populate values from allowlisted resource providers, show
   provisioning state, and add confirmed enroll, sync, reprovision, detach, and
   credential-rotation operations in right-side panels.
7. **Verify the lifecycle.** For each enabled manufacturer, run focused
   catalog, MAC assignment, create, edit, clear/detach, sync, reprovision,
   retry, and compensation tests. Physical-device checks are performed only in
   a client-approved test tenant with non-production hardware.

The first implementation priority is steps 1–3. Manufacturer enrollment cannot
be safely implemented from the legacy source alone because the repository does
not contain the client's current URLs, credentials, tenant identifiers, or
provider account configuration.

### Form variants

The audit covers all eight supported types: `sip_device`, `cellphone`,
`smartphone`, `landline`, `softphone`, `fax`, `ata`, and `sip_uri`.

### Current evidence

| Area | Evidence | Status |
| --- | --- | --- |
| Type-dependent tabs and fields | Current schema, legacy source, and authenticated side-by-side browser walkthrough | Implemented and interactively verified for all eight Device types |
| SIP URI workflow | Legacy form semantics, Device schema, focused Vue/API contracts, and live disposable lifecycle | Basic exposes the required route; Options exposes only contact-list visibility; create/edit/clear uses a minimal Switch payload and removes the temporary record |
| Cellphone and Landline workflows | Legacy templates, current Device schema, focused Vue/API contracts, and live disposable lifecycle | Basic enabled state is synchronized with forwarding; Kazoo-primary Options remain visible; four current-schema extensions are grouped under Advanced forwarding; create/edit/disable-clear verified for both types |
| Registered endpoint capabilities | Legacy templates, current schema, payload-capability tests, browser matrix, and disposable live lifecycle | T.38 is shown for SIP Device, Softphone, Fax, and ATA; Ignore completed elsewhere is limited to SIP Device and Softphone; the required `fax` outbound flag is preserved; create/edit/clear verified across all five registered endpoint types |
| Version compatibility | Live `GET /v2/schemas/devices` plus current upstream Device schema | API publishes a safe matrix; Vue, Zod, Laravel, and DTO payloads conditionally support current SIP/provisioning fields, dynamic forwarding limits, and legacy `check_sync_*` fields |
| Number classifiers | Live `GET /accounts/{id}/phone_numbers/classifiers` | Verified locally |
| Restrictions | `call_restriction.<classification>.action` schema plus legacy serializer | Implemented through typed Switch options, Laravel allowlist, Vue control, and focused tests; create/edit/reset-to-inherit verified live for every applicable type |
| Closed groups | Legacy `closed_groups` restriction mapping | Implemented as a bounded inherit/deny control; create/edit/reset-to-inherit verified live |
| Existing nested configuration | Sanitized detail projection and focused contracts | Implemented; full live create/edit matrix pending |
| JSON-backed routing fields | Device, dialplans, metaflows, and custom SIP header schemas plus focused boundary contracts | Music-on-hold, outbound flags, SIP headers, dial-plan rules, and core metaflow settings implemented for registered endpoint types; forwarding and SIP URI types omit unrelated payload sections |
| General flags and formatters | Device and formatter schemas plus live Switch lifecycle | Typed virtual fields, Zod/Laravel validation, Headless UI editor, and safe replacement implemented for registered endpoint types |
| Provisioning events and commands | Device provision schema and `/devices/{id}/sync` | Event settings verified for SIP Device, Fax, and ATA; reload and reboot commands verified on disposable unregistered devices and exposed as audited capability-gated actions |
| Caller ID and E911 | Device schema plus account phone-number projection | External caller ID is account-owned; emergency choices require projected E911 capability in both Vue and Laravel; create/edit/clear verified live |
| Provisioning model discovery | Monster-compatible provisioner `/api/phones` catalog | Typed discovery is implemented. Compose supplies a discovery-only local catalog with Cisco, Grandstream, Poly, and Yealink examples; `SWITCH_PROVISIONER_URL` can replace it with a real provisioner API root. Manual hardware values remain the failure fallback |
| Line keys | Device `provision.combo_keys` and `feature_keys` | All five schema types implemented; full replacement uses read-modify-POST because live `PATCH` merges old keys; create/edit/clear verified live |
| Guided metaflows | Metaflow and module schemas | Supported recursive trees have a guided editor; media, callflow, device, and extension references use public UUID selectors; unsupported trees are locked and preserved; root replacement and clear verified live |
| Hotdesk state | Device document `hotdesk.users` and privacy boundary | Dedicated audited sign-in/sign-out controls use public extension UUIDs; unprojected active users are counted without exposing Switch IDs |
| Secrets | Central redaction plus response assertions | Implemented |
| SIP Device create/edit/clear pilot | Sanitized local runtime capture | Verified for the audited fields; all temporary upstream records removed |

Basic and Advanced now follow the upstream workflow semantics: Basic hides the
detailed tab strip and shows the core form, while Advanced reveals a Basic tab
plus the tabs supported by the selected Device type. Recording and notification
controls remain available inside Options where supported, avoiding extra top-level
tabs that do not exist in the Kazoo workflow.

The live Monster form and its source define the familiar tab composition, but
the current Switch schema remains authoritative for fields and allowed values.
This creates several intentional differences: GridPBX exposes newer schema-backed
call waiting, DND, locale, recording, routing, formatter, metaflow, and
provisioning controls inside the established tabs; it uses the schema default of
300 seconds for SIP expiry; it supports both SRTP and ZRTP methods; and its
restriction actions are limited to the current schema values `inherit` and
`deny`. Monster's legacy `allow` restriction option and `media.webrtc` field are
not copied because they are absent from the current schema. Credential policy is
also intentionally stricter, with a 12-character minimum. These are compatibility
and security decisions, not missing form controls.

One non-intentional mismatch found during the browser comparison was corrected:
Device names are now limited to the schema's 128 characters in both Zod and
Laravel instead of accepting 255 characters.

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

The expanded runtime matrix is stored in
`grid-api-switch/tests/Fixtures/Devices/runtime-routing-fields.json`. It
verifies routing fields, caller-ID create/edit/clear behavior, restriction
create/edit/reset behavior, exact audio/video codec order, ringtone
create/edit/clear behavior, guided metaflow action replacement, general flags,
formatter replacement, and provisioning event configuration for every
applicable Device type. It also verifies all five
line-key schema types plus full replacement and clear behavior on a disposable
SIP Device. This deployment accepts
outbound flags as a flat array (the object-shaped schema variant was silently
dropped), restores metaflow `binding_digit` to `*` on clear, and represents
cleared provisioning event strings as `""`; Laravel normalizes those empty
wire values to `null` for Vue. Both non-rebooting reload and reboot sync
commands succeeded against temporary unregistered provisionable devices. All
temporary Devices and Media were removed.

### Remaining runtime matrix

The authenticated browser pass verifies the type selector and visible
Basic/Advanced tab matrix for all eight Device types against Monster. Runtime
audits cover creation and the fields applicable to each workflow rather than
using the generic Device schema as permission to show every field everywhere.
The dedicated SIP URI audit verifies its minimal write shape and live
create/edit/contact-list-clear lifecycle. Dedicated forwarding and registered
endpoint audits verify their field-capability matrices and remove every
temporary record. The local schema correctly keeps newer controls hidden,
retains the legacy provisioning-event controls, and enforces the 15-character
forwarding limit. Disposable authenticated lifecycle verification now passes
for Device hotdesk sign-in/sign-out, recursive resource-linked metaflow
create/edit/clear, User hotdesk profile create/edit/PIN-preserve/clear, and
User portal credentials create/unchanged-password omission/forced-update/clear.
Remaining runtime work is connecting a real external provisioner catalog.

## Delivery order

After Device, audit mutation-capable entities in dependency order:

1. User/Extension, Voicemail, Directory, and LineKey
2. Callflow, Group, Menu, Queue/Agent, Conference, and Temporal routing
3. Blacklist, Fax box, and Phone number management
4. Media and account configuration

CDRs, recordings, services, and system status use a read/display audit rather
than artificial create/edit operations.
