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
| Initial Device during create | The aggregate exposes a small starter Device payload with plain make/model and SIP fields; it does not share Device catalog validation, type capabilities, icons, or conditional fields | Keep this as an explicitly minimal starter endpoint or extract reusable Device sections; do not duplicate the full Device editor inside User creation. Validate every exposed starter field with the Device domain services and direct users to the full Device editor for advanced configuration |
| Managed Voicemail during create/edit | The aggregate exposes only enablement, notification emails, transcription, and PIN setup | Keep the intentionally small mailbox bootstrap and route advanced mailbox configuration to the Voicemail editor |
| Current User schema coverage | Calling options, contact-list exclusion, privacy, credentials, and hotdesk are typed; caller-ID variants, call forwarding/recording/restrictions, media, MOH, ringtones, flags, formatters, dial plan, metaflows, profile, pronounced name, feature/role policy, and verified status remain unimplemented or policy-gated | Add bounded, capability-driven sections in batches; do not expose raw nested JSON or copy every Monster feature tile without checking the connected schema |
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
| ASR-dependent fields | The schema supports transcription, while actual provider availability is returned only by the Switch authentication response and is not yet retained by the GridPBX session contract | Publish the known/unknown capability state, preserve the schema field, and do not claim runtime availability until session capability projection is implemented |
| Notification precedence | Save-after-notify overrides delete-after-notify in Switch | Disable and clear Delete in Vue when Save is enabled, and reject the contradictory pair at both Zod and Laravel boundaries |
| `flags[]` ownership | The schema defines flags as values set by external applications | Preserve projected flags on every update; do not expose them as an operator-editable form field |
| `notify.callback` | The schema provides a typed callback object but Monster does not expose it in the basic mailbox workflow | Provide a bounded advanced workflow for number, disabled state, attempts, interval, timeout, and ordered schedule; do not expose raw callback JSON |
| Intentional differences | `announcement_only` is unsupported by the connected schema; `mp4` is accepted by the current schema even though Monster hid it; greeting and message operations already use dedicated workflows | Preserve these intentional schema-first decisions |

### Wave 2 implementation batches

1. Extract the shared invalid-control treatment and remove duplicate client
   validation alerts in User/Extension and Voicemail. Add focused component
   tests before applying the helper to later domains.
2. Align User/Extension option controls and the minimal nested Device/Voicemail
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
longer create a duplicate global alert. Focused unit tests, TypeScript, the
production build pass. The isolated headless Extension/Voicemail walkthrough
remains pending because Chromium cannot launch inside the current sandbox and
the escalated launch was not approved. Later entity forms have not yet been
declared compliant by this result.

Batch 2 is complete for User/Extension option sourcing and the starter Device
boundary. Timezone, language, and presence now use API-backed Headless UI
choices, represent inheritance as `null`, and preserve a projected legacy
value during edit. The Devices domain publishes the starter capability set.
The aggregate wizard permits only SIP-capable endpoint types that it can fully
create, conditionally permits MAC identity for provisionable desk/fax/ATA
types, and directs catalog/model/line-key/advanced work to the full Device
editor. Cellphone, landline, and SIP URI remain available in the full Device
workflow, where their required destination fields exist.

The same batch replaces Voicemail's native timezone/datalist and assignment
select with API-backed Headless UI listboxes. Account inheritance is `null`,
public Extension UUIDs are the only assignment identifiers exposed, and legacy
projected values are retained safely during edit. New protected mailboxes—and
existing mailboxes enabling protection—now require a 4–6 digit PIN in both Zod
and Laravel; an already protected mailbox may be edited with a blank PIN to
keep the existing write-only secret.

The Voicemail callback object is now represented by an entity-organized Switch
DTO and bounded API/UI fields. Turning callback configuration off removes the
public `notify` object on the full Crossbar update. Notification disposition is
deterministic: Save clears Delete in the form, while contradictory direct API
payloads are rejected. External `flags[]` are deliberately not editable and
are preserved from `switch_json` during updates. The options response reports
transcription as schema-supported with runtime availability `null`; the form
shows that honest unknown state until authentication capability projection is
available. An isolated authenticated headless walkthrough created an
unassigned mailbox with a paused callback, edited it, cleared the callback,
and removed the disposable mailbox. The walkthrough also established that an
unassigned mailbox must omit `owner_id`; the connected Switch rejects an
explicit JSON `null` for that field.

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

### Group and Menu follow-through (2026-08-29)

Group now uses a domain Zod/composable boundary, shared invalid styling, and
Headless UI choices for music-on-hold and member selection. Nested member
errors resolve to the member editor, API field errors remain inline, and the
authenticated headless walkthrough verified the red name border and a
viewport-bounded music listbox. The current Group schema contains only name,
endpoints, music-on-hold, and external flags; GridPBX exposes the first three
and preserves the external flags without accepting them from operators.

Menu applies the same baseline to every numeric, PIN, pattern, toggle, and
media control. Its current schema supports custom invalid, transfer, and exit
media in addition to the smaller legacy Monster workflow, so those bounded
controls are an intentional schema-backed superset. External flags are never
operator-editable. A blank recording PIN on edit means keep the existing
write-only value: `grid-api-switch` obtains it only for the outbound full
Switch update and never returns or persists the secret in GridPBX. The
authenticated headless walkthrough verified multiple red invalid controls,
inline-only errors, a non-clipping media listbox, and clean browser/network
state.

### Queue and Agent follow-through (2026-08-29)

Queue now uses the same domain Zod/composable, Headless UI, invalid-control,
and inline-only error baseline. The current Switch schema additions are
represented as virtual fields read from and written back to `switch_json`:
connect announcement media, create-only maximum priority, announcement
interval, position/wait announcements, and the optional all-or-none set of
four custom announcement prompts. Only public Media UUIDs cross the UI/API
boundary; Laravel resolves Switch resource identifiers inside the account.
No JSON-derived field added a MySQL column.

An authenticated isolated lifecycle created a disposable queue with priority
and periodic announcements, edited the interval, cleared the announcement
object, and deleted the queue. The same run verified the Headless UI menu stays
inside the viewport and validation remains inline with red controls.

`cdr_url` and `recording_url` remain deliberately absent from the operator
form until an outbound URL/SSRF allowlist policy is available. Existing values
are preserved across full Switch updates but are never returned by the API.
Agent status actions use a separate Zod/composable and Headless UI command
form with conditional pause-timeout validation. Automated live status changes
are intentionally not sent to real agents; the audited API boundary remains
covered with an isolated gateway test.

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

### Blacklist follow-through (2026-08-29)

Blacklist now uses the shared domain composable, Zod, inline-only error, and
red invalid-control baseline. Name and blocked-number errors attach directly
to their controls, the submit path uses `novalidate` so browser-native prompts
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

### Call activity follow-through (2026-08-29)

Call History and Recordings are read/display workflows rather than artificial
CRUD forms. Their search, direction, outcome/audio availability, date, and
duration controls now follow the same form baseline: Headless UI-backed
choices, domain composables, `novalidate`, matching Zod/Laravel constraints,
red invalid borders, and field-local messages. Number inputs are normalized at
the Zod boundary because Vue emits numeric runtime values for `type="number"`
even though URL query filters are serialized strings.

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
the operation is reversible. Realm, asserted identity, advanced routing,
billing/top-up, zones, and notifications remain explicitly gated. Focused SDK,
Laravel, Vue, TypeScript, and isolated authenticated Playwright checks pass.

## Callflow guided-form audit

The current Callflow editor deliberately covers safe entry-point and root-target
mutations; it is not presented as a complete visual implementation of every
`callflows.*.json` module schema.

| Area | Switch contract | GridPBX treatment | Status |
| --- | --- | --- | --- |
| Route identity | `callflows.name` | Required, trimmed, maximum 128 characters, inline Zod/API errors | Implemented |
| Phone entry points | `callflows.numbers[]` | Projected inventory UUIDs only; create requires one, update may clear assignments; extension and non-inventory numbers remain preserved | Implemented |
| Root destination | `callflows.action` plus selected module schema | Headless UI type/target selectors resolve public UUIDs server-side | Implemented for the allowlisted resource modules |
| Existing module data | selected `callflows.<module>.json` data | Retained when only the target of the same module changes; old data is discarded when intentionally changing module type | Implemented and package-tested |
| Children and unknown branches | recursive `children` object | Preserved losslessly by the Switch write DTO and displayed structurally | Implemented |
| Unsupported or unresolved root | module catalog and current projection | Locked in the editor response and API mutation path; no silent fallback target is selected | Implemented |
| Wildcard fallback branch | `children._` | Optional Headless UI selectors resolve public UUIDs server-side; create/replace/clear preserves sibling branches and same-module data | Implemented |
| Unsafe fallback subtree | nested, unsupported, or unresolved `children._` | Locked in the editor response and API mutation path and preserved losslessly | Implemented |
| Menu key branches | `children.timeout`, `children.0`–`children.9`, and `children.*` | Typed per-key operations with public UUID targets; add/replace/clear does not replace the full child map | Implemented |
| Legacy and unsafe Menu branches | `children.#`, unknown keys, nested or unresolved key nodes | Displayed as preserved read-only state; `#` cannot be newly created | Implemented |
| Numeric branch JSON shape | numeric child object properties | Normalized as JSON objects in Switch writes, MySQL JSON, and API resources so `{"0": ...}` never becomes a list | Implemented and tested |
| Temporal Rule Set match | `data.rule_set`, `children.rule_set`, and `children._` | Shows ordered public Rule UUIDs for context; create/replace/clear resolves only public destination UUIDs and preserves additional temporal keys | Implemented and tested |
| Visual route map | Recursive `flow.children` tree | Scroll-bounded connected nodes with semantic branch badges and keyboard-accessible selection; unknown child keys become numbered preserved labels in the public contract while internal keys remain lossless | Read-only foundation implemented and headless-tested |
| Drag-and-drop editor placement | Full route graph and action palette | The interactive graph canvas lives on the main Callflow page, not inside a slide-over; a right-side panel may edit only the currently selected node | Required layout decision |
| Selected-node inspector | Public safe tree contract | Shows public branch breadcrumbs, module, resolved label, reference state, child count, and honest editability status; never displays raw node data or upstream IDs | Implemented and headless-tested |
| Module reference palette | Primary `callflows.*.json` action schemas | Searchable categorized catalog of all 73 primary local schemas, labeled as guided, planned, or capability-gated; no inactive item is presented as an edit action | Implemented and headless-tested |
| Other keyed recursive branches | Direct temporal rules and module-specific branch schemas | Read-only structural view until each module editor has reference and round-trip coverage | Pending |

The selectable node-and-connector diagram, safe inspector, and schema-backed
module palette are now the visual foundation for the advanced editor. The
main page will own the drag-and-drop canvas so it has sufficient room for
branching routes; right-side panels are limited to selected-node properties.
The remaining work adds recursive linear and keyed mutations plus
module-specific node forms. Unsupported nodes remain locked and lossless.
GridPBX uses its Tailwind visual language rather than copying Monster's
styling.

Focused Switch package, Laravel feature, Vue schema/store, and isolated
authenticated headless Playwright checks cover these boundaries without
creating a live route.

## Delivery order

After Device, audit mutation-capable entities in dependency order:

1. User/Extension, Voicemail, Directory, and LineKey
2. Callflow, Group, Menu, Queue/Agent, Conference, and Temporal routing
3. Blacklist, Fax box, and Phone number management
4. Media and account configuration

CDRs, recordings, services, and system status use a read/display audit rather
than artificial create/edit operations.
