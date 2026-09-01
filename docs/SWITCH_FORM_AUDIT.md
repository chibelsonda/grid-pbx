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

## Shared form acceptance baseline

The Device pilot established a UI and validation baseline that applies to every
mutation-capable entity. Reusing the visual treatment does not mean using one
generic form or exposing every schema field for every workflow.

1. Create and edit workflows open in a right-side slideover unless the entity
   needs a dedicated visual editor, upload surface, or operational console.
2. Zod owns immediate client validation and Laravel repeats all trust-boundary
   validation. The two contracts must agree on required values, lengths,
   enums, conditional fields, and clear semantics.
3. Every invalid text input, textarea, listbox, combobox, switch container, and
   compound editor receives the shared red invalid border/focus treatment and
   `aria-invalid`. Its message is displayed next to the control.
4. Client validation does not add a duplicate top or bottom summary alert.
   Global alerts are reserved for server, authorization, connectivity, and
   Switch mutation failures that cannot be assigned to a field.
5. Select popovers render above their card and remain fully visible inside a
   scrolling slideover. Labels, helper text, placeholders, and ordinary borders
   use the approved readable neutral palette.
6. Headless UI primitives and shared form helpers are preferred for interactive
   controls. Entity-owned components retain domain-specific conditional logic.
   Native controls are encapsulated by `FormInput`, `FormTextarea`,
   `SearchInput`, `FormFileInput`, and `FormCheckbox` so label association,
   descriptions, native attributes, model modifiers, `aria-describedby`, and
   red invalid styling do not drift between domains.
7. Related records are selected through account-scoped public UUID options.
   Database primary keys and Switch resource identifiers never cross into Vue.
8. Secrets are write-only. Edit forms show configured-state metadata and
   explicit preserve, rotate, or clear operations instead of returning a saved
   secret.
9. Schema-supported fields are shown only in a useful, authorized workflow.
   Capability-gated, operational, unsafe, obsolete, and unsupported fields are
   conditionally shown, moved to a dedicated command, or explicitly documented
   as excluded.
10. Focused component/contract tests cover invalid styling and conditional
    visibility. Isolated headless Playwright covers clipping, keyboard behavior,
    and console/network errors without taking control of the desktop pointer.
11. Every entity audit covers both the legacy Basic and Advanced workflows.
    Each visible or schema-backed field must be classified as implemented,
    conditionally available, read-only, safely preserved, obsolete, or
    intentionally excluded; a passing Basic CRUD path never completes the audit.

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
| Options | Ringtones, T.38, forwarding flags, contact-list exclusion, and ignore-completed-elsewhere | Matches the Kazoo per-type Options workflow; broader schema values remain typed and preserved without becoming generic browser controls | Keep the audited Kazoo presentation matrix and preserve non-presented JSON values on edit |
| Restrictions | Seven hard-coded classifiers with Inherit/Allow/Deny and a separate `extra.closed_groups` flag | Connected classifier discovery with current-schema Inherit/Deny and bounded closed-group behavior | Do not restore `allow` unless the connected schema advertises it |
| Provisioning catalog | MySQL `prov_brand`, family, and model records; family is implicit in the selected model | Authenticated external `/api/phones` catalog with explicit brand/family/model and Zod/Laravel branch validation | Support provider adapters; do not make legacy MySQL tables the universal contract |
| Provisioning model metadata | Model supplies main key count, expansion-module count, and keys per module | Catalog DTO and API now expose bounded optional capacities, supported key types, safe value-source identifiers, and manufacturer provider | Completed at the contract boundary; real-provider values still require client access |
| Provisioning selection on edit | Brand and model are locked after create | Values may be changed or cleared and followed by explicit sync/reprovision | Keep mutability, but require confirmation, audit, and provider-safe compensation |
| Vendor ZTP | Create calls manufacturer APIs for Polycom, Yealink, Grandstream, or Snom and creates local provisioner credentials | Catalog discovery and Switch sync/reprovision exist; manufacturer enrollment is not implemented | Real client provider/ZTP adapters remain required |
| Line keys | Model-sized main panel plus expansion-module panels; values come from model-specific metadata | Typed Switch replacement now lists only provisionable physical Device types, groups main/expansion sections, enforces model capacity/types, and provides account-scoped suggestions through fixed API providers | Softphones and forwarding-only endpoints are excluded; real provider metadata and live physical-phone verification remain |
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
| Cellphone and Landline workflows | Legacy templates, current Device schema, focused Vue/API contracts, and live disposable lifecycle | Basic enabled state is synchronized with forwarding; Options exposes require-keypress, caller-ID retention, and contact-list visibility; additional schema values are preserved without expanding the Kazoo form; create/edit/disable-clear verified for both types |
| Registered endpoint capabilities | Legacy templates, current schema, payload-capability tests, browser matrix, and disposable live lifecycle | T.38 is shown for SIP Device, Softphone, Fax, and ATA; Ignore completed elsewhere is limited to SIP Device and Softphone; the required `fax` outbound flag is preserved; create/edit/clear verified across all five registered endpoint types |
| Version compatibility | Live `GET /v2/schemas/devices` plus current upstream Device schema | API publishes a safe matrix; Vue, Zod, Laravel, and DTO payloads conditionally support current SIP/provisioning fields, dynamic forwarding limits, and legacy `check_sync_*` fields |
| Number classifiers | Live `GET /accounts/{id}/phone_numbers/classifiers` | Verified locally |
| Restrictions | `call_restriction.<classification>.action` schema plus legacy serializer | Implemented through typed Switch options, Laravel allowlist, Vue control, and focused tests; create/edit/reset-to-inherit verified live for every applicable type |
| Closed groups | Legacy `closed_groups` restriction mapping | Implemented as a bounded inherit/deny control; create/edit/reset-to-inherit verified live |
| Existing nested configuration | Sanitized detail projection, focused contracts, and disposable lifecycle checks | Implemented; applicable create/edit/clear workflows are live verified |
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

### Device Advanced-tab presentation correction (2026-09-01)

The generic Device schema accepts considerably more data than Kazoo presents
in each Device workflow. Rendering every accepted schema group in Options was
a presentation regression: it exposed endpoint behavior, recording,
locale/notification, routing, formatter, metaflow, and SIP-header editors that
do not appear in the corresponding Kazoo Options tab.

The Options tab now follows the audited per-type Kazoo matrix. SIP Device shows
ringtone headers, T.38, contact-list visibility, and
ignore-completed-elsewhere; Softphone omits only the ringtone fields; Fax and
ATA show T.38 plus contact-list visibility; forwarding devices show
require-keypress, caller-ID retention, and contact-list visibility; SIP URI
shows contact-list visibility only. The broader typed configuration, Zod
contract, Laravel request, Switch DTOs, and unknown-field-preserving write
boundary remain available so non-presented values survive ordinary edits.
Music on hold stays in Audio; schema-only routing flags and nested settings
remain protected by the typed payload and read-merge-write boundary.

Public references remain account-scoped UUIDs. Media, Callflow, Device, and
Extension selections are resolved to raw Switch identifiers only in Laravel,
and neither those identifiers nor internal database keys are added to the form
payload or response. Existing SDK and API focused tests continue to prove the
read-merge-write preservation boundary for unknown Switch properties. A new
reactive-metaflow regression test also prevents Vue proxy objects from reaching
the plain request payload.

Focused verification for the presentation correction passed 53 Device Vue
tests across the Options capability matrix, form helpers, and payload builder,
plus Vue TypeScript checking, E2E TypeScript checking, targeted lint, and three
isolated headless workflow checks covering SIP URI, forwarding devices, and
registered endpoints. Live mutation was not repeated because this correction
changes visibility only; existing payload-boundary tests continue to cover
preserved schema values.

Basic and Advanced now follow the upstream workflow semantics: Basic hides the
detailed tab strip and shows the core form, while Advanced reveals a Basic tab
plus the tabs supported by the selected Device type. Options follows Kazoo's
per-type controls instead of adding generic recording, notification, or routing
sections that do not exist in that workflow.

A final per-type presentation comparison corrected stale UI capability entries:
Smartphone again exposes Caller ID, Audio, and Video inside Advanced, while Fax
and ATA again expose Audio. Monster includes those sections, the installed
generic Device schema validates their Caller ID and endpoint-media fields, and
the installed Crossbar Device path does not reject them by `device_type`.
GridPBX keeps connected-schema SIP compatibility in the SIP tab while retaining
the Kazoo Options presentation matrix.

The live Monster form and its source define the familiar tab composition, while
the current Switch schema remains authoritative for validation, allowed values,
and transport preservation. Intentional differences remain where the current
schema is explicit: GridPBX uses the 300-second SIP expiry default, supports
both SRTP and ZRTP methods, and limits restriction actions to `inherit` and
`deny`. Monster's legacy `allow` restriction option and `media.webrtc` field are
not copied because they are absent from the current schema. Credential policy
is also intentionally stricter, with a 12-character minimum. Schema values that
are not part of Kazoo's Device form remain preserved rather than appearing as
extra generic Options controls.

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

Directional custom SIP headers now also have a focused UI-driven lifecycle
against the connected Switch. One disposable SIP Device was created with
separate inbound and outbound headers, reopened, updated with replacement
values and a renamed outbound header, reopened again, explicitly cleared in
both directions, and deleted. The public response exposed only the bounded
name/value rows; write-only SIP credentials were not asserted or returned.
The isolated headless case passed, and an independent projection count
confirmed that no active matching Device remained afterward.

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

## User/Extension and Voicemail audit checkpoint (2026-08-29)

This is the first Wave 2 audit. It compares the connected `users` and
`vmboxes` schemas, their referenced schemas, current Monster workflows, and the
existing Vue, Laravel, and `grid-api-switch` implementations. No form changes
were made as part of this checkpoint.

### User/Extension findings

The People & Extensions workflow intentionally treats User as the aggregate
root for an optional managed Device, Voicemail Box, and Callflow. That workflow
is useful, but each nested object still keeps its own schema and dedicated
full editor.

| Area | Current evidence | Decision / required remediation |
| --- | --- | --- |
| Core identity validation | First name, last name, extension, email, and timezone show inline messages but do not consistently apply the shared invalid border or `aria-invalid` | Apply the shared validation-control helper and remove the duplicate client-validation summary alert from create and edit |
| User options | Language and presence ID expose `aria-invalid` but not the invalid border; switches have no shared invalid container treatment | Use the same control helper for inputs and switch containers |
| Credentials and hotdesk | Typed, write-only secret handling and live preserve/clear behavior are implemented; several controls duplicate hard-coded invalid classes | Preserve the security contract and replace duplicated styling with the shared helper |
| Language, timezone, and presence | Current fields are free text; Monster used account-derived selections for the values it knew | Prefer capability/account-backed listboxes or comboboxes while preserving an existing unprojected value during edit |
| Initial Device during create | Kazoo treats Device creation as a related User workflow, while the former starter payload exposed only a duplicated subset of Device fields | Reuse the Device domain's complete capability-driven Basic/Advanced editor as a subview of the existing Extension drawer. Device form state, Zod validation, options, and payload mapping remain single-source; Extension owns only inclusion and new-owner orchestration. |
| Managed Voicemail during create/edit | Both aggregate operations previously exposed only enablement, notification emails, transcription, and PIN setup | Reuse the complete Voicemail editor as a subview of the existing Extension drawer. Create and edit now share the full field component, Zod contract, Laravel validation, and Switch mutation factory. |
| Current User schema coverage | Calling options, contact-list exclusion, privacy, credentials, hotdesk, recursive metaflows, caller-ID variants, forwarding, recording, restrictions, endpoint media, MOH, ringtones, safe dial plans/formatters, bounded profile data, and pronounced-name Media are typed; external flags and policy/status fields are preserved and read-only | Continue relationship and operation audits without exposing raw nested JSON or copying every Monster feature tile without checking the connected schema |
| Relationships | Managed Device, Voicemail Box, and Callflow creation is orchestrated and projected through public API records | Add lifecycle tests for compensation and partial failure as form scope expands; related domain editors remain separate |

The connected schema requires `first_name` and `last_name` and currently
advertises the remaining User groups recorded in the field matrix. Monster is
still valuable for feature grouping—caller ID, call forwarding, hotdesk,
voicemail, music on hold, recording, and DND—but the connected schema and
observed clear behavior determine the actual payload.

### Voicemail findings

| Area | Current evidence | Decision / required remediation |
| --- | --- | --- |
| Invalid controls | Name, mailbox, timezone, notification emails, assignment, seek duration, and PIN expose inline errors but do not consistently receive the shared invalid border | Apply the shared validation-control helper to native and Headless UI controls, including compound/toggle containers |
| Error presentation | Zod failures are copied into the store's global mutation error and displayed above the submit button | Keep client errors inline only; retain the global error region solely for API/Switch failures |
| Assignment and timezone | Assignment uses the older select wrapper and timezone uses a native input/datalist | Use non-clipping Headless UI choices backed by account extensions and supported timezone options; preserve existing values safely |
| PIN behavior | The PIN is write-only and an omitted edit PIN is not sent to Switch, but create validation does not require a PIN when `require_pin` is enabled | Add matching Zod/Laravel conditional validation. Edit must distinguish an already-configured PIN from a mailbox that needs a new PIN without exposing the secret |
| ASR-dependent fields | The schema supports transcription, while actual provider availability is returned by the Switch authentication response | The shared token provider retains only typed availability/default booleans. The mailbox options response exposes those safe values, Laravel rejects newly enabling an explicitly unavailable capability before gateway mutation, and the shared toggle is disabled while off. Unknown auth state remains honest, and an existing enabled mailbox can preserve or disable its value |
| Notification precedence | Save-after-notify overrides delete-after-notify in Switch | Disable and clear Delete in Vue when Save is enabled, and reject the contradictory pair at both Zod and Laravel boundaries |
| `flags[]` ownership | The schema defines flags as values set by external applications | Preserve projected flags on every update; do not expose them as an operator-editable form field |
| `notify.callback` | The schema provides a typed callback object but Monster does not expose it in the basic mailbox workflow | Provide a bounded advanced workflow for number, disabled state, attempts, interval, timeout, and ordered schedule; do not expose raw callback JSON |
| Intentional differences | `announcement_only` is unsupported by the connected schema; `mp4` is accepted by the current schema even though Monster hid it; greeting and message operations already use dedicated workflows | Preserve these intentional schema-first decisions |

### Wave 2 implementation batches

1. Extract the shared invalid-control treatment and remove duplicate client
validation alerts in User/Extension and Voicemail. Add focused component
tests before applying the helper to later domains.
2. Align User/Extension option controls and the nested Device/Voicemail
   bootstrap with account capabilities and owning-domain validation services.
3. Fix Voicemail conditional PIN and ASR behavior, notification precedence,
   timezone/assignment choices, preserve external flags, then add bounded
   callback support.
4. Run focused Zod, Vue, Laravel, and `grid-api-switch` tests followed by an
   isolated headless Playwright create/edit/clear and clipping walkthrough.
5. Only after these acceptance checks pass, audit Directory and LineKey using
   the same baseline.

Current progress: batch 1 is complete for User/Extension and Voicemail. Their
text inputs, textareas, listboxes, and applicable switch containers now use the
shared invalid treatment; nested errors resolve to the owning control; local
Zod failures remain inline; and API validation responses with field errors no
longer create a duplicate global alert. Focused unit tests, TypeScript, and the
production build pass. The isolated headless Extension walkthrough also passes,
including the shared Device drawer subview, all eight Device types, Advanced
Caller ID and Restrictions visibility, a single-dialog accessibility boundary,
and the existing credential/hotdesk validation. Later entity forms have not yet
been declared compliant by this result.

Batch 2 is complete for User/Extension option sourcing and the related Device
boundary. Timezone, language, and presence use API-backed Headless UI choices,
represent inheritance as `null`, and preserve projected values during edit.
The Extension create workflow switches the existing Headless UI slide-over to
a wide Device subview containing the same reusable Basic/Advanced fields used
by the standalone Device workflow. It preserves the unfinished Extension draft
without stacking a second modal. All eight Device types, capability-based
visibility, provisioning catalog, Zod schema, payload mapper, Laravel Device
validation, and Switch mutation translation are shared; Extension owns only
inclusion, new-owner assignment, dependency ordering, and compensation.

The Extension create and edit workflows now apply the same pattern to managed
Voicemail. They open the complete standalone mailbox field component as a
subview of the same drawer, keep the Extension draft in memory, derive and lock
the managed mailbox identity, and return to the aggregate without stacking a
modal. Standalone and embedded operations share the same Zod input, Laravel
`SaveVoicemailBoxRequest` boundary, mutation-data factory, Switch gateway, and
redacted MySQL projection. Isolated authenticated lifecycles cover full
aggregate creation, edit hydration, advanced callback/audio clearing,
persistence reload, and disposable aggregate removal. The standalone callback
create/edit/clear/delete lifecycle also passes after this extraction.

The next managed-User parity batch is complete. The edit slide-over now uses
public phone-number UUIDs for external and emergency caller ID, applies an
independent Laravel E911 check, exposes all current `call_forward` leaf fields,
discovers call-restriction classifiers from Switch, and edits the current User
recording direction/network matrix. Account/Endpoint recording branches remain
exclusive to the Account schema. Asserted identity, recording URLs,
and unknown nested properties remain server-owned and are preserved without
returning private Switch identifiers or raw JSON to Vue. Creation intentionally
uses the same tabbed contract and sends explicit typed values only; inherited
or unresolved values remain omitted or require an explicit preserve state.

The following advanced-User batch now covers the current `endpoint.media`
schema, `music_on_hold.media_id`, and ringtone headers. Audio and video codec
order is explicit, media transport/encryption/fax/timeout values are bounded,
and music choices use account-scoped public Media UUIDs. Existing unprojected
music can only be retained through an explicit preserve state. Laravel resolves
the public UUID immediately before the Switch write, and the User DTOs merge
unknown safe nested fields without exposing raw `switch_json`. The legacy Basic
and Advanced workflows remain audit evidence, while the connected schema
determines the accepted field set. Focused SDK/API/Vue tests and an isolated
headless walkthrough verify the advanced section, viewport-bounded media
selection, and inline invalid borders.

The final bounded advanced-User field batch adds guided dial-plan and request
formatter editors, the current profile object, pronounced-name Media, and a
safe policy summary. Recursive or otherwise unsafe regular expressions are
rejected in Vue and Laravel. Public Media UUIDs are resolved only inside the
account boundary, and unresolved spoken-name Media can be explicitly retained
without disclosing its Switch identifier. Verified state, privilege, feature
level, and external application flags remain read-only; their values and other
safe unmodeled top-level keys are preserved server-side on every managed edit.
An isolated authenticated walkthrough verifies disclosure visibility, inline
regex invalid styling, viewport-bounded Media choices, and clean cleanup. It
does not replace the remaining live Switch create/edit/clear mutation capture.

### User/Extension Advanced-tab drift re-audit (2026-08-31)

A fresh field-by-field comparison of the installed `users` schema and its
`call_recording`, `call_waiting`, `caller_id`, `dialplans`, `endpoint.media`,
`formatters`, `metaflows`, and `profile` references found no disconnected or
missing managed-edit controls. Monster's User feature screens still support the
current grouping and conditional behavior, while the installed schemas remain
the payload authority. Create and edit now expose the same ten Advanced
sections; defaults remain typed and inheritance-safe instead of being hidden in
a smaller create-only contract.

The public boundary remains unchanged: caller-ID and Media choices use
account-scoped public UUIDs, raw Switch identifiers are resolved only in
Laravel, asserted identity and recording storage URLs remain server-owned, and
unknown nested fields are preserved by the typed read-merge-write path. User
policy, external flags, and Voicemail-owned fields remain read-only or in their
own domain rather than becoming generic Advanced inputs.

Focused verification passed with five Switch SDK tests / 31 assertions, the
managed Extension update API test / 38 assertions, the invalid User-calling
API boundary / 8 assertions, 13 Extension Zod/metaflow tests, isolated E2E
TypeScript typecheck, and the single isolated headless schema-backed User
calling walkthrough. No User implementation change or disposable live Switch
mutation was required by this drift check.

### Authenticated Monster User form walkthrough (2026-08-31)

An isolated headless walkthrough authenticated to the local Monster UI through
a short-lived account API token plus an account-scoped administrator. It opened
the live Add User form, inspected every Basic and Advanced tab, exercised the
conditional Hot Desking PIN control, and never submitted a mutation. The test
keeps the token out of URLs, storage state, reports, and traces.

| Monster mode / tab | Visible installed controls | GridPBX decision |
| --- | --- | --- |
| Basic | Username, first name, last name, email, privilege, voicemail email notification, internal caller-ID name/number, external caller-ID name/number, and related Device creation | Keep the task-oriented Extension aggregate, but add an explicit Basic mode. Extension identity, optional portal login, common calling options, and the reusable Device/Voicemail subviews belong here. Privilege and verified policy remain read-only unless a separately authorized administration workflow is approved. |
| Caller ID | Presence ID; internal, external, and emergency caller-ID name/number. Asserted identity was not visible under the installed capability | Add the schema-backed Caller ID section to Advanced for both create and edit. Continue using public Phone Number UUIDs for external/E911 choices and keep asserted identity server-owned. |
| Options | Verified, timezone, music on hold, contact-list exclusion, and record-user toggle | Group writable current-schema equivalents under Advanced Options. Verified remains a status rather than a browser mutation; richer current-schema recording controls remain available instead of collapsing them to Monster's single toggle. |
| Call Forward | Enabled, failover, destination number, substitute/bypass phones, require keypress, keep caller ID, and direct-calls-only | Add the complete bounded current-schema forwarding contract to Advanced create and retain it on edit. GridPBX may expose a supported current-schema field absent from this older Monster screen when the installed schema/runtime confirms it. |
| Password Management | New password and confirmation | Map to the existing write-only portal credential component. Never hydrate or reveal a password; unchanged edit credentials remain omitted. |
| Hot Desking | Enabled, ID, require PIN, conditional PIN, and keep-logged-in-elsewhere | Reuse the existing typed Hotdesk component in Advanced. PIN remains write-only with explicit preserve/clear semantics. |
| Restrictions | Closed-groups policy plus installed call classifications (`tollfree_us`, `toll_us`, `emergency`, `caribbean`, `did_us`, `international`, and `unknown`) | Use live classifier options and public actions. Closed-groups behavior requires a schema/runtime-backed public contract before becoming editable. |

GridPBX now applies the result of that walkthrough to both create and edit. The
Extension drawer has an outer Basic/Advanced selector and a shared inner
Advanced tab bar for Caller ID, Options, Call Forward, Password, Recording, Hot
Desking, Restrictions, Media, Routing & Profile, and Metaflows. The last three
are current-schema controls that are not represented by the older Monster
screen. Portal identity remains in Basic while write-only
password management is isolated under Advanced. Caller ID, forwarding, and
restriction views share one typed form model rather than duplicating controls.
Client and server validation select the outer Advanced view and the exact inner
tab containing an advanced-only error. Focused Vue tests and isolated headless
create/edit walkthroughs pass without clipping, raw storage URLs, console
errors, or server failures.

Disposable live Switch create/edit/clear verification now also covers the
Advanced contract rather than presentation alone. It creates a managed User
with write-only portal credentials, caller ID, call forwarding, outbound
off-net recording, Hot Desking PIN protection, and an international-call
restriction; synchronizes the projection after create and edit; clears each
managed value; synchronizes again; and removes the aggregate through the
guarded deletion workflow. The run produced no browser console errors or HTTP
5xx responses. During this verification, Switch normalized the managed
callflow number with a leading `+`. Synchronization now uses the root `user`
node's resource ID as the authoritative Extension relationship and retains
exact-number matching only for older callflows without that reference. A
focused regression test covers this normalization case so synchronization
cannot silently detach the managed callflow and block safe deletion again.

This parity is a presentation and safe-contract improvement, not evidence that
every Monster field should be copied blindly. The installed schema and runtime
remain authoritative for payload validity, clear behavior, security ownership,
and current-version fields.

The 2026-09-01 create-parity follow-through submitted the complete tabbed User
contract through an isolated disposable lifecycle. Switch initially rejected
an empty `call_restriction` encoded as JSON `[]`; the typed DTO now emits `{}`
for an empty restriction object. The rerun created and synchronized Media,
ringtone, Profile, and Metaflow values, read them back through the public
projection, and removed all disposable resources successfully. Structured
Switch validation details are retained only inside the server adapter and
redacted operational logs; the browser continues to receive a safe error.

The lifecycle is now fully UI-driven rather than using an API-only setup step.
The authenticated drawer creates the User through Media, Routing & Profile,
and Metaflows, reopens the same shared controls to edit them, clears every
optional value, synchronizes after each mutation, and verifies the public
projection before guarded cleanup. Clearing optional numeric inputs exposed a
shared form-normalization defect: native number controls produce an empty
string when cleared. The shared Zod integer helper now converts only that empty
form value to `null` and is used by User, Device, Account recording, and
Metaflow schemas. The clear request sends a null Metaflow binding override;
after synchronization Switch correctly reports its effective default `*`.
The focused run passed in 7.8 seconds without console errors or HTTP 5xx
responses.

The same batch replaces Voicemail's native timezone/datalist and assignment
select with API-backed Headless UI listboxes. Account inheritance is `null`,
public Extension UUIDs are the only assignment identifiers exposed, and legacy
projected values are retained safely during edit. New protected mailboxes—and
existing mailboxes enabling protection—now require a 4–6 digit PIN in both Zod
and Laravel; an already protected mailbox may be edited with a blank PIN to
keep the existing write-only secret.

The Voicemail callback object is now represented by an entity-organized Switch
DTO and bounded API/UI fields. Turning callback configuration off removes the
modeled callback; an otherwise empty public `notify` object is omitted, while
unknown safe notification siblings remain server-side and survive the full
Crossbar update. Notification disposition is deterministic: Save clears Delete
in the form, while contradictory direct API payloads are rejected. External
`flags[]` are deliberately not editable and are preserved from `switch_json`
during updates. The options response now reads
the installed authentication capability without exposing the auth token or raw
capability document. The current cluster reports transcription availability
and default as `false`; Laravel rejects newly enabling it before Switch mutation,
and the form disables the off-state control while allowing an existing enabled
value to be preserved or turned off. Missing authentication capability data
still produces an honest `null` state. A focused 2026-08-31 isolated headless
test verified the live unavailable warning and disabled shared toggle without
issuing a mailbox mutation. An earlier isolated authenticated walkthrough created an
unassigned mailbox with a paused callback, edited it, cleared the callback,
and removed the disposable mailbox. The walkthrough also established that an
unassigned mailbox must omit `owner_id`; the connected Switch rejects an
explicit JSON `null` for that field.

The managed Extension aggregate now has its own disposable live lifecycle
coverage. The embedded shared editor creates a mailbox with notification
delivery, write-only PIN protection, WAV media, playback controls, and a
paused typed callback; synchronization is then verified from the public
mailbox projection. A second aggregate update changes email delivery, media,
callback state and number, and playback behavior, followed by another
synchronization assertion. The final update clears notification recipients,
callback configuration, notification actions, mailbox locking, and PIN
protection, restores MP3, and verifies the cleared Switch state after sync.
Disabling PIN protection also clears the upstream write-only PIN, which is the
safer runtime behavior. The Extension, mailbox, and managed callflow are then
removed through the guarded aggregate deletion workflow, leaving no active
matching projection. The isolated run reported no browser console errors or
HTTP 5xx responses.

### Voicemail Advanced-form drift re-audit (2026-08-31)

The installed `vmboxes` and `notify.callback` schemas reconfirmed that every
supported editable mailbox field is present in the shared standalone/embedded
form. Unsupported `announcement_only`, operational `is_setup`, greeting media,
external flags, message operations, and account playback keys correctly remain
outside generic Advanced inputs. Monster remains workflow evidence for PIN,
playback, notification, and ASR conditionals; the installed schemas and runtime
remain authoritative.

Runtime inspection exposed a preservation gap below the form. Voicemail `POST`
merges only existing CouchDB private fields into the submitted public document,
so omitted public fields—including an unchanged PIN, greeting media, setup
state, and future schema keys—could be lost. The typed SDK/API path now re-reads
an unchanged PIN privately, preserves safe unknown top-level and nested
notification fields from redacted `switch_json`, and normalizes empty preserved
`media` to the schema-required JSON object. Preservation metadata is never
accepted from or returned to Vue, and raw owner or Media identifiers remain
server-side.

Focused verification passed with 11 Switch SDK tests / 54 assertions, five
Laravel Voicemail API tests / 25 assertions, seven Voicemail Vue tests,
isolated E2E TypeScript typecheck, and one isolated headless protected-mailbox
create/edit/clear/delete lifecycle. The lifecycle confirmed two edits with a
blank browser PIN preserved the configured secret and callback clear persisted.
After cleanup, MySQL contained three historical matching soft-deleted
projections and zero active matches; CouchDB contained zero matching active
voicemail documents.

The 2026-08-31 presentation follow-through now uses the Device-style outer
Basic/Advanced selector in both standalone Voicemail and the embedded Extension
workflow. Advanced exposes Monster's inner Basic/Options tabs instead of one
flattened panel. Basic retains mailbox identity, account-scoped assignment,
and write-only PIN. Options owns account timezone, schema-backed audio format,
notifications, typed callback delivery, transcription and owner options, and
playback behavior; greeting media remains a dedicated operation. Client and
API field errors select the exact outer and inner tab without duplicating the
Zod or Laravel contract. The latest rerun passed three focused component tests,
Vue and isolated E2E typechecks, and two non-mutating isolated headless
Playwright checks covering standalone cross-tab validation and embedded reuse.

### Directory and Line Key follow-through (2026-08-29)

Directory now follows the same form baseline. The sort control uses the shared
Headless UI listbox and was verified inside the viewport with isolated headless
Playwright. Name, DTMF, confirmation, and member controls use the shared red
invalid treatment; the blank-name path remains inline and no duplicate global
validation alert is shown. Directory `flags[]` are external-application-owned,
so the operator editor was removed, Laravel now prohibits flag input, create
initializes an empty list, and edits/compensation preserve the projected flags.

Line Key already used capability-filtered Headless UI choices, grouped
main-unit/expansion slots, Zod, and field-level invalid styling. Its store now
also suppresses the duplicate mutation alert when the API returns actionable
nested field errors. The existing provisioning walkthrough remains the live
clipping and grouped-slot acceptance surface.

The capacity recheck confirmed that the four-key display is specific to the
selected SPA504G catalog model, not a hard-coded editor maximum. Matched models
derive their main-unit and expansion bounds from provisioner capability data;
unknown models retain the conservative 100-assignment fallback. Vue, Laravel
request validation, and the domain capability service now all reject a combo
key and feature key sharing the same physical position, even in fallback mode.
The focused verification passed all 16 Line Key API feature tests with 136
assertions, all nine Line Key form tests, and the isolated headless T54W
walkthrough. The walkthrough confirmed 10 main-unit positions, three 20-key
expansion groups, unclipped controls, and disposable create/edit/clear cleanup.

The 2026-08-31 Directory drift re-audit rechecked all installed schema fields,
the runtime update path, and Monster's Basic/Advanced workflow. The visible
name, single-match confirmation, DTMF bounds/defaults, first/last-name sort,
and membership controls remain aligned. Public member selections are still
account-scoped Extension UUIDs; Laravel privately resolves their User and
Callflow resource IDs and never returns either raw value.

One server-side preservation gap was corrected. Installed Directory updates
use `POST` and `crossbar_doc:load_merge`, so retaining only external `flags[]`
could remove an unknown future public property. Laravel now derives a private
preservation bag from redacted `switch_json`, removes modeled fields, response
`id`, derived `users`, private keys, and redacted values, and passes the result
to the typed SDK DTO. Modeled values always override the bag. Both `flags` and
`preserved_options` remain prohibited operator inputs. The focused SDK test
passed with 1 test/8 assertions, and the two focused Laravel tests passed with
9 assertions.

The disposable live follow-through on 2026-08-31 exercised create, edit,
authoritative reopen, final-member removal, `max_dtmf = 0` (`unlimited`), and
delete through the isolated headless UI. Kazoo accepted the Directory value but
correctly rejected an empty User `directories` JSON array, exposing a PHP
serialization defect: empty mapping objects are now emitted as `{}`. The same
run exposed Headless UI closing the underlying slide-over when its delete
confirmation opened; Directory now uses the established Menu guard and remains
mounted until confirmation completes. Focused SDK coverage passed with 6 tests
and 27 assertions, the Directory component regression passed with 2 tests, the
isolated E2E typecheck passed, and the corrected lifecycle passed. Public
responses used only the account-scoped Extension UUID. Independent cleanup
checks found zero active matching MySQL projections and zero active Switch
Directories; all six focused-attempt projections are soft-deleted.

The 2026-09-01 responsive and accessibility pass kept the Directory payload
and public/raw mapping unchanged. The inventory now has an accessible table
name, scoped headers, announced loading and failure states, keyboard-operable
Directory names, and table-local horizontal scrolling. Header, search,
Basic/Advanced, member-group, listbox, and slide-over controls remain inside a
390-pixel viewport. The pass also corrected the shared controlled tab bar so a
programmatic validation return to Basic cannot leave Advanced visually marked.
Five focused component/shared-control tests and one isolated non-mutating
headless run verified inline validation, tab semantics and classes, zero
Directory or sync writes, and clean browser/server state.

The 2026-08-31 Line Key drift re-audit then compared the editor with the
installed `devices.combo_key.json` and Device update runtime. The category,
position, type, optional value, conditional parking range, and labeled-value
controls cover the complete installed schema. GridPBX keeps the product caps
of 999 positions and 255 text characters. Parking validation now rejects
fractional numeric strings at the Laravel boundary instead of deferring the
failure to the SDK. Unknown request keys are prohibited.

Line Key intentionally remains a single-purpose form rather than adding an
outer Basic/Advanced selector. It is not an independent Switch resource: the
maps live under the owning Device's provisioning subtree. Monster presents
Combo Keys and Feature Keys as type-driven sections inside its Device editor,
not as a separate Line Key entity with Basic and Advanced views. GridPBX's
standalone panel already isolates only that advanced provisioning workflow and
groups rows by main unit and expansion module, so another tab layer would be
empty duplication rather than schema or workflow parity.

Line Key replacement remains a live read-modify-POST because installed
Crossbar PATCH recursively merges old combo/feature maps. The SDK preserves
safe unknown fields on retained key rows and inside retained labeled values,
as well as the surrounding Device/provision document; submitted modeled
fields take precedence and omitted positions are intentionally removed.

The reference boundary was also tightened against the installed provisioner
runtime. Presence and personal-parking values use only account-scoped
Extension/User UUIDs in list, preview, payload-preview, and update responses;
devices are not valid BLF targets. Laravel resolves those UUIDs to raw Switch
user IDs immediately before mutation and maps them back after projection.
Speed-dial choices store the literal dialable extension, while line appearances
are value-less combo keys and parking positions remain limited to 1–10.
Foreign UUIDs and type/value mismatches fail before any Switch request.

Focused checks passed with seven SDK tests / 17 assertions, eleven Laravel
tests / 91 assertions, ten Vue unit tests, both Vue and E2E TypeScript checks,
and an isolated headless disposable Device walkthrough. The walkthrough
confirmed the Device-detail and fleet editors, grouped main/expansion layout,
clean browser/network state, a live Switch create/edit/clear lifecycle, and
cleanup of the disposable device.

The Directory presentation follow-through now renders Monster's confirmed
grouping with the shared tabs: Basic contains the Directory name and
account-scoped members; Advanced contains sorting, DTMF limits, and
single-match confirmation. The focused component test, Vue/E2E typechecks,
and one isolated non-mutating browser check passed.

### Group and Menu follow-through (2026-08-29)

Group now uses a domain Zod/composable boundary, shared invalid styling, and
Headless UI choices for music-on-hold and member selection. Nested member
errors resolve to the member editor, API field errors remain inline, and the
authenticated headless walkthrough verified the red name border and a
viewport-bounded music listbox. The current Group schema contains only name,
endpoints, music-on-hold, and external flags; GridPBX exposes the first three
and preserves the external flags without accepting them from operators.

The 2026-08-31 Group drift re-audit reconfirmed that the installed Group
document has only `name`, `endpoints`, `music_on_hold.media_id`, and
external-application `flags`. Monster's extensions, phone numbers, ring
timing, and other feature screens are related Callflow workflows, not missing
Group Advanced fields. GridPBX retains its typed account-scoped User, Device,
and nested-Group member controls, public Media UUID selector, ordered weights,
cycle protection, and dependency-safe deletion.

The presentation audit also confirmed that Group is intentionally Basic-only.
Monster's template contains the generic view-button markup, but its tab list
has only one Basic item; `winkstartTabs` hides the selector whenever fewer than
two tabs exist. GridPBX therefore keeps name, membership, and optional hold
Media together instead of inventing an Advanced screen.

One full-update preservation gap was corrected. Installed Group `POST` uses
`crossbar_doc:load_merge`, so preserving only `flags[]` could discard future
public fields and unknown metadata attached to a retained endpoint or the
music-on-hold object. Laravel now derives those values privately from redacted
`switch_json`; resource/private/redacted and modeled fields are excluded, and
the typed SDK lets modeled input win. Removed endpoints remain removed, and
clearing music removes `media_id` while retaining safe sibling metadata. An
empty music value now serializes as `{}` rather than `[]`. Hidden preservation
input and unknown member keys are rejected, and Laravel now repeats Zod's
100-member cap.

Focused SDK, Laravel, Zod/composable, component, and E2E type checks cover the
Group boundary. An authenticated isolated-headless lifecycle created a
disposable Group with synchronized User, Device, and nested Group members plus
optional Media, edited it, cleared all members and the modeled hold-media
reference, reopened the authoritative result, and deleted it. This exposed and
fixed the required empty-object encoding for cleared `endpoints`; final MySQL
inspection found no active disposable Group projection. Delete confirmation is
owned by the page rather than nested inside the Headless UI slide-over, so
confirmation reliably reaches the store/API mutation.

The 2026-09-01 responsive and accessibility pass kept that installed-schema
contract and public/raw mapping unchanged. The inventory now has an accessible
table name, scoped headers, announced loading and failure states,
keyboard-operable Group names, and table-local horizontal scrolling. Header,
search, member selection, music listbox, and slide-over controls remain inside
a 390-pixel viewport, and the ordered member collection has an accessible group
name. The validation-only isolated scenario no longer deletes stale live Groups;
it verified inline errors with zero Group or synchronization writes and clean
browser/server state. Ring Group audible-media verification remains a separate
externally blocked acceptance item.

Menu applies the same baseline to every numeric, PIN, pattern, toggle, and
media control. Its current schema supports custom invalid, transfer, and exit
media in addition to the smaller legacy Monster workflow, so those bounded
controls are an intentional schema-backed superset. External flags are never
operator-editable.

The 2026-08-31 drift re-audit found that installed `cf_menu` consumes the three
`media.*` result-prompt values but not the documented top-level
`suppress_media` property. GridPBX now maps that control to three explicit
`false` values, disables the dependent fields, and ignores stale Media UUIDs
when an individual prompt is disabled. This matches Monster's runtime mapping
while retaining GridPBX's schema-backed custom-prompt superset.

A blank recording PIN on edit still means preserve. A new explicit removal
control clears it without returning or persisting the secret. The SDK obtains
the current PIN only for the outbound full Switch update. That same fresh read
now preserves safe unknown top-level and nested `media` fields because
installed Menu `POST` otherwise retains only private CouchDB metadata.
Unresolved raw prompt references cross the public boundary only as booleans;
they are preserved unless the operator explicitly clears or replaces them
with an account-scoped Media UUID. Empty media writes as `{}`.

Focused checks passed with four SDK tests / 19 assertions, four Laravel tests /
37 assertions, eight Vue tests in three files, Vue and isolated E2E TypeScript
checks, and the existing isolated form check. A second isolated lifecycle used
`E2E Menu 45693910` to create and replace a write-only PIN, enable and reopen
runtime prompt suppression, clear and reopen the PIN, delete the Menu, and run
an independent synchronization. The latest projection remained soft-deleted,
no active Switch Menu was re-imported, and neither the PIN nor raw Switch ID
appeared publicly. The same run exposed and verified a small nested-dialog
guard so the delete confirmation can no longer close its parent before
issuing the mutation.

The 2026-08-31 presentation follow-through replaced the single long Menu form
with the Device-style outer Basic/Advanced selector. Basic mirrors Monster's
name, write-only recording PIN, extension-dialing switch, and greeting
workflow. Advanced now exposes Monster's inner Basic, Extension Dialing, and
Options tabs instead of flattening the latter two sections. Extension patterns
belong to Extension Dialing; timeout, retry, recording, suppression, and the
additional installed-schema result-prompt controls belong to Options.
Validation selects the exact outer and inner tab for both client and server
errors. Three focused component tests, Vue and isolated E2E typechecks, and the
isolated non-mutating Menu browser check passed. The previous disposable live
check remains the payload evidence: it created, edited, reopened, cleared, and
deleted only its `E2E Menu` record.

### Queue and Agent follow-through (2026-08-29)

Queue now uses the same domain Zod/composable, Headless UI, invalid-control,
and inline-only error baseline. The current Switch schema additions are
represented as virtual fields read from and written back to `switch_json`:
connect announcement media, create-only maximum priority, announcement
interval, position/wait announcements, and the optional all-or-none set of
four custom announcement prompts. Only public Media UUIDs cross the UI/API
boundary; Laravel resolves Switch resource identifiers inside the account.
No JSON-derived field added a MySQL column.

The 2026-08-31 drift re-audit compared the complete installed `queues.json`
schema with `cb_queues`, `crossbar_doc:load_merge`, the ACDc queue FSM, and the
current GridPBX form. The form still covers every schema field that is safe for
an account operator. GridPBX's upper numeric limits are deliberate operational
safety caps layered over Kazoo's minimum-only fields. The reference Monster
checkout contains no Queue/Agent editor to add workflow evidence.

The 2026-08-31 presentation follow-through added the shared Basic/Advanced
tabs without inventing a Monster workflow. Basic contains Queue identity,
schema-backed strategy, account-scoped hold Media, and the account-scoped
Extension roster. Advanced contains ACDc timing, capacity, simultaneous-ring,
exit, empty-queue, recording, create-only priority, and announcement controls.
The outbound URL fields remain policy-gated and hidden. Client and API field
errors select the owning tab, with Basic errors taking priority when both tabs
contain errors; an API error present when the form opens now routes immediately.
Two focused Queue component tests, Vue and isolated E2E typechecks, and one
non-mutating isolated headless Playwright Queue form check passed. The browser
exercised the Device-style tab transition, Advanced announcement fields, and
return to the invalid Basic name field without sending a Queue mutation.

Kazoo's Queue POST path merges submitted data with only the existing private
fields, so omitted unknown public fields are otherwise deleted. The SDK now
performs an authoritative pre-update GET and merges safe unknown top-level,
`announcements`, and custom-prompt metadata into the typed write. Modeled
fields win, while `id`, `_rev`, `pvt_*`, redacted values, and the raw `agents`
roster are discarded. Existing `max_priority` is forced back onto updates so
the create-only schema constraint cannot be bypassed through the SDK.

An authenticated isolated lifecycle created a disposable queue with priority
and periodic announcements, edited the interval, cleared the announcement
object, and deleted the queue. The same run verified the Headless UI menu stays
inside the viewport and validation remains inline with red controls.

`cdr_url` and `recording_url` remain deliberately absent from the operator
form until an outbound URL/SSRF allowlist policy is available. The installed
schema names `recording_url`, while the installed ACDc queue FSM reads the
legacy/runtime key `call_recording_url`; enabling either without resolving
that contract would be misleading. Existing values under both keys are
preserved across full Switch updates but are never returned by the API.
Agent status actions use a separate Zod/composable and Headless UI command
form with conditional pause-timeout validation. Automated live status changes
are intentionally not sent to real agents; the audited API boundary remains
covered with an isolated gateway test.

The runtime panel now refreshes from Kazoo's per-Agent status endpoint every
five seconds only while its slide-over and the browser tab are visible. The
shared poller pauses during initial reads and commands, prevents overlapping
requests, resumes immediately when visibility returns, and stops on close or
account change. Background failures retain the last observation and use a safe
UI message. Command acceptance remains explicit and is not substituted for an
observed status because Kazoo can defer pause, resume, and logout while an
Agent is on a call. An isolated headless test verified open, periodic refresh,
and cleanup without sending a live command.

Per-Agent Queue membership uses Kazoo's separate `queue_status` contract.
Membership reads require Queue configuration only, so the Agent panel remains
available when live Agent status is not. Join and leave additionally require
live Agent controls, accept only public account-scoped Queue UUIDs, reconcile
the projected roster only after Switch accepts the command, and expose
unprojected assignments only as a count. Three focused Vue files passed 17
tests, Vue and E2E TypeScript checks passed, and two isolated headless cases
verified the read-only unavailable-status view plus an accepted public-UUID
leave command and polling cleanup. The accepted command used an intercepted
Switch boundary; no real Agent membership was changed because the connected
deployment reports live controls unavailable.

The installed Kazoo 7.0.4.1 BEAM runtime confirms that account-wide Agent
status is an Agent-ID keyed object containing timestamp-keyed histories. Its
only emitted states are `ready`, `logged_in`, `logged_out`, `connecting`,
`connected`, `wrapup`, `paused`, and `outbound`; individual entries can also
contain call, caller, Queue, wait, pause, alias, and internal record IDs.
GridPBX keeps only the latest recognized state and timestamp per Agent in the
typed Switch client. Laravel resolves private Agent IDs to account-scoped
public Extension UUIDs, represents a projected Agent without history as
`unknown`, counts unmatched rows without identifying them, and discards all
call and Queue details. The Agents table presents compact availability labels
and refreshes every ten seconds only while that tab and the browser document
are visible, retaining the last safe snapshot after a failure. Two focused SDK
tests passed with six assertions, two focused API tests passed with sixteen
assertions, three Vue files passed twenty tests, and the focused Vue/E2E type,
lint, and isolated headless polling checks passed. The connected deployment
still reports live Agent controls unavailable, so this is installed-runtime
and intercepted-boundary evidence rather than a live availability claim.

Queue capability discovery is now explicit rather than inferred from whether
configuration documents exist. The installed runtime has `cb_queues` and
`cb_agents` loaded, but does not run the ACDc OTP application and does not load
`cb_acdc_call_stats`. Safe account reads returned `200` for Queue and Agent
configuration, `500` for aggregate Agent status and Agent statistics, `503`
for Queue statistics, and `404` for `acdc_call_stats`. GridPBX probes Queue
configuration, aggregate Agent status, Agent statistics, and Queue statistics
independently, caches the four booleans for one minute, and returns only
`configuration_available`, `live_agent_controls_available`, and
`agent_statistics_available`, and `statistics_available`. Probe bodies and raw
Switch identifiers are discarded.

An isolated headless run on 2026-08-31 confirmed the connected public response
was `true`, `false`, and `false`, respectively. Queue creation remained enabled,
the unavailable live-control explanation was visible, Queue statistics stayed
capability-gated, and the browser sent zero Queue or Agent mutations. The one
browser test passed in 2.7 seconds. Focused SDK, Laravel, Zod, store/component,
Vue type, and E2E TypeScript checks also passed. No disposable Queue, live Agent
state change, or statistics request beyond the read-only probes was required.

The capability-gated read-only statistics UI is now implemented for
deployments where queues/stats is available. The installed Kazoo source shows
that the endpoint returns a deployment-configured recent window and raw call,
caller, Agent, and Queue fields. The typed Switch client deliberately keeps
only Queue/status/timing values; Laravel aggregates them by account-local
projected Queue and returns only public UUIDs, safe counts/durations, and an
unresolved-row count. The panel refreshes every 15 seconds only while visible,
does not overlap reads, preserves the last snapshot on failure, and includes a
manual refresh. Focused tests and an isolated headless intercepted-capability
walkthrough passed. The connected local Switch still returns the capability as
unavailable, so live runtime statistics remain visibly gated there.

Agent call-performance statistics use their own capability because Kazoo's
`agents/stats` endpoint can fail independently of both aggregate Agent status
and Queue statistics. The compressed response is keyed by private Agent ID and
contains a nested private Queue-ID breakdown. GridPBX validates and retains
only total, answered, and missed counts in the typed client. Laravel resolves
account-scoped projected Agents and returns public UUIDs, names/extensions,
answer rates, aggregate totals, and only a generic unresolved-Agent count. The
Agents tab polls every 15 seconds only while selected and visible, offers manual
refresh, prevents overlap, and keeps the last good snapshot after a background
failure. The source implementation uses the configurable ACDc cleanup window,
which defaults to one day. Focused SDK/API/UI/type checks and an isolated
headless initial/manual/periodic refresh test passed with a safe intercepted
public response. The connected deployment reports the capability unavailable,
so live Agent-statistics verification remains pending.

The preservation correction passed six focused SDK tests / 37 assertions and
two focused Laravel Queue update tests / 12 assertions. Four Queue/Agent Vue
files passed five tests, the isolated E2E TypeScript check passed, and isolated
headless checks passed the non-mutating schema form plus a disposable Queue
create/edit/announcement-clear/delete lifecycle. No live Agent state command
was sent because the connected capability remains unavailable.

The 2026-09-01 responsive and accessibility follow-through retained those
payload boundaries while hardening both inventories and the Queue form. Queue
and Agent tables now have accessible names, scoped headers, loading state, and
keyboard-operable record controls; unavailable Agent controls remain disabled.
The Extension roster is a labelled group, form and page errors are announced,
and in-flight saves disable the fieldset. A focused two-test component run,
Vue and isolated E2E TypeScript checks, focused lint, and one isolated
headless Playwright check passed. The browser exercised validation and tab
routing, then verified the form and page actions at a 390-pixel viewport. It
sent zero Queue, Agent, or Queue-sync mutations and reported no browser errors,
so no live Agent capability is claimed by this presentation-only check.

### Presence and parked-call status follow-through (2026-08-31)

The installed deployment loads `cb_presence` and `cb_parked_calls`, and its
`omnipresence` OTP application is running. Account-scoped reads returned `200`
for both `GET /presence` and `GET /parked_calls`. Compiled-runtime inspection
showed that the Presence endpoint searches and aggregates SIP subscriptions;
its detailed form may contain contacts, call and subscription identifiers,
proxy routes, user agents, realms, and notification bodies. It is diagnostic
evidence, not an authoritative live User-presence state. Set/reset Presence
operations remain disabled because they need an explicit authorization, audit,
identity-mapping, and near-real-time command design.

The parked-call endpoint is read-only and returns a `slots` map. Slot documents
may include raw call IDs, Switch URIs, SIP tags, Presence IDs, caller identity,
node information, and media configuration. GridPBX therefore exposes only
whether the summary endpoint is available and the count of active slots. It
does not expose slot numbers or raw slot documents. Kazoo park/retrieve behavior
runs inside a live callflow media leg and has no corresponding REST action, so
the browser offers no park or retrieve command.

The public endpoint is scoped by the account's GridPBX UUID. Laravel resolves
the private Switch account reference behind its gateway, probes both endpoints
independently, and caches the allowlisted result for ten seconds. No Presence
or parking document is copied into MySQL. Unknown runtime fields are discarded
from the response but remain untouched at the source because this slice sends
no Switch mutation.

An isolated authenticated headless check on 2026-08-31 loaded the new System
status page from the live local API. It confirmed the strict public shape,
visible capability explanations, aggregate parked-call count, zero operational
mutations, and absence of raw call, Presence, Switch, subscription, or account
identifiers. The one browser test passed in 2.6 seconds. Focused Switch SDK
tests passed with 2 tests and 5 assertions; Laravel passed with 3 tests and 18
assertions; the two focused Zod/store files passed 3 tests. Vue typecheck and
the isolated E2E TypeScript check also passed.

### Webhook resource capability follow-through (2026-08-31)

This resource audit is separate from the capability-gated `callflows.webhook`
palette action. The installed deployment loads `cb_webhooks`, runs the
`webhooks` OTP application, and exposes the global event catalog plus
account-scoped configuration, sample, and attempt reads. Live read-only probes
returned nine available event types, zero configured hooks, zero attempts, and
seven samples for the selected account.

The installed schema requires a name, event, and destination URI. It also
permits GET/POST/PUT, form or JSON bodies, retry settings, descendant and
internal-leg inclusion, event modifiers, and arbitrary string custom data that
can overwrite event fields. Monster's workflow exposes those controls and can
render complete attempt JSON. It remains reference-only: GridPBX does not copy
that mutation form or raw history behavior.

Runtime inspection confirmed that channel events can include caller/callee,
call, owner, account, reseller, resource, SIP, custom channel, and custom
application data; SMS/MMS events include message bodies; notifications may
carry their normalized event payload. Delivery adds raw account and hook IDs
to headers but has no signature or secret. The default URI blacklist covers
only literal `localhost`, `127.0.0.1/32`, and `0.0.0.0/32`; hostname resolution,
private/link-local/metadata destinations, DNS rebinding, and each redirect hop
are not safely enforced. Attempt documents can retain the URI, raw hook ID,
request/response headers, request/response bodies, retry state, and errors, and
Crossbar removes only document IDs before returning them.

GridPBX therefore added only an aggregate Webhook section to the existing
account-scoped System Status endpoint: event catalog availability/count and
configuration availability/configured/enabled counts. The Switch SDK discards
each raw list item after counting; Laravel fixes mutation and delivery-history
capabilities to false; the strict Zod schema rejects extra raw fields. No
Webhook document is persisted in MySQL and the allowlisted summary shares the
ten-second operational-status cache.

Focused checks passed with 2 SDK tests and 8 assertions, 3 Laravel tests and 28
assertions, and 2 UI files with 4 tests. Vue and isolated E2E TypeScript
typechecks passed. One isolated authenticated headless browser check passed in
2.6 seconds and confirmed the live aggregate card, no mutation request, and no
raw hook ID, URI, request body, response body, account reference, or Switch
payload in the public response. No hook was created, edited, enabled, disabled,
deleted, or called. Webhook CRUD, event selection, and delivery health remain
capability-gated until signed HTTPS allowlisted egress, per-hop DNS/IP and TLS
enforcement, minimized public-safe payloads, secret rotation, bounded retries
and response handling, redacted retention, authorization, immutable audit,
rate/circuit controls, and an emergency kill switch exist.

### SMS/MMS capability follow-through (2026-08-31)

This audit did not add a message form or messaging domain. The installed SMS
schema requires a destination and a 1–700-character body; MMS requires a
destination and accepts either MIME-encoded content or multipart uploads.
Runtime eligibility is stricter than either schema: the number must be assigned
and in service, its carrier must advertise the matching feature, the account
and reseller need the matching service and acceptable standing, and outbound
delivery can create billable ledger entries.

The live installed topology does not satisfy that boundary. `cb_sms` is loaded
but its authenticated account collection returns HTTP 503. `cb_mms` is present
on disk but is not loaded and returns HTTP 404. Doodle is not running, handles
only inbound SMS in this version, and the audited account has no projected
phone number from which carrier SMS/MMS capability could be proven. The
installed MMS reseller check also calls the SMS entitlement helper, so MMS
must remain gated even after its Crossbar module is enabled.

The runtime review found additional policy gaps: raw collection documents
contain message bodies, participant numbers, statuses, directions, timestamps,
and private IDs; Doodle's durable queue has unlimited TTL and length with an
infinite route timeout; and MMS builds attachments from supplied MIME types and
filenames without message-specific type, filename, count, malware, or content
rules beyond Crossbar's global payload limit. Consent and opt-out enforcement,
destination classification, sender ownership, rate limiting, idempotency,
billing confirmation, delivery audit, retention, deletion/legal hold, and
abuse response also remain undefined.

GridPBX therefore extends only the existing account-scoped System Status
summary. The Switch SDK probes `/sms` and `/mms` independently using
`paginate=true&page_size=1`, validates only the list envelope, and reduces the
result to two booleans. Laravel fixes message-content and sending capabilities
to false. The strict public Zod contract rejects extra raw message fields, and
no message document or number is persisted in MySQL. This is a read-only
capability foundation, not an SMS/MMS feature.

Focused checks passed with 2 Switch SDK tests and 14 assertions, 3 Laravel
tests and 35 assertions, and 2 UI files with 5 tests. Vue typecheck and the
isolated E2E TypeScript check passed. One isolated authenticated headless
Playwright check passed in 3.5 seconds and verified both live inventory
endpoints display unavailable, message content and sending remain disabled,
no operational mutation is emitted, and no body, participant, private message
ID, attachment, or Switch account identifier enters the public response or UI.
No SMS or MMS message document was sent, received, created, opened, or deleted.

### Number Porting capability follow-through (2026-08-31)

The installed Port Request schema and runtime describe a regulated workflow,
not a Phone Number edit form. A request requires a name and at least one number
and may hold losing-carrier account details, BTN, PIN, full billing address,
winning-carrier references, signee identity, signing/transfer dates,
notification recipients, comments, uploads, state, account ownership, and
port-authority identity. Monster guides operators through bill/LOA PDFs,
account verification, number entry, notifications, authorization, transfer
date, submission, state tracking, documents, and comments; it remains workflow
evidence only.

Installed state transitions are authority-sensitive. Submission is allowed
from unconfirmed or rejected. Pending requires submitted; scheduling accepts
submitted or pending; rejection accepts submitted, pending, or scheduled;
cancel accepts every non-completed active state; and completion accepts pending,
scheduled, or rejected. Non-authority accounts can submit their request and
cancel it while unconfirmed, but later operational transitions are reserved for
the configured port authority or super administrator. Completion is not a
label update: it creates and assigns in-service ported numbers, clears them from
the request, and reconciles callflow/trunk usage.

The external and document boundaries are not safe enough to expose. Phonebook
submission can forward the current Kazoo auth token and public request document
to a configured URL. A separate submitted-request exporter can POST the request
and every attachment to an account-configured URL. LOA generation calls Google
Charts with raw account and Port Request identifiers. Crossbar accepts PDF,
plain text, and generic octet-stream uploads up to its global 8 MB ceiling,
while Monster's client-only rule is PDF up to 5 MB. Comments and timelines can
contain private content, action-required flags, reasons, and raw authorization
account/user identities.

Live read-only checks found `cb_port_requests` loaded, an available empty
account endpoint, Phonebook unset/disabled, no Phonebook URL, and no
account-level submitted-request export URL. Because the installed active-state
listing disables pagination, GridPBX does not use a nominal `page_size=1`
probe. The Switch SDK instead queries the exact-number filter with the
non-number sentinel `gridpbx-capability-probe`, validates only the list envelope,
and immediately returns a boolean.

GridPBX exposes that boolean only in the existing account-scoped System Status
response. Laravel hard-disables request details, documents, and workflow
mutations; strict Zod rejects raw request fields. No request, number, billing
value, PIN, comment, attachment, state transition, authority identifier, or
carrier mutation is copied into MySQL or returned to the browser. This is a
read-only capability foundation, not a Porting feature.

Focused checks passed with 2 Switch SDK tests and 17 assertions, 3 Laravel
tests and 42 assertions, and 2 UI files with 6 tests. Vue typecheck and the
isolated E2E TypeScript check passed. One isolated authenticated headless
Playwright check passed in 3.5 seconds and verified the live inventory signal,
all higher-risk flags fixed to false, no operational mutation, and no sensitive
Port Request content in the public response or UI. No Port Request was created,
edited, submitted, transitioned, completed, canceled, or deleted; no individual
request document, uploaded document, or LOA was accessed.

### Number acquisition and release follow-through (2026-08-31)

The installed number workflow separates carrier information, search, purchase,
reservation, and release. `carriers_info` is read-only but returns a static
usable-carrier catalog and account-authorized creation states, neither of which
proves a provider is configured or reachable. Number search validates a
three-to-ten-character prefix, two-character country, and positive quantity,
then fans out to every effective carrier module. It can therefore contact
external providers despite using GET.

Live configuration inspection found both global and audited-account
`carrier_modules` unset, so the deployment falls back to `knm_local`. The local
carrier searches internal available inventory, does not bill, and has no-op
acquire/disconnect callbacks. The live carrier-info endpoint returned a valid
shape with maximum prefix 10, 24 static catalog entries, and the creation states
`aging`, `available`, `in_service`, `port_in`, and `reserved`. GridPBX exposes
none of those values; it returns only `carrier_configuration_available = true`.

Purchase/activation, reservation, and release remain operational commands.
Activation and reservation start as dry runs; non-empty charges return HTTP 402
and Monster's shared handler retries with `accept_charges=true` after operator
confirmation. No stable idempotency key protects that retry, and collection
operations can partially succeed. Reservation changes assignment/history and
can acquire a discovery number from a carrier. Release strips features and
public fields, can return ownership to a prior reservation account, and can
disconnect then age or delete a number. Local numbers without reserve history
are deleted even though the live global defaults leave permanent deletion and
aging disabled. Monster's availability recheck is mocked, while its E911 guard
and delete confirmation are client-only workflow evidence.

The SDK now performs only the account-scoped carrier-info GET, validates its
expected object and list types, discards the payload, and returns one boolean.
Laravel hard-disables search, purchase, reservation, and release. Strict Zod
rejects carrier catalogs/modules, creation states, available-number inventory,
quotes, and charges. The UI explains that endpoint availability is not carrier
readiness. No carrier search, quote acceptance, purchase, reservation, release,
provider call, or number mutation was performed.

Focused checks passed with 2 Switch SDK tests and 20 assertions, 3 Laravel tests
and 51 assertions, and 2 UI files with 7 tests. Vue and isolated E2E TypeScript
checks passed. One isolated authenticated headless Playwright check passed in
3.5 seconds and verified the live boolean, four literal-false operational
flags, zero mutation requests, and absence of raw carrier data in the public
response and UI.

### Conference follow-through (2026-08-29)

Conference now uses the same domain Zod/composable, Headless UI, shared red
invalid-control, and inline-only validation baseline. All role-number lists
follow the connected `conferences.json` schema: the arrays must be present but
may be empty. This corrected an API-only `required|array` rule that rejected a
schema-valid Conference without moderator numbers.

The current-schema sound fields are exposed through typed virtual values, not
new MySQL columns. `max_members_media` resolves a public account Media UUID;
`play_entry_tone` and `play_exit_tone` support the standard tone, silence, or
projected Media. An existing custom Switch tone that is not in the projected
Media catalog is represented only as “keep current custom tone,” so its raw
Switch value is neither leaked nor accidentally overwritten. Unresolved
conference-full media is preserved across edits.

An authenticated isolated lifecycle verified a viewport-bounded Headless UI
tone menu, red inline validation, create with an empty moderator list, edit of
the entry-tone mode, and cleanup. The first failed locator left one disposable
record; that exact test artifact was subsequently deleted through the same
isolated headless UI.

Bridge credentials, `domain`, external `flags`, read-only media-server
`focus`, and arbitrary nested `controls`/`profile` objects are intentionally
not operator-editable. They remain Switch-owned or advanced opaque data rather
than unsafe free-form inputs in the simplified form.

The 2026-08-31 drift audit corrected two additional mismatches. Kazoo models
both `member.pins` and `moderator.pins` as arrays, so GridPBX now accepts up to
20 unique 1–32 digit PINs per role through one comma/space-separated shared
control. PIN values remain write-only: public requests carry replacement
lists, public responses expose only configured booleans, and an explicit clear
writes an empty list. Existing PIN lists are omitted from ordinary edits and
therefore remain unchanged.

Conference edits now use Kazoo's native recursive `PATCH` operation instead of
the full-document `POST` merge path. The installed runtime merges only supplied
public fields and treats `null` as deletion. This preserves write-only PINs,
external `flags`, bridge metadata, arbitrary nested `controls`/`profile`
configuration, and other unknown fields without retrieving secrets into
GridPBX. The shared Basic/Advanced tabs follow Monster's workflow without
weakening the installed-schema contract. Basic contains identity, owner, and
write-only member access; Advanced contains conference-server identifiers,
participant and moderator behavior, sounds, and only the schema-backed bounded
`profile_name`, `caller_controls`, and `moderator_controls` references. It does
not expose a raw JSON editor.

One final isolated lifecycle used disposable Conference
`E2E conference 7872292` (public UUID
`415c1866-9287-4965-89c0-e8983b34cdf6`). The browser selected an account-scoped
Extension UUID, created two member and two moderator PINs, set all three
advanced references to `default`, disabled the entry tone, then replaced only
the member PIN list, enabled member deaf mode, restored the entry tone, and
reopened the authoritative values. A bounded raw observer confirmed the Switch
owner reference differed from the public Extension UUID, both initial PIN
arrays were correct, the moderator PIN list survived the member-only update,
and injected external `flags` plus an unknown nested `controls` marker survived
the typed edit. Neither raw owner reference, PIN value, nor marker appeared in
the public response or UI. The focused headless test passed in 52.7 seconds,
browser cleanup soft-deleted the MySQL projection, and the authoritative active
Switch collection contained no Conference with the exact disposable name.
Kazoo's direct deleted-resource GET materializes a schema-default skeleton, so
active-collection absence—not that synthetic skeleton—is the active-resource
cleanup assertion.

The 2026-09-01 operational slice adds Kazoo-compatible room `lock` and
`unlock` commands without treating an asynchronous command as completed
state. Laravel performs a fresh private Switch Conference read first, projects
that authoritative runtime snapshot, rejects a lock when no participants are
active, and permits unlocking a room Switch still reports as locked. The
public command response contains only `accepted`, the requested action, and a
safe message; raw Switch identifiers and command payloads remain server-side.
Accepted and failed attempts are audited, and the Vue inventory invokes the
existing queued Conference reconciliation after acceptance. Focused SDK tests
assert the exact Kazoo `PUT` command payload, API tests cover active lock,
inactive rejection, inactive unlock, authorization and audit behavior, and
the Pinia test verifies command-followed-by-sync orchestration.

The same operational boundary now supports live single-participant controls.
Participants are requested directly from Kazoo's Conference participant
endpoint and are never projected into MySQL. The Switch SDK reduces every
participant to a strict public allowlist before Laravel replaces the raw
participant ID with an encrypted, account-and-Conference-bound handle that
expires after five minutes by default. Mute/unmute, deaf/undeaf, and kick
requests decrypt that handle only on the server, fetch the current room again,
and reject a participant that has already left before sending the Kazoo
command. Audit metadata contains neither caller identity nor the raw
participant ID. The Headless UI live-room drawer derives the inverse control
from current speak/hear state, uses inline confirmation for kick, and refreshes
the authoritative participant list after acceptance. Focused verification
passed with focused Switch SDK, Laravel, Vue, and type checks. Native room-wide
mute/unmute and deaf/undeaf now use a separate high-impact workflow: the UI
previews the eligible non-moderator count, requires explicit confirmation, and
sends the observed room and target counts. Laravel re-reads the room under the
same command lock and rejects a stale preview before issuing Kazoo's atomic
participants command. Kazoo itself skips moderators and participants already
in the requested state. Accepted/failed attempts contain only safe aggregate
audit counts, and the live room refreshes afterward. Kazoo does not expose a
reliable per-participant completion result for this command, so the response
correctly reports asynchronous acceptance rather than fabricated partial
success. Vue follows acceptance with at most four live-room observations over
750 ms. It reports fully observed, partially/pending, or changed-room status
using aggregate counts and never exposes participant identifiers in the
result. Focused store coverage exercises both immediate observation and the
complete bounded pending path, and the isolated headless walkthrough verifies
the observed-state notice. The live-room panel additionally uses a reusable
five-second visibility-aware poller: it runs only while the panel and browser
tab are visible, pauses during participant commands, bulk reconciliation, and
media playback, resumes with an immediate refresh, prevents overlapping
requests, and cleans up on close/unmount. Focused fake-timer tests cover active,
paused, hidden, resumed, and stopped states; isolated headless coverage verifies
the background refresh. Bulk kick and dial-out remain intentionally disabled.

An isolated authenticated headless walkthrough now exercises the live-room
drawer with a simulated active Switch feed. It verified the participant status
presentation, opaque-handle-only mute and undeaf requests, authoritative
post-command refresh, and absence of raw participant/call markers without
originating a call or mutating Switch. The focused scenario passed in 1.3
seconds with no console, page, or HTTP 5xx errors. Compose startup also exposed
that private organization-logo runtime uploads were entering Docker's build
context; `grid-api/storage/app/private` is now excluded without weakening the
directory's private permissions.

The follow-up command audit compared the installed `cb_conferences` runtime,
generated Conference documentation, and `conferences.dial` schema. Kazoo media
play accepts a Media ID or a URL for a whole room or individual participant.
GridPBX prohibits URLs and accepts only a projected account Media UUID,
resolves it server-side, requires a streamable `audio/*` asset, refreshes the
active room or participant, requires confirmation in both its strict Zod and
Laravel request contracts, rejects raw URL fields, audits safe metadata, and
treats the HTTP 202 response only as acceptance. The bounded
whole-room and single-participant playback slice is implemented end-to-end.
Focused SDK coverage verifies the exact nested Kazoo payload and paths; API
coverage verifies account scope, media capability, active membership, opaque
participant resolution, authorization, safe responses, and audit metadata.
Vue component/store coverage and the isolated authenticated headless scenario
verify the confirmation workflow and that only a public Media UUID plus opaque
participant handle cross the browser boundary. No audible live-room command
was sent during isolated acceptance. The 2026-09-01 confirmation follow-up
passed one focused SDK test with 4 assertions, 5 Laravel playback tests with 38
assertions, 2 Vue files with 6 tests, Vue and isolated E2E TypeScript checks,
and one isolated headless scenario covering both room and participant targets.

Kazoo dial-out is materially different and stays disabled. It accepts raw
Device/User IDs, phone numbers, arbitrary SIP URIs, participant flags, caller
ID values, profiles, target call IDs, and timeouts. The installed documentation
explicitly states that phone-number legs pass through normal billing and
limits. GridPBX will not expose this generic contract until destinations are
public/account-scoped, external numbers and caller ID are authorized, billing
and limits are preflighted, rate limits and explicit confirmation are present,
requests are idempotent, and call results can be reconciled without exposing
raw call/job/endpoint identifiers.

### Temporal Rule and Rule Set follow-through (2026-08-29)

Temporal Rules now follow the current `temporal_rules.json` contract rather
than treating the legacy schedule form as the payload definition. Name and
cycle are the only upstream-required fields. The API applies the Switch
defaults for omitted interval and recurrence arrays, does not impose an
upstream-unsupported interval maximum, and retains the schema bounds for day,
month, ordinal, weekday, and time-window values. The Vue form conditionally
shows weekday, day-of-month, ordinal, and month controls for the cycles where
they are meaningful without turning optional Switch properties into invented
required fields.

The form uses a domain composable, Zod, viewport-bounded Headless UI cycle and
ordinal choices, shared red invalid styling, and inline-only errors. Invalid
day tokens are reported instead of being silently discarded. Rule Set
membership is selected with public Rule UUIDs and now has an explicit ordered
list with move controls, matching the ordered `temporal_rules` payload.

`enabled` is not an ordinary CRUD field. Force active, force inactive, and
resume schedule use the confirmed, audited command endpoint and map to
`true`, `false`, and an explicit `null` PATCH respectively. Laravel prohibits
operator attempts to write `enabled` or external `flags` through CRUD. A
normal edit preserves an existing override, and both Rule and Rule Set edits
preserve Switch-owned flags from the redacted `switch_json` snapshot. The
editable fieldsets remain locked until an operational command finishes so a
late projection refresh cannot overwrite user input.

Rule Set commands apply the selected override to every resolved member Rule
under an account-scoped lock and attempt compensation after a partial failure.
Effective status is a GridPBX projection evaluated in the account timezone;
it is clearly separated from the persisted override and does not claim to be
a Switch runtime status endpoint. Delete validation prevents removing Rules
used by Rule Sets or Callflows and prevents removing Rule Sets used by
Callflows.

Focused Switch, Laravel, Vue, and isolated authenticated Playwright checks now
cover schema defaults, external-flag preservation, CRUD/command separation,
inline validation, conditional fields, ordered membership, create/edit,
force active/inactive, reset, and cleanup. The final isolated run passed both
tests without console, page, or server errors. Twelve stale disposable Rules and
four stale disposable Rule Sets from earlier failed locator attempts were
removed by exact `E2E hours`/`E2E schedule` prefixes; no non-test records were
deleted. The cleanup pass also exposed and corrected orphaned membership rows
after soft-deleting a Rule Set; new deletes remove those rows, and historical
memberships whose parent set is already deleted no longer block Rule cleanup.

The 2026-08-31 drift re-audit confirmed that the GridPBX form still covers the
complete installed schema while intentionally exceeding Monster only where the
installed contract is broader: GridPBX supports schema-defined `date` and
`daily` cycles, whereas the installed Monster form offers weekly, monthly, and
yearly only. Monster's legacy `wensday` output remains normalized to canonical
`wednesday` publicly, and the installed runtime accepts both spellings. No new
operator field or raw advanced editor was justified.

Normal Rule and Rule Set edits now use Kazoo's native recursive `PATCH` rather
than full-document `POST`. Rule PATCH includes explicit `null` only for managed
nullable schedule fields so changing cycles clears stale day/weekday/month/
ordinal constraints; it does not reset an omitted operational override. Rule
Set PATCH replaces the ordered membership while leaving external `flags` and
unknown properties untouched. Focused SDK evidence covers the HTTP methods,
explicit clears, and omission of unmanaged fields; Laravel continues to reject
operator-supplied `enabled` and `flags` while preserving the projected values
for compensation.

One isolated disposable lifecycle created and edited
`E2E hours 48475979 updated`, exercised force inactive and resume schedule,
created and edited `E2E schedule 48475979 updated`, exercised Rule Set force
active and reset, reopened the edited Rule Set, and deleted both resources. The
single headless test passed in 6.4 seconds. Independent checks confirmed both
MySQL projections were soft-deleted and neither exact name remained in the
active Switch collections.

The 2026-08-31 presentation audit confirmed both editors should remain
Basic-only. Installed `temporal_rules.json` contains the visible name, cycle,
interval, recurrence selectors, anchor date, and time-window fields; `enabled`
is managed through the separate audited override controls and external flags
remain hidden and preserved. Monster places every editable Rule field in its
sole `#basic` panel. Although its generic header includes Basic/Advanced
buttons, `winkstartTabs` hides them when the template has fewer than two tabs.

Installed `temporal_rules_sets.json` contains only name, ordered
`temporal_rules` membership, and hidden external flags. Monster's Rule Set
editor likewise contains only name and its ordered item selector, with no tab
control. GridPBX therefore keeps each compact workflow together and does not
add an empty Advanced screen. The two focused panel tests now lock this
intentional absence while retaining inline validation.

The 2026-09-01 responsive and accessibility pass kept that payload and form
scope unchanged. The Rules and Rule Sets inventories now have accessible table
names, scoped headers, announced loading and failure states, keyboard-operable
record names, and table-local horizontal scrolling. Header and search actions
remain inside a 390-pixel viewport. One isolated non-mutating headless scenario
rechecked the schema-conditional fields, Cycle/Every alignment, inline errors,
both inventories, zero temporal writes, and absence of browser or server errors.

### Blacklist follow-through (2026-08-29)

Blacklist now uses the shared domain composable, Zod, inline-only error, and
red invalid-control baseline. Its name and blocked-number fields are migrated
to the shared accessible text/textarea components, and errors attach directly
to their controls. The submit path uses `novalidate` so browser-native prompts
cannot replace the application feedback, and actionable API field errors no
longer also create a global mutation alert.

The current schema defines a name, a number-keyed object, anonymous-caller
behavior, and external `flags[]`. GridPBX intentionally accepts only canonical
E.164 number keys even though the upstream object does not constrain its keys;
this is a bounded product policy for predictable caller-ID matching, not a
claim that the schema requires E.164. Account activation remains a separate
account-setting mutation coordinated by Laravel. External flags are prohibited
from operator input and are now preserved from the redacted `switch_json`
during full Switch updates and rollback attempts.

Focused Vue, TypeScript, Laravel, Switch package, and isolated authenticated
headless checks pass. The visual run verified shared red invalid controls,
inline-only errors, and clean browser/network state without creating a live
Blacklist.

The 2026-08-31 presentation audit confirmed that Blacklist should remain a
single Basic-only form. Monster exposes no Basic/Advanced selector and renders
name, anonymous blocking, and number membership together. The installed schema
adds only external `flags`, which remain hidden and preserved; GridPBX's
account activation switch coordinates a separate account setting. An empty
Advanced tab would not represent a Switch or Monster workflow.

The 2026-09-01 responsive and accessibility pass left that contract unchanged.
The inventory now has an accessible table name, scoped headers, announced
loading and failure states, keyboard-operable Blacklist names, and table-local
horizontal scrolling. Header, search, and slide-over controls remain inside a
390-pixel viewport. A stubbed public-UUID isolated headless run verified edit
opening by keyboard, shared inline validation, zero Blacklist or sync writes,
and no browser or server errors.

### Fax Box follow-through (2026-08-29)

Fax Box now follows the same form baseline. Its owner, caller-ID number, and
timezone controls use viewport-bounded Headless UI listboxes populated from
public Extensions, projected account phone numbers, and supported timezone
identifiers. Existing caller IDs or timezones absent from the current options
are retained as clearly labeled projected values. A null timezone inherits the
account default instead of the create form forcing UTC.

Every editable scalar and list has matching Zod/Laravel validation, shared red
invalid styling, and inline-only field errors. The current schema's default of
one retry is retained rather than copying Monster's older default of three.
The caller-ID selector uses projected account numbers to prevent arbitrary
outbound identity entry.

Email notification recipients, SMTP permissions, custom SMTP address, fax
identity/header, retry count, and T.38 are represented as typed virtual values
from `switch_json`; no JSON-key-per-column expansion was added. Existing
callback and SMS notification objects are preserved during full writes but
remain hidden: callbacks require an outbound URL/SSRF policy and SMS requires
confirmed provider capability. External `flags[]` are also preserved and
prohibited from operator input. System-owned attempts and the generated SMTP
address remain read-only.

Focused Vue, TypeScript, Laravel, Switch package, and isolated authenticated
headless checks pass. The visual run verified a non-clipping owner selector,
multiple red invalid controls, inline-only errors, and clean browser/network
state without creating a live Fax Box.

The 2026-08-31 presentation follow-through now mirrors Monster's explicit
Basic/Advanced mode. Basic contains `name`, account-scoped owner selection,
and inbound/outbound notification emails. Advanced contains caller-ID and
printed Fax identity, SMTP addressing and sender permissions, retries,
timezone, and the installed-schema `media.fax_option` T.38 switch. Generated
SMTP addressing stays read-only, while callbacks, SMS, flags, attempts, and
unknown nested values stay hidden and preserved. Client and API errors select
the owning tab, with Basic errors taking priority. One focused Fax Box
component test, Vue and isolated E2E typechecks, and the single mocked,
non-mutating isolated browser test passed; the browser verified both tabs,
bounded owner selection, and cross-tab inline validation without creating a
Fax Box.

### Fax message operations audit (2026-08-31)

The installed `faxes` schema requires sender and destination numbers, permits
zero to four retries, and supports email or SMS status recipients. A document
can be uploaded as multipart content or supplied as an HTTP URL. The URL form
can select GET or POST and can provide request content, content type, Host, and
Referer values. The installed attachment fetcher sends that server-side
request directly and has no visible destination allowlist, private-address
block, redirect policy, or DNS-rebinding defense. GridPBX therefore does not
expose URL-based sending.

Multipart sending is also asynchronous rather than an atomic upload command.
Crossbar first saves a global Fax job, returns HTTP 202, and spawns background
attachment processing. The running defaults accept eight content-type entries,
including prefix-based image, OpenXML, and OpenDocument families; store the
original, faxable TIFF, and generated PDF; retry attachment storage five times;
allow ten active outbound jobs per account; serialize jobs globally by
destination; and allow a running job to remain controlled for up to one hour.
Caller-ID validation is enabled, but that does not supply document malware
controls, billing policy, recipient authorization, idempotency, delivery audit,
or an authoritative outcome after a timeout or background conversion failure.

Forward and resubmit are new-job operations, not edits. Crossbar copies the
historical document to a random new Fax ID, merges public request fields,
removes prior transmission results, resets attempts, and marks the copy
pending. Retrying either request can therefore create duplicate transmissions.
Message deletion permanently removes the document record, while attachment
deletion is a separate permanent operation. Retention, legal hold, exact-message
confirmation, binary/metadata reconciliation, and immutable audit must cover
both deletion paths independently.

Monster provides history, parallel bulk delete, and outbound resubmit
workflows, with browser confirmation only. Its bulk-delete handler collects
the selected IDs before confirmation and appends them again inside the confirm
callback, so the reference workflow can submit each selected ID twice. The
installed forward action is not surfaced there, and no in-app Send Fax form was
found. These workflows are useful interaction evidence but are not safe
idempotency, retention, or authorization contracts.

Live read-only checks against account `4bb372131dddafedcdb142ea3a0ccf2f`
found available Fax Box, inbox, and outbox collections with zero records. The
active outgoing-job collection returned HTTP 503. Effective non-secret defaults
confirmed caller-ID validation, destination serialization, URL-document
storage, original/TIFF/PDF retention, five storage retries, a ten-job account
limit, a 40-second endpoint timeout, and a one-hour job wait. No Fax message,
attachment, or job was created, copied, transmitted, forwarded, resubmitted,
or deleted.

GridPBX now publishes one strict account-scoped history capability object with
only `switch_supported`, fixed `enabled = false`, and a safe reason for Send,
Forward, Resubmit, message deletion, and document deletion. No endpoint URL,
queue state, raw Fax ID, Switch account ID, document location, provider data,
or message content enters that contract. Vue validates it with a strict Zod
schema and renders five informational policy gates without action buttons or
mutation clients.

Focused verification passed with the one affected Laravel Fax test (10
assertions) and two Vue files with four tests. Vue typecheck and isolated E2E
TypeScript checks passed. One isolated authenticated headless Playwright Fax
test passed in 2.4 seconds and confirmed all five visible policy gates, the
absence of Send/Forward/Resubmit/Delete controls, the existing Fax Box form
behavior, and clean browser/network state. The disposable UI listener was
stopped after the run.

### Phone Number follow-through (2026-08-29)

Phone Numbers do not currently have an ordinary CRUD form, and that is
intentional rather than an omitted application of the shared form controls.
The current Switch schema describes CNAM, E911, and porting data, but runtime
permission comes separately from `_read_only.features.available`; older
deployments may instead return `_read_only.features_available` or a root
`features_available`. GridPBX now recognizes all three versioned shapes.

The detail slide-over exposes an allowlisted virtual view of CNAM, E911 address
and notification state, and a minimal porting summary from redacted
`switch_json`. Provider location IDs, coordinates, billing identifiers,
comments, and the raw document stay server-side. Callflow assignment remains
owned by the Callflow editor so two forms cannot race to rewrite the same
relationship.

The operational capability panel deliberately distinguishes Switch feature
availability from GridPBX write permission. CNAM, E911, purchase, activation,
port, release, and messaging commands remain disabled until their provider,
billing, compliance, confirmation, authorization, and audit contracts are
configured. They will use dedicated command panels rather than a generic JSON
form. Focused package, API, Vue, TypeScript, and isolated authenticated
headless checks pass without issuing a carrier mutation.

### Phone Number CNAM mutation audit (2026-08-31)

The installed `phone_numbers` schema accepts only `cnam.display_name` as a
one-to-fifteen-character string and `cnam.inbound_lookup` as a boolean. Monster
shows those two controls only for an in-service number, validates the same name
length, removes a blank display name, and sends the fetched number document
back through the generic number update workflow. That describes the operator
workflow but does not establish safe carrier completion semantics.

Kazoo's current account number update uses recursive `PATCH` merging through
`knm_phone_number:update_doc/2`; a future typed CNAM command must use that path
so unrelated public fields and unknown nested CNAM fields survive. `POST`
instead resets the public document and is not an acceptable CNAM edit path.
Provider execution happens before the number is saved. A dry run can return a
service quote and Crossbar then repeats the operation only after charges are
accepted. State changes also update the services ledger and can fail for
credit or provider reasons, so this is not an ordinary idempotent form save.

The running deployment has no account-level CNAM provider override and inherits
`knm_cnam_notifier`. That provider marks inbound/outbound feature state, but an
outbound name change only publishes an asynchronous CNAM notification when the
request is not a dry run; it supplies no carrier acknowledgement or completion
status. The audited account contains no phone numbers, so no disposable carrier
mutation was possible. GridPBX therefore continues to expose only the
allowlisted CNAM projection and per-number selectable capability. The public
reason now states that selectable does not mean carrier-confirmed, while raw
provider state and unknown nested fields remain private.

CNAM mutation stays capability-gated until a target provider and completion
contract are configured, quote and charge confirmation are represented
server-side, permission and immutable audit rules are approved, retries cannot
duplicate billable work, and authoritative resynchronization can distinguish
pending, completed, rejected, and indeterminate outcomes. No live number was
created or changed during this audit. Focused verification passed with the one
affected Laravel API test (25 assertions), the one CNAM detail component test,
the isolated E2E TypeScript check, and one isolated headless Playwright test in
1.5 seconds. The browser confirmed the allowlisted values, the explicit
notification-only gate, and the absence of purchase, release, or port actions.

### Phone Number E911 mutation audit (2026-08-31)

The installed `phone_numbers` schema requires `street_address`, `locality`,
`region`, and `postal_code` whenever an E911 object is present. Region is
exactly two characters; caller name, when supplied, is at least three
characters; and notification recipients are an email array. Provider-owned
status, activation time, coordinates, location ID, plus-four, and legacy
address data appear in the same object but are not operator-owned fields.

Monster's reference form edits street and extended address, city, state,
postal code, and notification emails for an in-service number. It sends the
postal code to Google Geocoding to suggest city/state, handles Kazoo's
multiple-address response by asking the operator to choose, and sends the
fetched number document through the generic update workflow. Its removal guard
only counts E911-featured numbers in the browser and refuses to remove the last
one. That UI-only count is useful workflow evidence but is not an authoritative
dependency or concurrency control.

Kazoo resolves E911 through an account/reseller provider selection and the
same dry-run service quote path as other billable number features. The default
Dash provider validates and geocodes an address, can return multiple choices,
then adds and provisions a location. Its configured HTTP client disables TLS
certificate verification, allows 180-second connect/response timeouts, and can
write address-bearing request/response XML to `/tmp` when provider debug is
enabled. Removal deactivates the local feature even when the upstream removal
reports an error. Other installed providers have different compensation risks:
Telnyx removes the previous address before creating and assigning its
replacement and cleans old address records asynchronously, while Vitelity uses
separate validate, update, lookup, and delete calls. A generic retry therefore
cannot be assumed safe or idempotent.

Emergency routing is coupled to this feature. Stepswitch can restrict the
emergency caller ID to the account's E911-enabled numbers and otherwise choose
another enabled number, but `ensure_valid_emergency_cid` defaults to false. The
running deployment leaves that safeguard unset. It also inherits
`knm_dash_e911`, has no configured Dash username or password, has provider
debug unset/false, and has no account-level E911 provider override. The audited
account contains zero phone numbers, so no disposable provisioning or removal
could be performed.

GridPBX therefore keeps E911 mutation unavailable. The public API/UI continues
to expose only the allowlisted address, notification, and status projection;
provider location IDs, coordinates, provider state, legacy data, and the raw
Switch document remain private. Its capability reason now states that Kazoo
selectability does not establish provider readiness or safe emergency caller-ID
routing. A future command must use typed recursive `PATCH`, preserve unknown
nested data, treat geocoded choices as short-lived server-owned state, require
approved address/privacy and notification policy, confirm billing and exact
number/removal intent, recheck every emergency-caller-ID dependency under an
account lock, use verified TLS and bounded provider timeouts, record immutable
audit, and reconcile completed, rejected, partially compensated, and
indeterminate outcomes before reporting success. No E911 or Phone Number write
was issued during this audit.

Focused verification passed with the one affected Laravel Phone Number test
(29 assertions), the one Phone Number detail component test, the Vue typecheck,
and the isolated E2E TypeScript check. One isolated authenticated headless
Playwright Phone Number test passed in 1.9 seconds against a disposable UI
listener using the existing same-scheme local API. The browser confirmed the
allowlisted E911 projection, the explicit provider-readiness gate, and the
absence of purchase, release, or port controls. The disposable listener was
stopped immediately after the test.

### Phone Number form-drift re-audit (2026-08-31)

A fresh entity-form comparison found no disconnected Advanced controls because
Phone Numbers intentionally do not expose a generic edit form. The installed
schema still groups public CNAM, E911, and porting data in the number document,
but runtime mutation adds provider selection, recursive merge semantics,
dry-run quotes and charge acceptance, service-ledger effects, emergency-caller
ID dependencies, state transitions, and potentially destructive carrier work.
Monster's conditional forms remain useful workflow evidence; they do not make
those operations safe or complete in this deployment.

The current detail panel therefore remains the correct bounded surface. It
shows allowlisted state, feature, CNAM, E911, porting, and public Callflow
assignment values while rendering explicit non-writable capability reasons.
It deliberately renders no Basic/Advanced selector: those labels describe
editable field groups, while this panel is a read-only projection and each
future carrier change requires its own confirmed operation workflow.
No raw number document, provider identifier, billing data, Switch account ID,
or internal database key is returned. Assignment remains in the Callflow
workflow, preventing two forms from owning the same relationship. No Phone
Number, carrier, billing, or emergency-service mutation was attempted.

Focused verification passed with five Switch package tests / 26 assertions,
three Laravel API tests / 37 assertions, the one Vue detail component test,
isolated E2E TypeScript typecheck, and the single isolated authenticated
headless detail test in 2.0 seconds. The browser confirmed the allowlisted
values, all three policy-gated feature operations, the absence of purchase,
release, and port buttons, the absence of artificial Basic/Advanced tabs, and
clean console/network state. The disposable UI listener was stopped after the
run.

### Media follow-through (2026-08-29)

Upload-backed Media create/edit and audio replacement now use domain
composables, Zod, `novalidate`, shared red invalid controls, and inline-only API
errors. Submit remains available while fields are empty so validation can mark
the exact name or file control instead of silently disabling the action or
showing a browser-native prompt. Client and Laravel validation agree on MP3,
WAV, and OGG files with a 5 MB maximum.

The account music-on-hold choice now uses the shared viewport-bounded Headless
UI listbox and public Media UUIDs. Binary data continues to live in Switch;
GridPBX stores only allowlisted metadata and streams authorized audio through
the API.

Metadata updates use a typed read-merge-write boundary. Existing
`content_type`, `prompt_id`, `source_id`, `source_type`, and TTS text/voice are
retained from redacted `switch_json`, while operator requests are prohibited
from supplying those hidden fields. New generated TTS and recording flows stay
capability-gated because they require a configured generation/runtime provider,
not just fields present in `media.json`. Focused Vue, Laravel, Switch package,
TypeScript, and isolated authenticated headless checks pass without creating
or replacing live media.

### Media form-drift re-audit (2026-08-31)

The installed `media` schema reconfirmed the current bounded upload form:
name, optional description and language, streaming choice, and a required
MP3/WAV/OGG file up to 5 MB. `media_source`, content metadata, prompt/source
links, and TTS remain Switch-owned or belong to dedicated generation workflows.
Monster's upload/TTS conditional remains workflow evidence; generated TTS and
recording stay gated because schema presence alone does not establish a safe
provider or live-call contract.

Runtime inspection found a preservation gap beneath the otherwise complete
form. Media update validation calls `crossbar_doc:load_merge`, which retains
only existing private fields before saving the submitted public document. The
previous mutation code reconstructed known hidden fields but could erase future
or external public keys, unknown nested TTS options, and returned content length.
The typed SDK/API boundary now merges safe preserved options before modeled
fields, retains a positive Switch-owned `content_length` when present, and
removes private/read-only keys plus all redaction-marker values. None of this
preservation metadata is accepted from or returned to Vue.

Focused verification passed with three Switch SDK tests / 24 assertions, the
single affected Laravel metadata-update test / 11 assertions, two Media Vue
form tests, isolated E2E TypeScript typecheck, and the single isolated headless
Media form/listbox test in 3.8 seconds. A disposable connected lifecycle then
created Media with a nested unknown marker, uploaded an 844-byte WAV, projected
it under a public UUID, edited its metadata through the production mutation
service, reopened the raw document, and deleted it. The raw marker and nested
value survived; the raw resource ID and unknown data never entered the public
resource. This Kazoo build returned nullable `content_length` both before and
after the edit despite the upload, so GridPBX preserved that authoritative
nullable value rather than fabricating a byte count. Independent cleanup found
two historical soft-deleted audit projections, zero active matching MySQL rows,
and zero matching active Switch Media documents.

The presentation follow-through now mirrors Monster's real Media view split.
Basic contains upload-backed name, description, language, and audio selection;
Advanced contains the installed `streamable` option. Monster's Basic TTS
branch remains capability-gated in GridPBX, while its existing schema values
and prompt/source metadata remain hidden and preserved. Client and API errors
select the owning tab. One focused Media component test, Vue and isolated E2E
typechecks, and the single mocked non-mutating Media browser check passed.

### Caller-ID List form-drift re-audit (2026-08-31)

The installed `lists.json` schema requires only `name` and permits optional
`description` and `org`. Its separate `list_entries.json` schema requires the
raw parent `list_id` and permits number, pattern, display-name, name, type, and
profile data. The current Monster checkout contains no standalone Caller-ID
List editor, so it supplies no stronger Basic/Advanced workflow evidence.

GridPBX now groups the fields by their installed-schema purpose using the same
shared control as Device and the other audited forms. Basic contains the list
name and the operational number/prefix or safe-regex match entries. Advanced
contains the optional description and organization metadata (`organization`
publicly, mapped to Kazoo's `org`). Validation and API errors select the owning
tab, with Basic taking priority when matching errors and metadata errors occur
together.

The audit also corrected the installed full-update boundary. Both List and
entry `POST` validation finish through `crossbar_doc:load_merge`, which retains
private fields but can discard omitted unknown public values. The typed Switch
client now performs an authoritative read before each update and merges safe
unmodeled List and entry fields beneath the submitted modeled values. Private,
read-only, and redaction-marker values are filtered; clearing modeled optional
fields still removes them.

List and entry resources keep separate redacted Switch snapshots, and the
account-scoped public List and entry UUIDs remain the only resource identities
exposed. The schema-required raw `list_id` is supplied privately by the adapter
and never appears in UI input or public output. Preserved fields remain
server-side rather than becoming editable form controls.

Focused verification passed with two Switch SDK tests / 14 assertions, three
Caller-ID List component tests, Vue and isolated E2E TypeScript checks, and the
isolated mocked headless form test. The SDK check covers safe unknown-field
retention and private/redacted-field rejection for both resource types. The
browser confirmed the Basic/Advanced field placement, return to Basic for
name/pattern errors, viewport-safe match-type listbox, and clean console and
server-response state without mutating a live List.

The 2026-09-01 responsive and accessibility pass kept the installed-schema
mapping unchanged. The inventory now has an accessible table name, scoped
headers, announced loading and failure states, keyboard-operable List names,
and table-local horizontal scrolling. Header, search, entry-add, tab, and
slide-over controls remain inside a 390-pixel viewport. A stubbed public-UUID
isolated run verified keyboard edit opening, Basic/Advanced visibility, inline
safe-regex errors, zero List or synchronization writes, and clean browser and
server state.

### Call activity follow-through (2026-08-29)

Call History and Recordings are read/display workflows rather than artificial
CRUD forms. Their search, direction, outcome/audio availability, date, and
duration controls now follow the same form baseline: Headless UI-backed
choices, domain composables, `novalidate`, matching Zod/Laravel constraints,
red invalid borders, and field-local messages. Number inputs are normalized at
the Zod boundary because Vue emits numeric runtime values for `type="number"`
even though URL query filters are serialized strings.

The 2026-08-31 presentation re-audit reconfirmed that these query controls are
list filters, not Basic entity fields, and the collapsible “Advanced filters”
area is not an Advanced entity tab. Installed `cb_cdrs` permits only GET for
collections, summaries, interactions, legs, and individual CDRs. Installed
`cb_recordings` permits collection GET and individual GET/DELETE; the latter is
a destructive retention/storage operation and remains policy-gated rather
than becoming an editable field. Monster's Call Logs workflow similarly uses
date/search filters, read-only detail, interaction legs, and CSV export with no
Basic/Advanced editor. GridPBX intentionally renders both detail panels as a
single read-only view with no Basic/Advanced selector or edit/delete actions.

The CDR detail panel resolves projected Recording summaries through public
UUIDs. Recording detail links back to its projected CDR, also by public UUID.
No numeric primary key, Switch resource ID, raw `switch_json`, media URL, or
storage credential crosses either response. Audio stays behind the existing
authenticated, account-scoped, audited range-stream endpoint. Deletion and
retention automation remain disabled pending approved policy. Focused API,
Vue, TypeScript, and isolated authenticated Playwright checks pass without
creating, mutating, or deleting call activity.

### Account projection follow-through (2026-08-29)

The Accounts placeholder is replaced by a main-page workspace showing the
selected account's public identity, organization, enabled state, realm,
timezone, and tenant-scoped counts for the primary projected resources. The
detail endpoint resolves only through authenticated organization membership
and returns `404` for another organization's account. Numeric/ULID primary keys
and the upstream Switch account ID remain server-side.

The first write-safe Account settings slice is available to platform,
reseller, and account administrators. It uses a typed Switch DTO for name,
legal organization, timezone, language, call waiting, do-not-disturb, outbound
privacy/rate display, internal/external/emergency caller identity, and
internal/external ringtones. External numbers resolve from public UUIDs in the
account's projected inventory; emergency choices are limited to E911-enabled
numbers. Unresolved current numbers are preserved unless an administrator
explicitly replaces or clears them. The complete returned Account
`data` document is redacted into MySQL `switch_json`; the form reads normalized
projection columns and never exposes raw JSON or upstream identifiers.

The form is a right-side Headless UI/Tailwind panel with a domain composable,
Zod, `novalidate`, shared red invalid controls, and inline field messages.
Refresh and update are audited. Enable/disable is a separate administrator
command requiring the exact account name; disabled accounts remain visible so
the operation is reversible.

Account restrictions now come from the connected Switch number-classifier
endpoint and include stored unknown classifications without hard-coding a
deployment's numbering plan. Account and endpoint call-recording defaults use
the complete direction/network matrix with bounded format, duration, trigger,
sample-rate, and time-limit controls. Existing recording storage URLs remain
hidden from the API and are preserved server-side during settings updates.
Dial plans and request formatters are now typed virtual fields backed by
`switch_json`, with bounded guided rows, portable safe-regex validation, and
lossless preservation of unknown server-owned rule options. Account preflow
uses a projected Callflow public UUID, including explicit preservation of an
unresolved current reference. Metaflow binding digit, timeout, and call-leg
defaults and supported recursive number/pattern action trees are editable
through the shared Device/Account editor. Media, Callflow, Device, and Extension
references use account-scoped public UUIDs; unsupported or unresolved roots are
locked and preserved verbatim. Realm, asserted identity, User metaflow wiring,
billing/top-up, zones, and notifications remain explicitly gated.
Focused SDK, Laravel, Vue, TypeScript, and isolated authenticated Playwright
checks pass.

### Account hierarchy and reseller presentation follow-through (2026-08-31)

Installed Kazoo account-tree behavior defines `tree` as raw account IDs ordered
from the most ancestral account to the immediate parent and provides separate
parents, tree, children, and descendants reads. Monster uses that hierarchy for
account browsing and descendant administration; it is not an Account settings
Basic/Advanced field group. GridPBX likewise keeps Reseller administration as
a separate read-oriented workspace with public Account UUID relationships,
projection coverage, service/billing-owner health, and safe promotion/demotion
preflight. Raw Switch account IDs and numeric database keys remain private.

Authorized onboarding accepts only a short-lived opaque reference issued for
the current actor and reseller scope. It requires exact-name confirmation and
explicit acknowledgement that existing organization members inherit access,
then projects the already-existing Switch descendant, records an audit event,
and queues its service projection. It does not create, move, promote, demote,
or update the Switch account. The focused SDK test passed with 8 assertions;
13 Laravel hierarchy/onboarding tests passed with 159 assertions; four focused
UI files passed 9 tests; and one isolated authenticated headless scenario
passed in 3.2 seconds. The browser exposed no raw account identifier and
offered no reseller-role mutation control.

### Account Advanced-settings drift re-audit (2026-08-31)

The installed Account contract was re-read in full, including the referenced
call-waiting, caller-ID, recording, dial-plan, formatter, and metaflow schemas,
`cb_accounts` validation, `crossbar_doc` patch behavior, and Monster's Account
settings forms. The managed matrices and conditional resource selectors remain
schema-aligned. External and emergency caller identities still accept only
account-scoped public Phone Number UUIDs, preflow accepts only a public
Callflow UUID, and metaflow resources use their public account-scoped UUIDs;
Laravel performs every raw Switch-resource mapping privately.

Three concrete form drifts were corrected. Timezone validation now matches the
installed 5–32 character bound and the selector offers `Etc/UTC`, not the
schema-invalid `UTC` alias. Internal and external ringtone headers accept the
schema's complete 256-character limit. Outbound privacy now exposes Monster's
"Use Switch default" behavior as public `null`: Laravel accepts null, the typed
Switch DTO emits null, and Kazoo's recursive Account `PATCH` removes the
optional nested property. The raw virtual value `inherit` is never sent to the
installed schema. The language list now includes Monster's German and Russian
choices and safely retains an unknown current language instead of clipping it.

Unknown Account fields are not reconstructed by the UI. Installed Account
updates call `crossbar_doc:patch_and_validate/3`, whose recursive left merge
retains untouched public top-level and nested fields. Recording storage URLs,
unknown dial-plan and formatter options, and unsupported metaflow trees also
remain hidden and are explicitly preserved by Laravel. Account Music on Hold
and Blacklist activation remain DRY in their dedicated Media and Blacklist
workflows. Realm/asserted identity, billing/top-up, zones, notification state,
and voicemail callback URLs stay capability- or policy-gated.

Focused evidence passed with 4 SDK tests/21 assertions, 2 Laravel tests/42
assertions, 6 Account Zod tests, Vue typecheck, isolated E2E TypeScript
typecheck, and one isolated authenticated headless Account walkthrough (1.8
seconds). The browser selected Switch-default privacy, submitted public null,
and asserted that raw account and Switch identifiers were absent. This audit
did not mutate the live Account; installed runtime inspection and the typed
boundary tests establish the preservation and null-delete behavior without
risking shared tenant configuration.

The presentation follow-through now uses the shared outer Basic/Advanced
selector while preserving the existing focused recording sub-tabs. Basic
contains Account identity and locale, everyday calling defaults, ringtones,
privacy, and internal/external/emergency caller identity. Advanced contains
dynamic account restrictions, the account/endpoint recording matrix, dial
plans, request formatters, preflow, and guided metaflow activation/actions.
This follows Monster's separation of ordinary account/profile settings from
Accounts-manager policy and Callflows application settings; Monster has no
single combined Account editor whose tab labels should be copied literally.

Client and API errors select the owning outer tab, with Basic errors taking
priority when both groups fail. The existing account-scoped public Phone
Number, Callflow, Media, Device, and Extension UUID boundaries are unchanged,
as are recursive Account PATCH preservation, hidden recording URLs, and locked
unresolved metaflow trees. Two focused Account component tests, Vue and E2E
TypeScript checks, and the isolated mocked Account walkthrough passed. The
browser traversed both outer tabs, edited Advanced and Basic controls, returned
to Basic for the invalid account name, submitted the same public payload, and
sent no live Account mutation.

## Callflow guided-form audit

The current Callflow editor deliberately covers safe entry-point and root-target
mutations; it is not presented as a complete visual implementation of every
`callflows.*.json` module schema.

| Area | Switch contract | GridPBX treatment | Status |
| --- | --- | --- | --- |
| Route identity | `callflows.name` | Required, trimmed, maximum 128 characters, inline Zod/API errors | Implemented |
| New-route workflow | Unsaved Callflow document above `flow` | Create opens as a full-width, Switch-style blank Callflow workspace rather than a slide-over. The entry card collects the name and one or more projected Phone Number UUIDs, then the installed action palette selects one resource-backed root action. The server resolves its public account-scoped destination UUID to the raw Switch resource only at write time | Implemented and isolated-headless verified |
| Phone entry points | `callflows.numbers[]` | Projected inventory UUIDs only; create requires one, update may clear assignments; extension and non-inventory numbers remain preserved | Implemented |
| Feature-code inventory | Top-level `featurecode.name` / `featurecode.number`, `numbers[]` or `patterns[]`, and `flow` | A dedicated account-scoped page lists existing feature-code callflows as projected active and derives a readable code, action, category, and runtime dependency summary from the safe projection. Zod reduces each response to its public Callflow UUID and allowlisted display fields; raw Callflow/account/owner/resource IDs, private node data, and `switch_json` never enter the store or UI. Lifecycle controls are absent and all non-GET account requests are asserted to remain zero | Read-only foundation implemented and isolated-headless verified |
| Root destination | `callflows.action` plus selected module schema | Headless UI type/target selectors resolve public UUIDs server-side | Implemented for the allowlisted resource modules |
| Existing module data | selected `callflows.<module>.json` data | Retained when only the target of the same module changes; old data is discarded when intentionally changing module type | Implemented and package-tested |
| Children and unknown branches | recursive `children` object | Preserved losslessly by the Switch write DTO and displayed structurally | Implemented |
| Unsupported or unresolved root | module catalog and current projection | Locked in the editor response and API mutation path; no silent fallback target is selected | Implemented |
| Wildcard fallback branch | `children._` | Optional Headless UI selectors resolve public UUIDs server-side; create/replace/clear preserves sibling branches and same-module data | Implemented |
| Unsafe fallback subtree | nested, unsupported, or unresolved `children._` | Locked in the editor response and API mutation path and preserved losslessly | Implemented |
| Menu key branches | `children.timeout`, `children.0`–`children.9`, and `children.*` | Typed per-key operations with public UUID targets; add/replace/clear does not replace the full child map. Before the first save, a Menu root accepts a guided resource-backed palette drop on the first unused schema-editable key and reopens the same typed form with its projected public UUID selected | Implemented; focused component and isolated-headless create-workspace checks pass |
| Legacy and unsafe Menu branches | `children.#`, unknown keys, nested or unresolved key nodes | Displayed as preserved read-only state; `#` cannot be newly created | Implemented |
| Numeric branch JSON shape | numeric child object properties | Normalized as JSON objects in Switch writes, MySQL JSON, and API resources so `{"0": ...}` never becomes a list | Implemented and tested |
| Temporal Rule Set match | `data.rule_set`, `children.rule_set`, and `children._` | Shows ordered public Rule UUIDs for context; create/replace/clear resolves only public destination UUIDs and preserves additional temporal keys. The full-page create workspace previews the literal `rule_set` match branch before the first save | Implemented; focused component and isolated-headless create-workspace checks pass |
| Direct Temporal Rules | ordered `data.rules[]`, `children.<rule_id>`, and `children._` | Selects and reorders public Rule UUIDs, requires one public match destination per rule, maps raw branch keys only on the server, and explicitly clears removed rules while preserving unrelated children | Implemented; SDK, API, Zod, and isolated-headless tested |
| Callflow entry node | Document-level `numbers[]`, `patterns[]`, and `name` above `flow` | A distinct Kazoo-aligned top card displays the primary number/pattern and additional-entry count, then connects to the actual root action. New routes are authored in the full-page canvas rather than a create slide-over; the root popup uses public UUID selectors and can preview Menu keys plus the wildcard `_` fallback before the first save. Empty `_` and schema-editable Menu-key positions accept only guided resource-backed palette drops and reopen their existing public selectors; inline modules are rejected rather than accepted as arbitrary draft node JSON. Entry data is never synthesized into `flow` | Implemented; focused component and isolated-headless create-workspace checks pass |
| Visual route map | Recursive `flow.children` tree | Scroll-bounded connected nodes with semantic branch badges, centralized module-specific icons, and keyboard-accessible selection; unknown child keys become numbered preserved labels in the public contract while internal keys remain lossless | Interactive foundation implemented and headless-tested |
| Main-page editor placement | Full route graph and action palette | The graph uses the full available Callflow content width inside small responsive gutters rather than a narrow centered maximum. A compact categorized palette starts in a sticky Kazoo-style right rail, can be moved within the viewport, and has an explicit Dock control; typed mutation forms remain in right-side panels | Implemented and isolated-headless verified |
| Tree mutations | Recursive node and branch operations | Pointer drag-and-drop and the keyboard workflow move guided subtrees into empty public `_`, Menu digit/Star/timeout, and Temporal Rule Set branches. Guided palette cards are also draggable onto eligible nodes; a drop opens the same validated form and never writes until explicit submission. The node modal supports insert-before when the source default continuation is empty and swapping two disjoint subtrees. Guided public child subtrees can be removed only after explicit confirmation; root, preserved, unsupported, unresolved, no-op, and cyclic operations are rejected. Every mutation starts from the latest raw Switch document and preserves unrelated document, node-data, sibling, and child fields | Safe move, palette drop, occupied-position reorder, and child-subtree deletion boundaries implemented |
| Selected-node information | Public safe tree contract | An accessible modal shows public branch breadcrumbs, module, resolved label, reference state, child count, honest editability status, and safe move/reorder controls; it never displays raw node data or upstream IDs | Implemented and headless-tested |
| Guided reference action forms | Resource-backed `callflows.*.json` modules | Palette actions add User/Extension, Device, Voicemail, Callflow, Media, Directory, Group, Queue Member, Menu, Conference, Fax Box, and Temporal Rule Set nodes only to empty schema-valid branches. The selected-node modal retargets the same modules while preserving module data and complete children | Implemented across SDK, API, Zod, Vue, and isolated headless tests |
| Schema-backed inline action forms | `callflows.sleep`, `tts`, `collect_dtmf`, `send_dtmf`, `flush_dtmf`, `dead_air`, `language`, `manual_presence`, `group_pickup`, `page_group`, `receive_fax`, `ring_group_toggle`, `hotdesk`, `do_not_disturb`, `conference` service mode, `voicemail` check mode, `record_call`, `record_caller`, `missed_call_alert`, `set_cid`, `prepend_cid`, `set_alert_info`, `response`, `hangup`, `set_variable`, `set_variables`, `branch_variable`, and `branch_bnumber` | Zod and Laravel validate current schema fields plus defensive operational bounds in a right-side panel. Manual Presence accepts a bounded local presence ID or one explicit realm, the schema statuses `idle`, `ringing`, and `busy`, and `skip_module`; the visible create default is Monster's explicit `busy`, while omitted legacy status is read as the Kazoo schema default `idle`. Group Pickup follows Monster's single-target workflow and accepts exactly one account-scoped public Device, Extension, or Group UUID; the server resolves it to Kazoo's mutually exclusive `device_id`, `user_id`, or `group_id`, while private `approved_*` restrictions and unknown properties remain hidden and losslessly preserved. Ambiguous or unresolved existing targets stay read-only. Page Group accepts one to twenty distinct account-scoped public Device UUIDs, maps raw Kazoo endpoint IDs only on the server, and exposes only one-way/two-way audio plus `skip_module`; materialized timing values and unknown endpoint fields stay private and preserved, while user/group expansion, barge, unsafe timings, and unresolved endpoints remain read-only. Receive Fax accepts one account-scoped public Extension UUID, resolves it server-side to Kazoo's raw `owner_id`, writes `media.fax_option` as `auto`, `true`, or `false`, and supports `skip_module`; unknown nested `media` fields remain private and losslessly preserved, while unresolved owners remain read-only. Ring Group Toggle accepts only an account-scoped public Callflow UUID whose synchronized module summary contains `ring_group`; Laravel resolves the raw `callflow_id` only for Switch writes, while feature-code or non-ring-group targets are rejected and unavailable targets are read-only. Login, logout, and `skip_module` are the only public fields, and unknown node data remains private and losslessly preserved by the Switch DTO. Hotdesking is resource-free at design time and exposes only `action` (`login`, `logout`, or `toggle`) plus `skip_module`; raw or server-owned `id`, `interdigit_timeout`, and unknown node properties remain private and are preserved by typed edits. Do Not Disturb is also resource-free publicly and accepts only `action` (`activate`, `deactivate`, or `toggle`) plus `skip_module`; raw `id` and unknown node data remain private and lossless, with no public target mapping. Conference Service uses a public-only `service_mode: true` discriminator which Laravel removes before writing `conference` without a raw `id`; the configured Conference action remains a separate account-scoped public-UUID workflow. Only `skip_module` is managed, while unknown discovery settings stay private and lossless. Check Voicemail writes only resource-free `action: check` and `skip_module`, never accepts or exposes a mailbox `id`, and keeps Kazoo's caller-ID and single-mailbox auto-login flags private and server-owned. Missed Call Alert accepts public extension UUIDs or validated email addresses and maps extension recipients to Switch IDs only on the server. Alert-Info rejects CR/LF header injection. Response accepts final SIP error codes and optional cause text while preserving existing Switch-managed media. Hangup exposes only the schema-defined skip behavior. Set Variable is restricted to Kazoo's mapped `call_priority` variable, values `0`–`255`, and schema-supported channel choices; unsupported existing variable names are redacted, preserved, and read-only. Set CAV uses repeatable virtual key/value rows in the form but writes the exact schema-defined `custom_application_vars` object, with bounded safe keys, duplicate rejection, `export`, and `skip_module`; unsupported existing maps remain redacted and lossless. Branch Variable is restricted to `custom_channel_vars.call_priority`, exposes only the default and priority `0`–`255` result branches, and renders those branches as conditions rather than generic keys. Branch Bnumber exposes Kazoo's `hunt`, optional safe `hunt_allow`/`hunt_deny`, and `skip_module` fields; branch mode accepts exact dial-string children, while hunt mode is blocked until those exact branches are removed. The Switch DTO merges only managed public properties into existing node data and preserves the complete subtree. Recording URLs, HTTP methods, origins, media names, and other server-owned values are never exposed or accepted | Implemented across SDK, API, Zod, Vue, focused tests, and isolated headless walkthroughs; disposable live Call Priority, Branch Bnumber, Set CAV, Manual Presence, Group Pickup, Page Group, Receive Fax, Ring Group Toggle, Hotdesking, Do Not Disturb, Conference Service, and Check Voicemail create/edit/reopen/delete verification runs against Switch |
| Ring Group guided form | `callflows.ring_group` strategy, ordered endpoints, repeats, computed timeout, `ignore_forward`, `fail_on_single_reject`, `ringback`, `ringtones.internal`, `ringtones.external`, and `skip_module` | Accepts 1–20 distinct ordered account-scoped public Extension, Device, or Group UUIDs and maps them privately to Kazoo `user`, `device`, or `group` endpoints. Supports simultaneous, in-order, and weighted-random strategies, bounded delay/timeout, and 1–3 attempts; weighted-random requires an explicit `1`–`100` weight and zero delay for every member. Bridge flags are strict booleans. `ringback_media_id` accepts only synchronized streamable account audio; internal/external phone alerts are bounded CR/LF/NUL-safe `Alert-Info` strings. Unknown nested values remain private and preserved. Laravel computes the hidden attempt timeout with a 120-second cap. User/Group fan-out remains dynamic Kazoo behavior and the configured 20-member cap is not presented as a resolved-device cap | Mixed relationship contract implemented across SDK, API, resolver, Zod, Vue, and focused tests. Existing disposable live configuration evidence covers the Device/Media path; account-scoped User/Group public-to-raw mapping is protected by focused API tests |
| Caller-ID condition branches | `check_cid` and `cidlistmatch` `children.match` / `children.nomatch` | Regex-mode Check CID has safe-regex validation, stable public result branches, and optional all-or-none identity override fields. The public Extension UUID is resolved server-side into Kazoo's nested `caller_id.external` and `user_id` values. Existing absolute caller-number branches are numbered preserved branches; their nodes and destinations cannot be rewritten. Caller-ID List Match selects an account-scoped projected List by public UUID; Laravel resolves the private List ID and exposes only stable `match`/`nomatch` branches. Lists and entries retain separate redacted `switch_json` snapshots and are never confused with account Blacklists. Standalone list metadata and number/pattern entries use account-scoped API CRUD and a shared-control slide-over editor with safe-regex validation. The Switch adapter hydrates summary-only entry collections and supplies the schema-required parent `list_id` internally | Regex-mode Check CID, Caller-ID List Match, and standalone Caller-ID List CRUD implemented; absolute Check CID mode intentionally read-only; authenticated local Switch create/edit/reopen/clear/delete verified |
| Captured-number branches | `branch_bnumber` exact child keys, `hunt`, `hunt_allow`, `hunt_deny`, and `_` continuation | Branch mode accepts bounded dial strings (`0`–`9`, `*`, `#`, and `+`) as typed condition branches. Hunt mode exposes safe optional allow/deny regexes and only the default continuation; enabling it with exact children is rejected. Existing data and subtrees remain lossless | Implemented across SDK, API, Zod, Vue, focused tests, and disposable live Switch verification |
| Module reference palette | Installed Kazoo palette registry plus connected-version-safe current-schema actions | Expanded categories use the exact installed Kazoo section names, membership, and order, without an invented “Schema extensions” category. Supported current-schema actions that are absent from the installed palette are search-only, so existing guided workflows remain reachable without changing the visible native registry. All entries and diagram/editor nodes use one centralized corresponding icon map. Guided resource and supported inline actions open their schema-appropriate right-side form; planned and restricted entries remain non-mutating | Implemented and headless-tested |
| Installed palette classification | All 49 visible Monster actions across Basic, Advanced, Time of Day, Ring Group Toggle, Hotdesking, Do Not Disturb, Caller-ID, Call Recording, and Call Forwarding | Exactly 40 actions are guided through a public destination or typed inline contract. Nine variants are capability-gated: Pivot, DISA, Global Carrier, Account Carrier, Webhook, Dynamic CID, and Call Forwarding activate/deactivate/update. No visible action remains planned. The catalog test fixes the exact counts and restricted IDs; the API test rejects every restricted module before gateway access; the isolated browser opens every category, verifies the 40/9/0 status split and disabled restricted controls, and sends no Callflow mutation | Complete and isolated-headless verified |
| Other keyed recursive branches | Module-specific branch schemas beyond Menu, temporal routing, Caller-ID, Call Priority, and Branch Bnumber | The installed 49-action Monster registry has no additional default-palette keyed branch contract. Its only multi-child actions are Menu and Time of Day, both already guided. A redacted 2026-08-31 inventory of 30 active test-account callflows likewise found no unhandled keyed shape. Unknown or future-version branch keys remain read-only and losslessly preserved until their own schema/runtime audit exists | No current default-palette target; future capability-gated |

The 2026-08-31 Feature Codes audit confirmed that `callflows.json` treats
`featurecode` as descriptive metadata rather than a separate resource or
enabled flag. Installed Monster considers an existing matching Callflow
enabled, disables it by deleting that whole Callflow, and renumbers it through
a full document update. Its mutation registry also contains commented or stale
contracts, including legacy Do Not Disturb action names, so it is workflow
evidence rather than payload authority. Direct read-only Switch hydration and
the MySQL projection independently returned the same 17 active feature-code
callflows for account `4bb372131dddafedcdb142ea3a0ccf2f`: four Call Forward,
Directed Extension Pickup, three Hotdesk, Intercom, Call Move, three Parking,
Privacy, and three Voicemail routes. The browser verified the public UUID/code
inventory, search, responsive containment, keyboard search, explicit read-only
state, raw-ID redaction, and zero mutations. A focused 2026-09-01 rerun
additionally verified the accessible table name and column headers, loading
semantics, mobile action containment, and absence of browser or server errors.
No Switch resource was created, changed, or deleted during this audit.

Focused verification passed with one Laravel API test and 9 assertions, three
Vue unit files with 5 tests, Vue and isolated-E2E TypeScript checks, and one
isolated authenticated headless Playwright test in 2.0 seconds.

### Callflow action security audit

The 2026-08-30 audit checked the installed schemas and compiled Kazoo runtime,
then used Monster only to confirm the intended operator workflows.

| Action | Installed schema and runtime behavior | Security and product decision |
| --- | --- | --- |
| ACDC Queue | `callflows.acdc_queue` permits `action` (`login` or `logout`), requires raw Queue `id`, and permits `skip_module`. Installed `cf_acdc_queue` answers the call, derives the raw Agent ID from the authorizing endpoint's single Hotdesk user or owner, adds or removes the raw Queue ID in that account-local User's `queues` list, publishes the matching Queue membership event, plays the result prompt, and continues. It does not accept a design-time Agent and has no PIN challenge | Guided and search-only as `{ action, queue_id, skip_module }`. The public Queue UUID is resolved account-locally to raw `id` only at the Switch boundary; unsynchronized and cross-account Queues are rejected. Public readback returns the Queue UUID/label and never the raw ID. Existing unresolved targets are read-only, and unknown node fields remain private and losslessly preserved. The editor warns that the no-PIN behavior belongs behind a trusted feature-code route. Focused SDK/API/resolver/Zod/component tests and a disposable isolated lifecycle verified both actions, mapping, redaction, reopen, cleanup, soft deletion, and zero active Switch matches. No media leg was originated, so Agent inference and live membership mutation remain compiled-runtime evidence |
| Ring Group User/Group expansion | User members resolve through `kz_attributes:owned_by(UserId, device, Call)` in the caller account database. Group members open the raw Group in that account and recursively expand Device, User, and nested Group members. Exact raw Device/delay/timeout triples are deduplicated; differing timing remains duplicated. Device eligibility is evaluated later and no final resolved-device cap exists | Enabled for synchronized account-scoped public Extension and Group UUIDs to match installed Kazoo/Monster semantics. Raw identifiers are resolved server-side and never returned. GridPBX's managed Group writer prevents direct/nested cycles, while externally authored legacy cycles or unresolved members keep a node read-only. The UI explicitly describes 1–20 configured members, not resolved Devices, because membership can change at call time |
| Ring Group bridge flags | `callflows.ring_group.ignore_forward` is a boolean with schema default `true`; installed `cf_ring_group` passes its binary boolean to the bridge as `Ignore-Forward`, which maps to FreeSWITCH's fatal outbound-redirect behavior. `fail_on_single_reject` is an optional boolean passed as `Fail-On-Single-Reject`; absence leaves the FreeSWITCH behavior disabled | Guided as two strict shared-checkbox controls with public defaults `true` and `false`. Neither field accepts a URL, identifier, or untrusted nested payload. Malformed legacy values make the node read-only. A disposable isolated lifecycle verified both defaults, the `false`/`true` edit, authoritative reopen, raw values, public raw-ID/private-field redaction, private ringtone and unknown-field preservation through the production DTO path, browser cleanup, MySQL soft deletion, and zero active Switch matches. No media leg was originated, so the installed runtime establishes live bridge semantics |
| Ring Group ringback and phone alerts | `callflows.ring_group.ringback` is a string passed through `kz_media_util:media_path` and then to the bridge. Plain raw Media IDs become account-scoped media paths, but arbitrary HTTP/HTTPS and special stream values are also runtime-capable. `ringtones.internal` is chosen for calls without inception and `ringtones.external` otherwise; the selected string becomes SIP `Alert-Info`, not audio | Guided only as account-scoped `ringback_media_id` for synchronized streamable `audio/*` Media plus optional bounded internal/external `Alert-Info` text. Raw Media IDs are resolved only at the Switch boundary and never returned publicly. URL/special-stream/system-path ringback is rejected to avoid SSRF and availability risk; unsafe legacy values are redacted and read-only. CR/LF/NUL phone-alert values are rejected. Unknown nested ringtone keys are preserved privately. A disposable isolated lifecycle verified public/raw Media mapping, create/edit/reopen, skip, nested unknown preservation, cleanup, MySQL soft deletion, and zero active Switch matches. No media leg was originated, so audible playback and emitted SIP headers remain installed-runtime evidence |
| Ring Group Toggle | `callflows.ring_group_toggle` requires `action` (`login` or `logout`) and `callflow_id`, with optional `skip_module`. Installed `cf_ring_group_toggle` answers the call, opens that target only in the caller's account database, recursively visits every `ring_group`, and changes `disable_until` only on `user` endpoints whose raw ID equals `kapps_call:owner_id(Call)`. Login writes `0`; logout writes `66269664000`. Device/group endpoints and other users are unchanged. The module plays logged-in, logged-out, or invalid-choice prompts, saves the complete callflow with bounded conflict retries, and continues | Guided for synchronized non-feature callflows containing a Ring Group. The public API/UI use only the account-scoped target Callflow UUID; Laravel maps the raw target at the Switch boundary and rejects cross-account or non-ring-group targets. Focused SDK coverage proves unknown node fields survive typed edits and remain absent publicly. Disposable live configuration verified both actions, edits, reopen, raw/public mapping, cleanup, and public redaction. Crossbar sanitized the attempted unknown marker, so live preservation is not claimed. No media leg was originated, so compiled-runtime inspection rather than a live call proves the owner-only membership rule and prompts |
| Hotdesking | `callflows.hotdesk` permits `login`, `logout`, `toggle`, and `bridge`, with optional `id`, `interdigit_timeout`, and `skip_module`. Monster exposes only login/logout/toggle and writes only `action`. Installed `cf_hotdesk` does not consume a feature-code capture value: when no server-owned `id` is present it prompts for the account Hotdesk ID, except logout can infer the sole owner of the authorizing endpoint. Login loads the user's Hotdesk profile, enforces its PIN when `require_pin = true`, optionally removes that user from other devices, and writes the raw user ID under the current authorizing device's `hotdesk.users`. Logout never requests the profile PIN and removes either the current device or all devices according to `keep_logged_in_elsewhere`. Toggle chooses login when the user has no active Hotdesk endpoints and logout otherwise. Each mutation saves the complete Device document and sends an unsolicited MWI update | Guided only as the resource-free public contract `{ action: login|logout|toggle, skip_module }`; `bridge`, design-time user selection, raw `id`, and timing controls are rejected or remain server-owned. The form warns that logout and toggle's logout path do not prompt for a PIN and should remain behind a trusted feature-code route. Focused SDK coverage proves raw/private and unknown fields survive typed edits, while API/resolver/UI tests prove they remain absent publicly. A disposable isolated run created, edited, and reopened all three actions; an independent raw watcher confirmed the exact actions, `skip_module = true`, and no `id` or `interdigit_timeout`. No media leg was originated, so prompts, PIN entry, and Device session mutation remain compiled-runtime evidence rather than a live-call claim |
| Do Not Disturb | `callflows.do_not_disturb` permits `action` (`activate`, `deactivate`, or `toggle`), optional raw `id`, and `skip_module`. Monster's normal palette writes only the action. Installed `cf_do_not_disturb` uses `id` only when server-owned data already provides it; otherwise it resolves `kapps_call:owner_id(Call)` and falls back to `authorizing_id(Call)`, opens only the caller account database, accepts only User or Device documents, and changes only `do_not_disturb.enabled` with conflict retry | Guided only as resource-free `{ action, skip_module }`. GridPBX rejects legacy `enable`/`disable` spellings and any public raw `id`, returns `target: null` with `reference_status: not_applicable`, and preserves private/unknown node fields losslessly. There is no public-to-raw mapping: runtime identity resolution stays in Kazoo. The module has no PIN challenge, so the editor warns that this must remain behind a trusted feature-code route. Focused tests protect the contract and preservation; a disposable isolated run verified all three create/edit/reopen paths, raw/public redaction, and cleanup. No media leg was originated, so the account-local User/Device mutation remains compiled-runtime evidence |
| Call Forwarding | `callflows.call_forward` permits `activate`, `deactivate`, `update`, `toggle`, and `menu` plus `skip_module`; Monster's normal palette exposes the first three, while its feature-code registry also defines toggle. Installed `cf_call_forward` resolves the authorizing endpoint's owner or falls back to the endpoint, answers the call, and has no PIN challenge. Activate and update use a feature-code capture or prompt for an arbitrary 3–20 digit destination; toggle reuses a stored number or collects one. The runtime writes `call_forward.enabled` and `call_forward.number` on the complete account-local document with conflict retry, but performs no ownership, destination-class, emergency/premium/international, rate/lockout, or loop validation | Capability-gated as a category. All visible actions are disabled with a toll-fraud explanation; public create/update requests and direct Switch DTO writes reject the module, and existing nodes plus descendants stay read-only. Public projection exposes only the safe action and skip summary for labeling; the raw forwarding number and unknown properties remain private. A focused SDK regression proves private data and the subtree survive an unrelated typed edit. API, validator, resolver, public-tree, catalog, detail-panel, type, and isolated headless tests passed; the browser emitted no Callflow mutation. No disposable Switch write or live call was attempted because the audited runtime lacks the controls required to exercise it safely |
| ACDC Agent | `callflows.acdc_agent` permits `login`, `logout`, `paused`, and `resume`, optional `presence_id`, one of six presence states, integer `timeout`, and `skip_module`. Installed `cf_acdc_agent` answers the call and infers a raw Agent ID from the authorizing endpoint's single Hotdesk user or owner. It has no PIN challenge and sends no Queue ID. The ACDC handler opens that account-local raw User document without a queue-membership check at this boundary. `paused` becomes the runtime `pause` event; an omitted timeout defaults to 600 seconds, while message validation accepts any non-negative integer with no upper bound. Login, logout, pause, and resume can change live agent and presence state. The action is absent from the installed Monster palette | Capability-gated and search-only. All four schema variants are disabled with direction to the authenticated Queue Agent status panel. Public API and direct Switch DTO writes reject the module; existing nodes and descendants are preserved and read-only. The public projection exposes only `action` and `skip_module`; inferred raw Agent IDs, presence fields, timeout, and unknown properties remain private and lossless. The supported Queue Agent panel already resolves an account-scoped public Extension UUID, verifies membership, bounds pause time, authorizes the operator, and audits the result. Focused SDK/API/resolver/public-tree/catalog/type checks and one isolated headless no-mutation test passed. No disposable Switch write or live agent-state mutation was attempted because the missing feature-code authentication and membership controls are the reason it remains gated |
| Eavesdrop / Eavesdrop Feature | `callflows.eavesdrop` permits one raw target `device_id` or `user_id`, raw `approved_device_id`, `approved_user_id`, or `approved_group_id`, and `skip_module`. `callflows.eavesdrop_feature` replaces the direct target with raw `group_id` and captures an extension at runtime. Direct runtime finds active target channels and starts DTMF-enabled live monitoring; Feature resolves the captured account callflow to its first Device/User node, applies an optional direct Group membership restriction, and delegates to direct Eavesdrop. Missing approval fields deny access, but multiple approval fields do not combine: only the first configured Device/User/Group field is evaluated. Authorization Group expansion and Feature target Group matching also use different membership semantics. Both actions stop the current callflow and are absent from the installed Monster palette | Capability-gated and search-only. Public API and direct SDK writes reject both modules; existing nodes and every descendant remain preserved and read-only. Public projection exposes only `skip_module`; raw target, approval, and Group IDs plus unknown properties never cross the API and survive unrelated typed edits. Enabling requires an account-scoped supervisor entitlement, explicit target policy, immutable monitor audit, privacy/consent and notification policy, bounded listen/interaction modes, and live-call abuse verification. Focused SDK/API/resolver/public-tree/catalog/type checks and one isolated headless no-mutation test passed. No disposable Switch write or monitored call was attempted because those missing controls are the reason the feature is gated |
| Pivot | `callflows.pivot` requires `voice_url`, permits HTTP or HTTPS, GET or POST, form or JSON POST bodies, Kazoo or TwiML responses, optional `cdr_url`, debug persistence, and a request timeout up to 5000 ms. The schema defaults `req_format` to Kazoo, but the installed worker defaults an omitted request format to TwiML. The runtime sends account, call, caller, callee, custom application variable, custom SIP header, recording, transcription, and user-variable data. Kazoo-format responses can replace live call control; TwiML Redirect, Gather, and Record can issue relative or absolute follow-up requests. The worker accumulates streamed response chunks without a size ceiling and increments a counter without enforcing an iteration limit. The CDR callback is a separate unauthenticated form POST after channel destruction and does not receive the configured Pivot request timeout | Capability-gated. The installed worker applies no destination allowlist, DNS/IP/private-network rejection, application authentication header, callback signature, redirect-chain policy, or Pivot-specific TLS policy. Debug mode can persist request and response bodies. GridPBX exposes none of the Pivot URLs, formats, debug state, or callback configuration; typed SDK and API writes reject the module, while existing private data and descendants remain read-only and losslessly preserved. GridPBX must not expose Pivot until server-owned HTTPS origins, DNS rebinding-safe egress enforcement on every connection and redirect, bounded response size/iterations and total callback timeout, verified TLS, secret-backed authentication, signed callbacks, data-minimization controls, and audit/kill-switch behavior are defined outside user-editable callflow JSON |
| DISA | The installed public schema declares only `skip_module`, but the runtime and Monster workflow consume undeclared `pin`, `retries`, `interdigit`, `max_digits`, `preconnect_audio`, and `use_account_caller_id`; runtime also accepts undeclared `enforce_call_restriction` and `ring_repeat_count`. Monster stores the PIN as visible text in node metadata and merely warns before saving an empty PIN. Runtime explicitly permits an empty PIN, logs the digits entered for a bad PIN, and has only recursive per-call retries with no cross-call rate limit or lockout. It normalizes the collected destination, then executes an exact, pattern, or account no-match Callflow. Restriction enforcement defaults to false; when enabled it checks only account-level classification and fails open if the account cannot be loaded or the class is not explicitly denied. The selected original or account caller ID is marked `Retain-CID` before the target Callflow runs | Capability-gated. This schema/runtime drift cannot support a safe typed editor. GridPBX exposes none of the PIN, dialing, restriction, audio, or caller-ID configuration; typed SDK and API writes reject DISA, while existing private data and descendants remain read-only and losslessly preserved. Enabling requires a mandatory write-only, strongly hashed secret or stronger identity, bounded retry/timeout/digit policy, persistent rate limiting and lockout, default-deny destination classification that fails closed, explicit emergency/premium/international policy, account-owned source-number authorization, redacted immutable audit records, spend/concurrency controls, and live abuse tests |
| Conference Service | Monster writes the existing `conference` module without an `id`. `cf_conference` omits `Conference-ID`; the installed conference discovery worker then prompts up to three times for a 1–16 digit account conference number and applies the selected conference's existing member/moderator PIN rules | Guided as a distinct resource-free variant. Public writes carry only `service_mode: true` and `skip_module`; Laravel strips the discriminator and the raw node never contains a conference resource ID. Configured Conference remains UUID-backed. Focused preservation/collision tests and a disposable live Switch configuration lifecycle passed; no media-leg prompt call was originated during that run |
| Check Voicemail | `callflows.voicemail` supports `action: check`, optional mailbox `id`, caller-ID matching, single-mailbox login, timeouts, and message limits. Installed `cf_voicemail` runtime inspection confirmed that resource-free check mode discovers an account mailbox by feature-code capture or prompts for its number, bounds retries, and enforces the selected mailbox PIN policy; an authenticated owner may intentionally bypass the PIN only when that mailbox has `require_pin = false` | Guided only as resource-free `{ action: check, skip_module }`. GridPBX rejects mailbox IDs, compose mode, caller-ID matching, and single-mailbox auto-login at its public write boundary. Those private fields and unknown data are redacted and preserved. Focused tests and a disposable Switch lifecycle passed; Kazoo materialized both private login flags as `false`, no raw `id` existed, and the injected unknown marker survived the edit |
| Global Carrier | Monster adds an empty `offnet` node with no editor. The installed `cf_offnet` runtime forces `use_local_resources = false` and delegates to `cf_resources`, which publishes a paid external bridge request containing call/account identity, caller IDs, channel/application variables, SIP headers, a final `to_did`, normalization controls, resource flags/type, and timeout/media settings. Installed route-entry code classifies the original request before the callflow runs, while the later module may replace the final DID. The denied-classification map passed to StepSwitch is consulted for reclassified short-dial correction, not the ordinary final destination. Resource classifiers select eligible carriers but do not authorize the caller. The schema `timeout` bounds bridge answer time; `cf_resources` otherwise waits in repeated ten-second event windows until an offnet response or channel destruction. A generic Callflow token bucket exists at route entry, but it is not a carrier-action destination, spend, or concurrency policy. The global emergency path defaults `deny_invalid_emergency_cid` to false and can continue with an anonymous caller ID | Capability-gated. Even empty data can originate through system-wide carriers from an arbitrary inbound or internal tree position, enabling hairpin loops and unbounded spend. GridPBX exposes none of the DID, caller-ID, SIP, normalization, resource, flag, media, or timeout fields; typed SDK and API writes reject the module, while existing private data and descendants remain read-only and losslessly preserved. Enabling requires an authenticated outbound-only context, classification and authorization of the final normalized destination, default-deny emergency/premium/international policy, loop prevention, spend/rate/concurrency controls, immutable audit events, and server-owned SIP/resource settings |
| Account Carrier | Monster adds `resources` and optionally writes an operator-entered raw `hunt_account_id`. The installed runtime defaults local-resource hunting to the current account and accepts the same DID, normalization, SIP, caller-ID, and resource-selection controls as `offnet`. Any hunt account also forces outbound handling for numbers otherwise owned by Kazoo. Installed StepSwitch validates a requested hunt account against the caller's account hierarchy, but any present hunt account takes the local-resource emergency branch, which explicitly skips emergency caller-ID validation | Capability-gated. A hierarchy check does not make raw Switch account IDs or arbitrary local-carrier selection safe for the public API. GridPBX exposes no raw hunt account or private routing configuration; typed SDK and API writes reject the module, while existing private data and descendants remain read-only and losslessly preserved. Enabling requires the same final-destination and toll-fraud controls as Global Carrier, account-scoped public UUID resolution with explicit reseller entitlement, projected carrier-pool capability, server-owned routing metadata, and an emergency policy that does not rely on the local-resource validation bypass |
| Webhook | `callflows.webhook` accepts an operator URI using HTTP or HTTPS, GET or form-encoded POST, arbitrary `custom_data`, integer retries, and `skip_module`; unlike Monster's legacy form, the installed callflow schema does not support PUT or a JSON format control. `cf_webhook` asynchronously continues the callflow, then publishes a broad normalized call snapshot containing raw account, authorizing, owner, call, caller/callee, SIP-header, application-variable, and Switch-host data. Delivery adds raw `X-Account-ID` and `X-Hook-ID` headers but no signature or authentication secret. The active URL blacklist contains only literal `localhost`, `127.0.0.1/32`, and `0.0.0.0/32`; hostnames are not resolved during validation. Installed HTTP defaults follow up to four redirects, have an infinite total request timeout, and use TLS `verify_none`. Runtime retries are clamped to 1–5 and occur only for client/network errors after a fixed two-second delay; only HTTP 200 is success, while non-200 responses are not retried. Failed attempts persist the URI, request headers/body, response headers/body, and errors in the account MODB | Capability-gated. GridPBX exposes no URI, custom data, retries, raw identifiers, or delivery payload; typed SDK and API writes reject Webhook, while an existing node's private configuration, unknown fields, continuation, and descendants remain locked, read-only, and losslessly preserved. No live callback was sent because the installed path is not safe for an operator-controlled destination. Enabling requires server-owned HTTPS origin allowlists, DNS resolution and private/link-local/metadata rejection on every connection and redirect, verified certificates and hostnames, a bounded total timeout and response size, signed minimal payloads with replay protection and secret rotation, public-safe identifiers, redacted attempt records with retention limits, bounded idempotent retry/backoff policy, account rate/circuit controls, immutable audit events, and an emergency kill switch |
| Dynamic CID | Monster creates an empty `dynamic_cid` node with no editor. The installed runtime treats an omitted action as `manual`, prompts the caller for a replacement caller-ID number, and by default accepts any ten digits matching `\d+`; it does not prove that the number belongs to the account. `static` accepts arbitrary name/number data. `list` and `lists` consume raw list identifiers and can also select a new destination from the feature-code capture. Destination call restrictions default on only for that list-routing path, are bypassed if endpoint lookup fails, and can be explicitly disabled; `permit_custom_callflow` can allow a matched custom route. The downstream caller-ID layer validates dynamic external numbers only when the system-wide `callflow.ensure_valid_caller_id` setting is true. It is unset in this deployment, so the installed default is false | Capability-gated. GridPBX exposes none of the arbitrary/manual caller-ID, raw list ID, prompt, digit/regex, restriction-bypass, or custom-route configuration; typed SDK and API writes reject Dynamic CID, while existing private data, unknown fields, continuation, and descendants remain locked, read-only, and losslessly preserved. No live call was originated because caller-ID ownership cannot be guaranteed by the installed path. A safe variant requires an account-scoped public Phone Number UUID or a dedicated projected caller-ID profile, server-side ownership/E911 verification and raw mapping, immutable anti-spoofing audit, authenticated feature-code context, final-destination restrictions that fail closed when endpoint resolution fails, rate limits, and live carrier-level caller-ID verification before enablement |

The selectable node-and-connector diagram and compact schema-backed right
palette now render in a wide main-page workspace with small responsive gutters
and no narrow centered maximum. The palette can be dragged within the viewport
and returned to its right-side dock. There is no graph detail slide-over;
selected-node information and safe reorder controls use an accessible modal.
This gives branching routes
sufficient room and preserves the agreed boundary: right-side panels are
limited to typed mutation forms. Guided subtrees can now be moved into empty
schema-valid branches, inserted before a compatible occupied position, or
swapped across disjoint positions without exposing raw
Switch keys or rebuilding node data. Guided resource modules have shared add
and retarget forms backed by account-scoped public UUID choices. Sleep,
text-to-speech, DTMF collection/send/flush, Dead Air, Language, the two
recording actions, Missed Call Alert, Set Caller ID, Prepend Caller ID, Set
Alert Info, regex-mode Check Caller ID, Caller-ID List Match, and terminal SIP
Response, Hangup, Manual Presence, Group Pickup, Receive Fax, Call Priority
assignment, Set CAV, Call Priority branching, Branch BNumber branching/hunting,
and Do Not Disturb
now have bounded non-reference forms. Set
CAV follows Monster's repeatable key/value workflow while keeping those rows
virtual: the API and Switch adapter receive the exact schema object and never a
form-only array. A focused 2026-08-31 recheck confirmed that all 49 installed
palette actions have a classified boundary: 40 guided actions resolve to a
public resource-target or typed inline form, while nine high-risk actions stay
disabled. The isolated headless route-map scenario dragged a Voicemail resource
child and a TTS inline child onto eligible parents, opened their respective
right-side forms, submitted the expected public-safe payloads, and kept
preserved branch identifiers hidden. Resource references remained account-
scoped public UUIDs. The same pass corrected a stale Do Not Disturb schema test:
only `activate`, `deactivate`, `toggle`, and `skip_module` are accepted; unrelated
Ring Group fields and raw owner IDs are rejected.

Callflow creation now starts in that same wide main-page workspace instead of
opening a create slide-over. The unsaved workspace reuses the existing Zod-
validated route fields and installed-palette catalog. Resource-backed root
actions select only account-scoped public destination types; raw Switch IDs
remain server-resolved, and inline actions are added through their typed forms
after the first root has been persisted. Existing-route root edits remain a
typed slide-over because they mutate an authoritative Switch document and must
preserve its complete unknown subtree. A focused 2026-08-31 isolated headless
check confirmed that Create exposes an accessible workspace region, no create
dialog exists, the root palette is visible, choosing User opens the root-action
configuration modal, validation remains inline, and listboxes remain inside
the viewport. Focused component tests and both Vue and isolated E2E TypeScript
checks passed.

A focused 2026-09-01 create-workspace pass added pre-save Menu-key palette
drops without broadening the write contract. Dropping Voicemail on a Menu root
selected the first unused schema-editable key, reopened the existing Menu form,
and selected the projected account-scoped public Voicemail UUID; no raw Switch
identifier or arbitrary node JSON entered the draft. Thirteen focused component
tests, Vue typecheck, isolated E2E TypeScript typecheck, focused ESLint, and the
single isolated headless create-workspace scenario passed. The browser scenario
did not submit the draft, so it made no live route mutation.

The 2026-08-31 disposable route `E2E Node Delete 133359` used number `87133359`
to verify child-subtree deletion against the connected Switch. Its User root
and Voicemail default child were synchronized into MySQL, the isolated browser
confirmed that the root was not removable, sent only public path `['_']` plus
the explicit subtree confirmation, reopened the route with the User root intact,
and never received the raw Callflow identifier. An independent raw read found
the expected private User mapping and zero remaining children. Focused SDK
coverage separately proves unknown top-level, root-data, and sibling-data fields
survive the typed deletion. Cleanup and an independent reconciliation found
zero active MySQL projections, one soft-deleted projection, and zero active
Switch matches.

The live branching walkthrough creates a
disposable Device-rooted route in Switch, adds and reopens the Branch Variable
node, updates its schema-supported skip flag, adds a Priority 42 terminal
branch, adds Branch Bnumber in hunt mode, clears its safe hunt filter during an
edit, adds an exact captured-number `1000` terminal branch, creates Set CAV with
two variables, reopens it, updates one value, clears the second variable and
the export flag, creates Manual Presence with Monster's explicit `busy` default,
updates its local ID to a realm-qualified ID, changes status to `idle`, enables
`skip_module`, then creates Group Pickup for an Extension and changes it to a
Device while preserving the mutually exclusive Kazoo target contract. It then
creates Receive Fax below Group Pickup with an account-scoped public Extension
UUID and `media.fax_option = auto`, edits the option to boolean `true`, enables
`skip_module`, and reopens the authoritative values. The 2026-08-30 live run
injected an unknown nested `media.private_transport` marker between create and
edit: the marker remained in the raw Switch document, while both it and the raw
owner ID remained absent from the public API and UI. The route was deleted, its
MySQL projection was independently confirmed soft-deleted, and no active Switch
callflow with the disposable name or resource remained.
The same isolated walkthrough then created Conference Service below Receive Fax,
confirmed its resource-free public contract and `not_applicable` reference state,
enabled `skip_module`, and reopened the authoritative value. A raw-only
`private_prompt_id` marker injected after creation survived the typed UI edit,
remained absent from public responses and `flow_structure`, and the raw node
contained no `id`. The final disposable route projection was independently
confirmed soft-deleted, retained the marker only in its raw snapshot, and had no
matching active Switch callflow. Two earlier orchestration-only failed attempts
also ran their `finally` cleanup and were independently confirmed soft-deleted
with no active Switch matches.
The walkthrough then created Check Voicemail below Conference Service with the
resource-free `action: check`, enabled `skip_module`, and reopened the saved
value. The public API returned `target: null` and
`reference_status: not_applicable`; neither the injected `private_prompt_id`
marker nor Kazoo's
private login flags appeared publicly. The independently inspected raw snapshot
contained no mailbox `id`, preserved the marker, and held
`single_mailbox_login = false` and `callerid_match_login = false`. The route's
MySQL projection was soft-deleted and no active Switch callflow matched its exact
name or resource ID.

The 2026-08-30 Ring Group Toggle walkthrough used source route
`E2E Ring Group Toggle 1788092620256` (public UUID
`8da3e827-16db-4d94-aaae-46e27efd7dfa`, raw Switch ID
`d5d74e2855de3eace54879ebd351444b`) and target
`E2E Ring Group Toggle Target 1788092151989` (public UUID
`09b8ef06-5de2-4cde-a712-a6e609416dae`, raw Switch ID
`3ff7374fbe3e99a943467a0d35ddd059`). The isolated headless test created Login
below Ring Group, selected the public target, enabled `skip_module`, reopened it,
then created and edited Logout below Login and reopened the authoritative values.
Public responses never contained the raw target ID. An independent raw watcher
confirmed both actions referenced that exact raw target and held
`skip_module = true`. The browser run passed one focused test in 30.6 seconds.
Crossbar sanitized an attempted unknown marker; lossless unknown-field
preservation is therefore evidenced by the focused SDK regression rather than
claimed from the live injection. The final source, target, and three earlier
orchestration-only sources were independently confirmed soft-deleted, with zero
active MySQL projections and zero matching active Switch callflows. No media leg
was originated, so membership mutation and prompt playback remain compiled-runtime
evidence rather than live call evidence.
The 2026-08-30 Hotdesking walkthrough used disposable source route
`E2E Hotdesk 1788094232589` (public UUID
`30dce7a2-6f15-43d6-8b84-010c402430c6`, raw Switch ID
`43fd7376685c547b63dcc1db196c95c8`). The isolated test created Login below
Ring Group Logout, enabled `skip_module`, and reopened it; it repeated the same
create/edit/reopen lifecycle for Logout and Toggle. Every public response held
only the action and skip flag, with `target: null` and
`reference_status: not_applicable`. An independent raw watcher captured
`login`, `logout`, and `toggle` in order with `skip_module = true`; none of the
three raw nodes contained `id` or `interdigit_timeout`. The focused browser run
passed one test in 36.1 seconds. Unknown-field preservation is evidenced by the
focused Switch DTO regression rather than a live injection. Browser cleanup
soft-deleted the projection before reconciliation; a separate synchronization
kept it soft-deleted, and no active Switch callflow matched the exact name or
raw ID. No media leg was originated, so PIN prompts and Device session changes
remain compiled-runtime evidence.
The 2026-08-30 Do Not Disturb walkthrough used disposable source route
`E2E Do Not Disturb 1788096546218` (public UUID
`6d04749b-5d2f-480d-9e95-264e0b2e4fd6`, raw Switch ID
`f896fdf9fe2eef4fd81c39c29b8bd898`). One focused isolated headless test created
Activate below the Device-rooted route, enabled `skip_module`, reopened it, and
repeated that create/edit/reopen lifecycle for Deactivate and Toggle. Every
public response contained exactly `action` and `skip_module`, with `target: null`
and `reference_status: not_applicable`; no raw owner, Device, or node `id` was
exposed. An independent raw watcher captured `activate`, `deactivate`, and
`toggle` in order with `skip_module = true`, and none of the raw nodes contained
`id`. The focused browser run passed one test in 7.5 seconds. Unknown-field and
private-`id` preservation are evidenced by the focused Switch DTO regression
rather than a live injection. Browser cleanup soft-deleted the MySQL projection
before reconciliation; a separate synchronization kept it soft-deleted, and no
active Switch callflow matched the exact name or raw ID. No media leg was
originated, so the account-local User/Device mutation and prompt playback remain
compiled-runtime evidence.
The 2026-08-30 Call Forwarding verification intentionally used the UI-only
complex route instead of a disposable Switch mutation. One focused isolated
headless test opened the installed Call Forwarding palette category, confirmed
Enable, Disable, and Update were all disabled as `Capability required`, selected
the existing forwarding node, verified the arbitrary-destination security
explanation and absence of an edit control, and observed zero Callflow mutation
requests. The test passed in 889 ms. Focused server tests separately proved that
public create/update and direct Switch DTO writes are rejected, only the safe
action/skip summary is projected, descendants are exposed under preserved
read-only branches, and a raw forwarding number plus unknown node data survive
an unrelated typed edit. No live forwarding configuration or media leg was
created because the missing authentication and toll-fraud controls are the
reason this capability remains gated.
The 2026-08-30 ACDC Agent verification also intentionally avoided a disposable
Switch mutation. One focused isolated headless test searched the UI-only action
catalog, found Login, Logout, Pause, and Resume as search-only actions, confirmed
all four were disabled as `Capability required`, and observed zero Callflow
mutation requests. The test passed in 787 ms. Focused server tests proved the
module is rejected before gateway access and by the direct Switch DTO, only
safe action/skip metadata is projected, raw presence and timeout fields are
redacted, and existing descendants are exposed only as preserved read-only
branches. Installed compiled-runtime inspection supplied the identity,
membership, timeout, and presence evidence. No live agent state was changed
because the absence of a PIN and a queue-membership check at this callflow
boundary is the reason the capability remains gated.
The 2026-08-30 Eavesdrop verification intentionally avoided a disposable
Switch mutation and live call monitoring. One focused isolated headless test
searched the UI-only catalog, found direct and feature-code Eavesdrop as
search-only actions, confirmed both were disabled as `Capability required`,
and observed zero Callflow mutation requests. The test passed in 1.9 seconds.
Focused server tests proved both modules are rejected before gateway access and
by the direct SDK, only `skip_module` is projected publicly, all raw target,
approval, and Group IDs are redacted, descendants are preserved under locked
branches, and private plus unknown node data survive an unrelated typed edit.
Installed schema and compiled-runtime inspection supplied the authorization,
target discovery, Group restriction, DTMF monitoring, and terminal behavior
evidence. No public/raw UUID mapping exists because the actions are not
writable, and no monitored call was originated because the missing supervisor,
audit, and privacy controls are the reason the capability remains gated.
The 2026-08-30 ACDC Queue walkthrough used disposable route
`E2E ACDC Queue 20260830142404451` (public UUID
`61854d2b-0195-4277-bc8b-50201d929608`). One focused isolated headless test
created Queue agent login below the User-rooted route, selected an
account-scoped Queue by public UUID, enabled `skip_module`, and reopened the
authoritative values. It then created Queue agent logout beneath login with the
same public Queue and reopened both nodes. Public responses returned only
`action`, `queue_id`, the safe Queue label/capability metadata, and
`skip_module`; the Queue's raw Switch ID never crossed the API. An independent
SDK read captured login then logout with the expected private raw Queue `id`
and `skip_module = true`. The focused browser test passed one test in 27.2
seconds. Unknown-field preservation is evidenced by the focused Switch DTO
regression rather than a live injection. Browser cleanup deleted the Switch
route; a separate synchronization confirmed the MySQL projection remained
soft-deleted and found zero active Switch callflows with the exact name. No
media leg was originated, so Agent discovery, prompts, and live User Queue
membership changes remain compiled-runtime evidence.
The final 2026-08-30 installed-palette sweep opened every category in the
UI-only route and counted 49 unique action cards: 40 `Guided now`, nine
`Capability required`, and zero `Visual editor planned`. Every restricted card
was disabled and the browser emitted no Callflow mutation. The single focused
headless test passed in 1.4 seconds. The matching catalog contract proves every
guided action resolves to a public-destination or typed-inline implementation,
while the focused API contract rejected Pivot, DISA, Global Carrier, Account
Carrier, Webhook, Dynamic CID, Call Forwarding, and the search-only ACDC Agent
module before gateway access.
The Page Group walkthrough used a fresh disposable route named
`E2E Page Group 1788088149315`. It created Page Group below Check Voicemail with
one account-scoped public Device UUID and `audio = one-way`, edited it to
`audio = two-way` with `skip_module = true`, and reopened the authoritative
values. The raw Switch node contained the expected private Device resource ID,
top-level `timeout = 5`, and endpoint `delay = 0` and `timeout = 20`; the public
API and UI returned only the Device UUID and never exposed the raw ID or hidden
timing fields. The typed edit preserved those Kazoo-materialized defaults. The
route was deleted through the browser, and its MySQL projection and Switch
resource were checked independently. Unknown nested endpoint preservation is
covered by the focused Switch DTO regression test: Crossbar sanitized attempted
live unknown-field injection, and direct CouchDB writes are prohibited by the
implementation policy. No media-leg page was originated during this
configuration lifecycle, so Page Group remains a verified guided foundation.
The Ring Group walkthrough used a fresh disposable route named
`E2E Ring Group 1788090166193`. It created Ring Group below Page Group with one
account-scoped public Device UUID, simultaneous delay `5`, endpoint timeout
`20`, and two attempts. It then changed the strategy to in-order, confirmed the
UI reset delay to `0`, changed endpoint timeout to `30`, reduced attempts to
one, enabled `skip_module`, and reopened the authoritative values. The raw
Switch node held the expected private Device resource ID and computed
top-level timeout `30`; neither appeared in the public API/UI contract. The
route was deleted through the browser, its MySQL projection was independently
confirmed soft-deleted, and no active Switch callflow matched its name or raw
ID. Crossbar sanitized attempted schema/private marker injection, so lossless
private and unknown endpoint/node preservation is covered by the focused Switch
DTO regression test; no direct CouchDB write was used. Three orchestration-only
failed attempts also ran browser cleanup and were independently confirmed
soft-deleted with no active Switch matches. No media-leg call was originated,
so Ring Group remains a verified guided foundation rather than a globally
complete telephony feature.
The 2026-08-30 weighted-random extension used fresh disposable route
`E2E Ring Group Weighted 20260830150119890` (public UUID
`1a1b4319-5b70-4290-9c90-511c20324f41`). One focused isolated headless test
created a Device-only simultaneous Ring Group with delay `5`, endpoint timeout
`20`, and two attempts, then edited it to `weighted_random`, delay `0`, endpoint
timeout `30`, explicit weight `75`, one attempt, and `skip_module = true`. The
form reopened with every authoritative value. Public requests and responses
used the account-scoped Device UUID and omitted the hidden top-level timeout,
ringback, and raw Device resource ID. An independent SDK watcher captured raw
Switch strategy `weighted_random`, computed timeout `30`, endpoint type
`device`, the expected private Device ID, and weight `75`. Browser cleanup
deleted the route; an independent synchronization confirmed its projection was
soft-deleted and found zero active Switch callflows with the exact name. The
focused SDK regression, rather than a live private-field injection, proves that
unknown endpoint and node fields survive typed edits. No media-leg call was
originated.

The final 2026-08-30 bridge-flag lifecycle used fresh disposable route
`E2E Ring Group Flags 1788104697523`. The form created a Device-only Ring Group
with `ignore_forward = true` and `fail_on_single_reject = false`, then edited
them to `false` and `true` while setting weighted-random weight `75`, endpoint
timeout `30`, and `skip_module = true`. Reopen confirmed every authoritative
value. Public requests and responses retained only the account-scoped Device
UUID and omitted the raw Device ID, a private `ringtones.external` value, and
an unknown node marker. An independent watcher added the two private values
through the production Switch DTO normalization path, then captured their
retention and the expected raw Device mapping after the typed edit. The one
isolated headless test passed in 4.4 seconds. Browser deletion and independent
synchronization confirmed a soft-deleted MySQL projection and zero active
Switch matches. No direct CouchDB write or media-leg call was used.

The 2026-08-31 Ring Group User/Group audit did not create a disposable node.
Installed runtime inspection confirmed account-local lookup but also dynamic
User ownership expansion, recursive Group expansion without a visited set,
deduplication only for equal Device/delay/timeout triples, and no final fan-out
cap. The installed Group schema and Crossbar validation do not reject recursive
membership, while Monster's direct User/Device warning does not cover nested
Group overlap or future membership changes. The subsequent parity
follow-through enabled synchronized account-scoped public Extension and Group
choices, maps them to Kazoo `user` and `group` endpoints only at the Switch
boundary, and maps authoritative results back to public UUIDs. Managed Group
writes reject cycles. Focused SDK, Laravel validator/mutation/resolver, Zod,
and component regressions prove the mixed contract and raw-identifier
boundary. The 20-member limit applies to configured entries, not the dynamic
resolved Device fan-out.

The 2026-08-31 ringback/phone-alert lifecycle used disposable route
`E2E Ring Group Media 1788127297`, unique number `88127297`, and a disposable
synchronized silent WAV. One isolated headless test selected that Media by its
account-scoped public UUID, set internal and external phone-alert values,
edited the Ring Group to weighted-random with endpoint timeout `30`, weight
`75`, updated both alerts, enabled `skip_module`, and reopened every
authoritative value. Public requests and responses never contained the raw
Media or Device IDs. An independent raw observer captured both expected raw
mappings, computed timeout `30`, both edited ringtone values, and retention of
an injected unknown nested ringtone key plus unknown node key. The focused
browser test passed in 5.1 seconds. Browser deletion and independent
reconciliation confirmed the Callflow projection was soft-deleted, no active
Switch callflow matched, and the disposable Media projection was soft-deleted
after its Switch resource was removed. No media leg was originated, so audible
ringback and emitted SIP `Alert-Info` remain installed-runtime evidence.

A 2026-08-31 follow-up audited the running local topology before attempting that
media-leg acceptance case. The Kazoo reference container exposed only Crossbar
on TCP 8000; no FreeSWITCH/media-server process, SIP or ESL listener, or RTP
path exists in the workspace stack. The audit therefore made no disposable
Switch write and originated no call. The exact next priority remains the
account-local, no-carrier Ring Group media-leg test in a representative
FreeSWITCH/ecallmgr environment; audible ringback and emitted internal/external
`Alert-Info` must not be marked live-verified before that evidence exists.
The isolated Ring Group scenario now has an explicit acceptance handoff through
`GRID_E2E_RING_GROUP_MEDIA_LEG_FILE`. When supplied, the external ESL/RTP
observer must report the exact disposable route name, observer type
`freeswitch_esl`, zero carrier attempts, and two distinct account-local SIP
calls. The internal and external observations must respectively contain
`internal-ring` and `external-ring`, confirm that the configured Media matches
the seed, and prove audible ringback. The test waits for this evidence before
editing or deleting the route. The variable remains unset in the local
Crossbar-only topology, so this harness improvement is not live-call evidence.

The installed default palette has no remaining planned action or unhandled
keyed branch contract. Future-version or search-only non-reference modules and
branch shapes require their own schema/runtime evidence before they can become
an implementation target. Direct temporal and Rule Set branches already use
typed, server-mapped operations. Unsupported nodes remain locked and lossless.
GridPBX uses its Tailwind visual language rather than copying Monster's styling.

Focused Switch package, Laravel feature, Vue schema/store, and isolated
authenticated headless Playwright checks cover these boundaries. The live case
uses a uniquely named disposable non-inventory-number route and verifies its
cleanup.

### Global Search projection boundary (2026-08-31)

Global Search is a read-only GridPBX projection workflow, not a writable
Switch entity and therefore not a Basic/Advanced form. Its authenticated
account endpoint searches 15 projected resource types only after each type
passes its existing `viewAny` policy. Queries are account-scoped by the private
projection foreign key, but results expose only account-owned public resource
UUIDs and allowlisted title, subtitle, type, and matched-field metadata. Raw
Switch IDs, internal primary keys, `switch_json`, secrets, provider references,
and unselected projection fields do not cross the API boundary.

Results are limited to five per type, exact and prefix matches rank before
contains matches, literal SQL wildcard input is escaped, and the endpoint is
rate-limited by user, account, and requester address. The UI validates the
response with strict Zod schemas, cancels stale requests, and routes UUID-backed
resources directly to account-scoped detail views. List-only destinations
receive a display search query rather than an internal identifier.

Recent results are intentionally memory-only. They are cleared when the
selected account or authenticated user changes and are never stored in
`localStorage`, so a later session cannot display stale metadata after a
permission change. Focused verification passed 9 Laravel tests / 58 assertions,
3 Vue files / 7 tests, Vue typecheck, isolated E2E TypeScript typecheck, and 3
isolated headless Playwright scenarios. Two mocked-response scenarios prove
filter, keyboard, in-session recent, non-persistence, and public-UUID navigation
behavior. The third read-only scenario passed against the actual selected
account and actual Global Search endpoint, confirming that a projected Callflow
returns through the strict five-field public contract with a public UUID.
Human/client workflow acceptance remains open, so the roadmap status is
Foundation rather than Complete.

### Shared mutation controls checkpoint

The Device reference form and the User/Extension workflow now render ordinary
text, password, email, telephone, number, and multiline fields through the
shared `FormInput` and `FormTextarea` components. This includes the embedded
Device and Voicemail editors plus Directory, Group, Media, Temporal routing,
Conference, Fax, Menu, Queue/Agent, Line Key, and Account mutation panels, so
nested and standalone workflows share the same labels, descriptions, ARIA
relationships, and field-local red validation state.

Purpose-specific shared adapters now cover search, file upload, and checkbox
behavior as well. `SearchInput` is used by entity lists, action palettes, and
the workspace header; `FormFileInput` owns Media and Voicemail audio selection;
and `FormCheckbox` supplies card, row, compact, and inline variants for form
selection groups and voicemail message selection. Advanced CDR/recording
filters, confirmation text, and guided metaflow fields also use `FormInput`.
There are no direct native `input` or `textarea` elements outside these shared
primitives in `grid-ui/src`; Headless UI listboxes and the existing toggle
adapter remain specialized controls.

Entity form tabs now use the same horizontal, overflow-safe Device presentation
through the shared `FormTabBar` and Basic/Advanced adapter. This standard covers
Account, Caller-ID List, Conference, Device, Directory, Fax Box, Media, Menu,
Queue, and Voicemail writable forms, plus nested Account recording targets.
Conference's Advanced view additionally restores Monster's inner `Basic`,
`Options`, and `Conference Server` sections while retaining newer typed Kazoo
fields in the section that owns their behavior. Menu similarly restores inner
`Basic`, `Extension Dialing`, and `Options`, while standalone and embedded
Voicemail share inner `Basic` and `Options`. The final consistency pass caught
and removed a reverted filled-button implementation: the Basic/Advanced adapter
is again a thin wrapper around `FormTabBar`, so underline styling, overflow,
keyboard behavior, sticky positioning, icons, and ARIA naming stay single-source.
Read-only details, page-level navigation, and schema/workflow-confirmed
single-section forms remain tabless; the shared layout is not used to invent
empty Advanced settings.

The Device parity fixture now records the older Monster tab matrix separately
from GridPBX's installed-schema extensions. Smartphone retains schema-backed
Caller ID, Audio, and Video sections, while Fax and ATA retain schema-backed
Audio, without falsely claiming those additions appear in the reference
Monster template. The final focused verification passed 12 UI files / 55 tests,
Vue typecheck, isolated E2E TypeScript typecheck, and three representative
non-mutating isolated headless Playwright checks for the dynamic Device matrix,
Conference's nested workflow, and Voicemail's cross-tab validation. Earlier
focused Menu, Queue, Directory, and embedded Voicemail checks remain valid; no
Switch resource mutation was required for this presentation-only consistency
pass.

## Delivery order

After Device, audit mutation-capable entities in dependency order:

1. User/Extension, Voicemail, Directory, and LineKey
2. Callflow, Group, Menu, Queue/Agent, Conference, and Temporal routing
3. Blacklist, Fax box, and Phone number management
4. Media and account configuration

CDRs, recordings, services, and system status use a read/display audit rather
than artificial create/edit operations.

### Services and Billing presentation re-audit (2026-08-31)

Services remains an account-scoped read-only projection of standing, billing
ownership/cycle, aggregate impact, plans, quantities, limits, ledger summaries,
transactions, reconciliation checks, and synchronization history. Installed
Kazoo exposes plan assignment/removal, overrides, manual quantities, top-up,
quote, synchronization, and reconciliation as distinct endpoints with their
own authorization and billing effects. They are not Advanced fields on a
generic Service document. Monster likewise separates service-plan and item
views from account billing and transaction workflows.

The dedicated Billing workspace keeps invoice summaries, receipts, successful
payment confirmations, and Switch transactions distinct. Each record opens a
single read-only detail view. A PDF button appears only after an authoritative,
account-scoped detail response confirms a safe document; it is an operation,
not a form field. Neither Service nor Billing record detail renders an
artificial Basic/Advanced selector or financial mutation controls. All public
relationships use account-scoped UUIDs, while bookkeeper/provider identifiers,
internal keys, raw upstream payloads, and credentials remain private.

The administrator-only Authorize.Net sandbox verifier is a separate,
default-off command workspace on Billing. Hosted tokenization, independent
capability flags, explicit confirmation, bounded amounts, idempotency, and
public attempt/profile UUIDs remain unchanged. Charge, void, refund, profile,
and webhook-recovery actions do not belong in a Service or Billing-record
Advanced tab, and no production financial operation is enabled by this
presentation audit.

### System Status presentation re-audit (2026-08-31)

System Status is not a durable Switch entity and has no create/edit form. Its
strict account-scoped response combines short-lived, ten-second-cached probes
for Presence subscription diagnostics, parked-call aggregate count, Webhook
catalog/configuration counts, SMS/MMS inventory endpoints, Port Request
inventory, and carrier-configuration endpoint shape. Raw account/call/resource
IDs, SIP state, Webhook destinations and attempts, message content, Port
Request data/documents, carrier catalogs, number inventory, quotes, and charges
are discarded before the public boundary and are never projected as editable
fields.

The only page operation is Refresh. Basic/Advanced tabs would incorrectly
suggest a writable System Status document, while restart, presence commands,
park/retrieve, Webhook mutation, message sending, Port Request transitions, and
number acquisition/release each belong to separate runtime, security, carrier,
or regulated workflows. Monster has separate Numbers, Porting, Messaging, and
Webhooks applications rather than one combined System Status editor. GridPBX
therefore keeps the dashboard single-view and read-only, with unavailable
operations expressed as explicit capability gates rather than buttons.

### Sandbox payment boundary checkpoint (2026-08-31)

The Billing workspace contains a separate administrator-only Authorize.Net
sandbox card. It uses the provider-hosted Accept UI contract and sends Laravel
only the returned opaque token; raw card number, expiry, and CVV fields do not
exist in the GridPBX form or request contract. Payment attempts, reversals, and
profiles use account-scoped public UUIDs in the UI. Internal primary keys,
provider transaction/profile identifiers, HMAC idempotency values, request
fingerprints, opaque tokens, and raw provider payloads are never rendered.

The implementation remains default-off behind the global payment flag, a
separate mutation flag, sandbox-only environment validation, and independent
charge, void, refund, and profile flags. Charge additionally requires the
hosted-tokenization public key at both capability and service boundaries.
Explicit typed confirmation, one-dollar default safety caps, a three-per-minute
account/user/IP limiter, encrypted private provider references, append-only safe
events, and source-operation reservation provide the initial sandbox safety
boundary. Indeterminate results are not automatically retried.

Focused payment tests passed with 26 Laravel tests and 179 assertions, seven UI
tests, Vue and isolated E2E TypeScript checks, and PHP syntax/format checks. One
isolated authenticated headless check passed in 1.7 seconds with every live
payment flag false; it observed null client configuration, no provider script,
zero payment mutations, and no Authorize.Net browser request. The explicitly
opt-in sandbox transaction case was not rerun during this checkpoint, so it
created no additional payment record. An earlier separately authorized `$1.00`
sandbox charge remains the single stored succeeded attempt; an independent
MySQL read confirmed its public UUID/status/amount and the presence—but not the
value—of its encrypted private provider reference. Void, refund, and profile
creation remain provider-mocked only and are not claimed as live verified.
