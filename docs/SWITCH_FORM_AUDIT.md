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
| ASR-dependent fields | The schema supports transcription, while actual provider availability is returned only by the Switch authentication response and is not yet retained by the GridPBX session contract | Publish the known/unknown capability state, preserve the schema field, and do not claim runtime availability until session capability projection is implemented |
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
keeps the smaller aggregate bootstrap so account inheritance is not replaced by
unseen default values.

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
| Direct Temporal Rules | ordered `data.rules[]`, `children.<rule_id>`, and `children._` | Selects and reorders public Rule UUIDs, requires one public match destination per rule, maps raw branch keys only on the server, and explicitly clears removed rules while preserving unrelated children | Implemented; SDK, API, Zod, and isolated-headless tested |
| Callflow entry node | Document-level `numbers[]`, `patterns[]`, and `name` above `flow` | A distinct Kazoo-aligned top card displays the primary number/pattern and additional-entry count, then connects to the actual root action. Entry data is never synthesized into `flow` | Implemented and isolated-headless verified |
| Visual route map | Recursive `flow.children` tree | Scroll-bounded connected nodes with semantic branch badges, centralized module-specific icons, and keyboard-accessible selection; unknown child keys become numbered preserved labels in the public contract while internal keys remain lossless | Interactive foundation implemented and headless-tested |
| Complex route demonstration | Production visual-route components with a deterministic in-memory tree | An explicit **Open complex demo** action renders a 20-node, eight-level route with temporal, Menu, Caller-ID, queue, conference, media, voicemail, and terminal branches. It is visibly labeled UI-only, uses public-safe synthetic references, disables every mutation affordance, and sends no Callflow write request | Implemented and isolated-headless verified |
| Main-page editor placement | Full route graph and action palette | The graph uses the full available Callflow content width inside small responsive gutters rather than a narrow centered maximum. A compact categorized palette starts in a sticky Kazoo-style right rail, can be moved within the viewport, and has an explicit Dock control; typed mutation forms remain in right-side panels | Implemented and isolated-headless verified |
| Tree mutations | Recursive node and branch operations | Pointer drag-and-drop and the keyboard workflow move guided subtrees into empty public `_`, Menu digit/Star/timeout, and Temporal Rule Set branches. Guided palette cards are also draggable onto eligible nodes; a drop opens the same validated form and never writes until explicit submission. The node modal supports insert-before when the source default continuation is empty and swapping two disjoint subtrees. Laravel and the Switch adapter reject root, preserved, unsupported, unresolved, no-op, and cyclic operations while preserving complete node data server-side | Safe move, palette drop, and occupied-position reorder boundaries implemented |
| Selected-node information | Public safe tree contract | An accessible modal shows public branch breadcrumbs, module, resolved label, reference state, child count, honest editability status, and safe move/reorder controls; it never displays raw node data or upstream IDs | Implemented and headless-tested |
| Guided reference action forms | Resource-backed `callflows.*.json` modules | Palette actions add User/Extension, Device, Voicemail, Callflow, Media, Directory, Group, Queue Member, Menu, Conference, Fax Box, and Temporal Rule Set nodes only to empty schema-valid branches. The selected-node modal retargets the same modules while preserving module data and complete children | Implemented across SDK, API, Zod, Vue, and isolated headless tests |
| Schema-backed inline action forms | `callflows.sleep`, `tts`, `collect_dtmf`, `send_dtmf`, `flush_dtmf`, `dead_air`, `language`, `manual_presence`, `group_pickup`, `page_group`, `receive_fax`, `ring_group_toggle`, `hotdesk`, `do_not_disturb`, `conference` service mode, `voicemail` check mode, `record_call`, `record_caller`, `missed_call_alert`, `set_cid`, `prepend_cid`, `set_alert_info`, `response`, `hangup`, `set_variable`, `set_variables`, `branch_variable`, and `branch_bnumber` | Zod and Laravel validate current schema fields plus defensive operational bounds in a right-side panel. Manual Presence accepts a bounded local presence ID or one explicit realm, the schema statuses `idle`, `ringing`, and `busy`, and `skip_module`; the visible create default is Monster's explicit `busy`, while omitted legacy status is read as the Kazoo schema default `idle`. Group Pickup follows Monster's single-target workflow and accepts exactly one account-scoped public Device, Extension, or Group UUID; the server resolves it to Kazoo's mutually exclusive `device_id`, `user_id`, or `group_id`, while private `approved_*` restrictions and unknown properties remain hidden and losslessly preserved. Ambiguous or unresolved existing targets stay read-only. Page Group accepts one to twenty distinct account-scoped public Device UUIDs, maps raw Kazoo endpoint IDs only on the server, and exposes only one-way/two-way audio plus `skip_module`; materialized timing values and unknown endpoint fields stay private and preserved, while user/group expansion, barge, unsafe timings, and unresolved endpoints remain read-only. Receive Fax accepts one account-scoped public Extension UUID, resolves it server-side to Kazoo's raw `owner_id`, writes `media.fax_option` as `auto`, `true`, or `false`, and supports `skip_module`; unknown nested `media` fields remain private and losslessly preserved, while unresolved owners remain read-only. Ring Group Toggle accepts only an account-scoped public Callflow UUID whose synchronized module summary contains `ring_group`; Laravel resolves the raw `callflow_id` only for Switch writes, while feature-code or non-ring-group targets are rejected and unavailable targets are read-only. Login, logout, and `skip_module` are the only public fields, and unknown node data remains private and losslessly preserved by the Switch DTO. Hotdesking is resource-free at design time and exposes only `action` (`login`, `logout`, or `toggle`) plus `skip_module`; raw or server-owned `id`, `interdigit_timeout`, and unknown node properties remain private and are preserved by typed edits. Do Not Disturb is also resource-free publicly and accepts only `action` (`activate`, `deactivate`, or `toggle`) plus `skip_module`; raw `id` and unknown node data remain private and lossless, with no public target mapping. Conference Service uses a public-only `service_mode: true` discriminator which Laravel removes before writing `conference` without a raw `id`; the configured Conference action remains a separate account-scoped public-UUID workflow. Only `skip_module` is managed, while unknown discovery settings stay private and lossless. Check Voicemail writes only resource-free `action: check` and `skip_module`, never accepts or exposes a mailbox `id`, and keeps Kazoo's caller-ID and single-mailbox auto-login flags private and server-owned. Missed Call Alert accepts public extension UUIDs or validated email addresses and maps extension recipients to Switch IDs only on the server. Alert-Info rejects CR/LF header injection. Response accepts final SIP error codes and optional cause text while preserving existing Switch-managed media. Hangup exposes only the schema-defined skip behavior. Set Variable is restricted to Kazoo's mapped `call_priority` variable, values `0`–`255`, and schema-supported channel choices; unsupported existing variable names are redacted, preserved, and read-only. Set CAV uses repeatable virtual key/value rows in the form but writes the exact schema-defined `custom_application_vars` object, with bounded safe keys, duplicate rejection, `export`, and `skip_module`; unsupported existing maps remain redacted and lossless. Branch Variable is restricted to `custom_channel_vars.call_priority`, exposes only the default and priority `0`–`255` result branches, and renders those branches as conditions rather than generic keys. Branch Bnumber exposes Kazoo's `hunt`, optional safe `hunt_allow`/`hunt_deny`, and `skip_module` fields; branch mode accepts exact dial-string children, while hunt mode is blocked until those exact branches are removed. The Switch DTO merges only managed public properties into existing node data and preserves the complete subtree. Recording URLs, HTTP methods, origins, media names, and other server-owned values are never exposed or accepted | Implemented across SDK, API, Zod, Vue, focused tests, and isolated headless walkthroughs; disposable live Call Priority, Branch Bnumber, Set CAV, Manual Presence, Group Pickup, Page Group, Receive Fax, Ring Group Toggle, Hotdesking, Do Not Disturb, Conference Service, and Check Voicemail create/edit/reopen/delete verification runs against Switch |
| Ring Group guided form | `callflows.ring_group` strategy, ordered endpoints, repeats, computed timeout, `ignore_forward`, `fail_on_single_reject`, and `skip_module` | Accepts 1–20 ordered account-scoped public Device UUIDs, simultaneous, in-order, or weighted-random strategy, bounded endpoint delay/timeout, and 1–3 attempts. Weighted-random requires an explicit `1`–`100` weight for every Device and zero delay. The two bridge flags are strict booleans: ignore forwarding defaults to `true`, while stop-on-one-rejection defaults to `false`. Laravel maps only raw Device IDs and computes the hidden top-level attempt timeout with a 120-second cap. User/group expansion, ringback/ringtones, unsafe timing, malformed legacy flags, and unresolved endpoints remain private and read-only. The Switch DTO preserves private and unknown node/endpoint fields | Implemented across SDK, API, Zod, Vue, focused tests, and disposable live simultaneous-to-weighted-random and bridge-flag create/edit/reopen/delete verification; no media-leg call originated, so this remains a guided foundation |
| Caller-ID condition branches | `check_cid` and `cidlistmatch` `children.match` / `children.nomatch` | Regex-mode Check CID has safe-regex validation, stable public result branches, and optional all-or-none identity override fields. The public Extension UUID is resolved server-side into Kazoo's nested `caller_id.external` and `user_id` values. Existing absolute caller-number branches are numbered preserved branches; their nodes and destinations cannot be rewritten. Caller-ID List Match selects an account-scoped projected List by public UUID; Laravel resolves the private List ID and exposes only stable `match`/`nomatch` branches. Lists and entries retain separate redacted `switch_json` snapshots and are never confused with account Blacklists. Standalone list metadata and number/pattern entries use account-scoped API CRUD and a shared-control slide-over editor with safe-regex validation. The Switch adapter hydrates summary-only entry collections and supplies the schema-required parent `list_id` internally | Regex-mode Check CID, Caller-ID List Match, and standalone Caller-ID List CRUD implemented; absolute Check CID mode intentionally read-only; authenticated local Switch create/edit/reopen/clear/delete verified |
| Captured-number branches | `branch_bnumber` exact child keys, `hunt`, `hunt_allow`, `hunt_deny`, and `_` continuation | Branch mode accepts bounded dial strings (`0`–`9`, `*`, `#`, and `+`) as typed condition branches. Hunt mode exposes safe optional allow/deny regexes and only the default continuation; enabling it with exact children is rejected. Existing data and subtrees remain lossless | Implemented across SDK, API, Zod, Vue, focused tests, and disposable live Switch verification |
| Module reference palette | Installed Kazoo palette registry plus connected-version-safe current-schema actions | Expanded categories use the exact installed Kazoo section names, membership, and order, without an invented “Schema extensions” category. Supported current-schema actions that are absent from the installed palette are search-only, so existing guided workflows remain reachable without changing the visible native registry. All entries and diagram/editor nodes use one centralized corresponding icon map. Guided resource and supported inline actions open their schema-appropriate right-side form; planned and restricted entries remain non-mutating | Implemented and headless-tested |
| Installed palette classification | All 49 visible Monster actions across Basic, Advanced, Time of Day, Ring Group Toggle, Hotdesking, Do Not Disturb, Caller-ID, Call Recording, and Call Forwarding | Exactly 40 actions are guided through a public destination or typed inline contract. Nine variants are capability-gated: Pivot, DISA, Global Carrier, Account Carrier, Webhook, Dynamic CID, and Call Forwarding activate/deactivate/update. No visible action remains planned. The catalog test fixes the exact counts and restricted IDs; the API test rejects every restricted module before gateway access; the isolated browser opens every category, verifies the 40/9/0 status split and disabled restricted controls, and sends no Callflow mutation | Complete and isolated-headless verified |
| Other keyed recursive branches | Module-specific branch schemas beyond Menu, temporal routing, Caller-ID, Call Priority, and Branch Bnumber | Read-only structural view until each module editor has reference and round-trip coverage | Pending |

### Callflow action security audit

The 2026-08-30 audit checked the installed schemas and compiled Kazoo runtime,
then used Monster only to confirm the intended operator workflows.

| Action | Installed schema and runtime behavior | Security and product decision |
| --- | --- | --- |
| ACDC Queue | `callflows.acdc_queue` permits `action` (`login` or `logout`), requires raw Queue `id`, and permits `skip_module`. Installed `cf_acdc_queue` answers the call, derives the raw Agent ID from the authorizing endpoint's single Hotdesk user or owner, adds or removes the raw Queue ID in that account-local User's `queues` list, publishes the matching Queue membership event, plays the result prompt, and continues. It does not accept a design-time Agent and has no PIN challenge | Guided and search-only as `{ action, queue_id, skip_module }`. The public Queue UUID is resolved account-locally to raw `id` only at the Switch boundary; unsynchronized and cross-account Queues are rejected. Public readback returns the Queue UUID/label and never the raw ID. Existing unresolved targets are read-only, and unknown node fields remain private and losslessly preserved. The editor warns that the no-PIN behavior belongs behind a trusted feature-code route. Focused SDK/API/resolver/Zod/component tests and a disposable isolated lifecycle verified both actions, mapping, redaction, reopen, cleanup, soft deletion, and zero active Switch matches. No media leg was originated, so Agent inference and live membership mutation remain compiled-runtime evidence |
| Ring Group bridge flags | `callflows.ring_group.ignore_forward` is a boolean with schema default `true`; installed `cf_ring_group` passes its binary boolean to the bridge as `Ignore-Forward`, which maps to FreeSWITCH's fatal outbound-redirect behavior. `fail_on_single_reject` is an optional boolean passed as `Fail-On-Single-Reject`; absence leaves the FreeSWITCH behavior disabled | Guided as two strict shared-checkbox controls with public defaults `true` and `false`. Neither field accepts a URL, identifier, or untrusted nested payload. Malformed legacy values make the node read-only. A disposable isolated lifecycle verified both defaults, the `false`/`true` edit, authoritative reopen, raw values, public raw-ID/private-field redaction, private ringtone and unknown-field preservation through the production DTO path, browser cleanup, MySQL soft deletion, and zero active Switch matches. No media leg was originated, so the installed runtime establishes live bridge semantics |
| Ring Group Toggle | `callflows.ring_group_toggle` requires `action` (`login` or `logout`) and `callflow_id`, with optional `skip_module`. Installed `cf_ring_group_toggle` answers the call, opens that target only in the caller's account database, recursively visits every `ring_group`, and changes `disable_until` only on `user` endpoints whose raw ID equals `kapps_call:owner_id(Call)`. Login writes `0`; logout writes `66269664000`. Device/group endpoints and other users are unchanged. The module plays logged-in, logged-out, or invalid-choice prompts, saves the complete callflow with bounded conflict retries, and continues | Guided for synchronized non-feature callflows containing a Ring Group. The public API/UI use only the account-scoped target Callflow UUID; Laravel maps the raw target at the Switch boundary and rejects cross-account or non-ring-group targets. Focused SDK coverage proves unknown node fields survive typed edits and remain absent publicly. Disposable live configuration verified both actions, edits, reopen, raw/public mapping, cleanup, and public redaction. Crossbar sanitized the attempted unknown marker, so live preservation is not claimed. No media leg was originated, so compiled-runtime inspection rather than a live call proves the owner-only membership rule and prompts |
| Hotdesking | `callflows.hotdesk` permits `login`, `logout`, `toggle`, and `bridge`, with optional `id`, `interdigit_timeout`, and `skip_module`. Monster exposes only login/logout/toggle and writes only `action`. Installed `cf_hotdesk` does not consume a feature-code capture value: when no server-owned `id` is present it prompts for the account Hotdesk ID, except logout can infer the sole owner of the authorizing endpoint. Login loads the user's Hotdesk profile, enforces its PIN when `require_pin = true`, optionally removes that user from other devices, and writes the raw user ID under the current authorizing device's `hotdesk.users`. Logout never requests the profile PIN and removes either the current device or all devices according to `keep_logged_in_elsewhere`. Toggle chooses login when the user has no active Hotdesk endpoints and logout otherwise. Each mutation saves the complete Device document and sends an unsolicited MWI update | Guided only as the resource-free public contract `{ action: login|logout|toggle, skip_module }`; `bridge`, design-time user selection, raw `id`, and timing controls are rejected or remain server-owned. The form warns that logout and toggle's logout path do not prompt for a PIN and should remain behind a trusted feature-code route. Focused SDK coverage proves raw/private and unknown fields survive typed edits, while API/resolver/UI tests prove they remain absent publicly. A disposable isolated run created, edited, and reopened all three actions; an independent raw watcher confirmed the exact actions, `skip_module = true`, and no `id` or `interdigit_timeout`. No media leg was originated, so prompts, PIN entry, and Device session mutation remain compiled-runtime evidence rather than a live-call claim |
| Do Not Disturb | `callflows.do_not_disturb` permits `action` (`activate`, `deactivate`, or `toggle`), optional raw `id`, and `skip_module`. Monster's normal palette writes only the action. Installed `cf_do_not_disturb` uses `id` only when server-owned data already provides it; otherwise it resolves `kapps_call:owner_id(Call)` and falls back to `authorizing_id(Call)`, opens only the caller account database, accepts only User or Device documents, and changes only `do_not_disturb.enabled` with conflict retry | Guided only as resource-free `{ action, skip_module }`. GridPBX rejects legacy `enable`/`disable` spellings and any public raw `id`, returns `target: null` with `reference_status: not_applicable`, and preserves private/unknown node fields losslessly. There is no public-to-raw mapping: runtime identity resolution stays in Kazoo. The module has no PIN challenge, so the editor warns that this must remain behind a trusted feature-code route. Focused tests protect the contract and preservation; a disposable isolated run verified all three create/edit/reopen paths, raw/public redaction, and cleanup. No media leg was originated, so the account-local User/Device mutation remains compiled-runtime evidence |
| Call Forwarding | `callflows.call_forward` permits `activate`, `deactivate`, `update`, `toggle`, and `menu` plus `skip_module`; Monster's normal palette exposes the first three, while its feature-code registry also defines toggle. Installed `cf_call_forward` resolves the authorizing endpoint's owner or falls back to the endpoint, answers the call, and has no PIN challenge. Activate and update use a feature-code capture or prompt for an arbitrary 3–20 digit destination; toggle reuses a stored number or collects one. The runtime writes `call_forward.enabled` and `call_forward.number` on the complete account-local document with conflict retry, but performs no ownership, destination-class, emergency/premium/international, rate/lockout, or loop validation | Capability-gated as a category. All visible actions are disabled with a toll-fraud explanation; public create/update requests and direct Switch DTO writes reject the module, and existing nodes plus descendants stay read-only. Public projection exposes only the safe action and skip summary for labeling; the raw forwarding number and unknown properties remain private. A focused SDK regression proves private data and the subtree survive an unrelated typed edit. API, validator, resolver, public-tree, catalog, detail-panel, type, and isolated headless tests passed; the browser emitted no Callflow mutation. No disposable Switch write or live call was attempted because the audited runtime lacks the controls required to exercise it safely |
| ACDC Agent | `callflows.acdc_agent` permits `login`, `logout`, `paused`, and `resume`, optional `presence_id`, one of six presence states, integer `timeout`, and `skip_module`. Installed `cf_acdc_agent` answers the call and infers a raw Agent ID from the authorizing endpoint's single Hotdesk user or owner. It has no PIN challenge and sends no Queue ID. The ACDC handler opens that account-local raw User document without a queue-membership check at this boundary. `paused` becomes the runtime `pause` event; an omitted timeout defaults to 600 seconds, while message validation accepts any non-negative integer with no upper bound. Login, logout, pause, and resume can change live agent and presence state. The action is absent from the installed Monster palette | Capability-gated and search-only. All four schema variants are disabled with direction to the authenticated Queue Agent status panel. Public API and direct Switch DTO writes reject the module; existing nodes and descendants are preserved and read-only. The public projection exposes only `action` and `skip_module`; inferred raw Agent IDs, presence fields, timeout, and unknown properties remain private and lossless. The supported Queue Agent panel already resolves an account-scoped public Extension UUID, verifies membership, bounds pause time, authorizes the operator, and audits the result. Focused SDK/API/resolver/public-tree/catalog/type checks and one isolated headless no-mutation test passed. No disposable Switch write or live agent-state mutation was attempted because the missing feature-code authentication and membership controls are the reason it remains gated |
| Eavesdrop / Eavesdrop Feature | `callflows.eavesdrop` permits one raw target `device_id` or `user_id`, raw `approved_device_id`, `approved_user_id`, or `approved_group_id`, and `skip_module`. `callflows.eavesdrop_feature` replaces the direct target with raw `group_id` and captures an extension at runtime. Direct runtime finds active target channels and starts DTMF-enabled live monitoring; Feature resolves the captured account callflow to its first Device/User node, applies an optional direct Group membership restriction, and delegates to direct Eavesdrop. Missing approval fields deny access, but multiple approval fields do not combine: only the first configured Device/User/Group field is evaluated. Authorization Group expansion and Feature target Group matching also use different membership semantics. Both actions stop the current callflow and are absent from the installed Monster palette | Capability-gated and search-only. Public API and direct SDK writes reject both modules; existing nodes and every descendant remain preserved and read-only. Public projection exposes only `skip_module`; raw target, approval, and Group IDs plus unknown properties never cross the API and survive unrelated typed edits. Enabling requires an account-scoped supervisor entitlement, explicit target policy, immutable monitor audit, privacy/consent and notification policy, bounded listen/interaction modes, and live-call abuse verification. Focused SDK/API/resolver/public-tree/catalog/type checks and one isolated headless no-mutation test passed. No disposable Switch write or monitored call was attempted because those missing controls are the reason the feature is gated |
| Pivot | `callflows.pivot` requires `voice_url`, permits HTTP or HTTPS, GET or POST, form or JSON POST bodies, Kazoo or TwiML responses, optional `cdr_url`, debug persistence, and a request timeout up to 5000 ms. The runtime sends account, call, caller, callee, custom application variable, custom SIP header, recording, transcription, and user-variable data. Kazoo-format responses can replace live call control; TwiML can issue relative or absolute follow-up requests. The CDR callback is a separate unauthenticated form POST after channel destruction | Capability-gated. The installed worker applies no destination allowlist, DNS/IP/private-network rejection, application authentication header, callback signature, redirect-chain policy, or explicit TLS policy. Debug mode can persist request and response bodies. GridPBX must not expose Pivot until server-owned HTTPS origins, DNS rebinding-safe egress enforcement, bounded response size/iterations, TLS verification, secret-backed authentication, signed callbacks, data-minimization controls, and audit/kill-switch behavior are defined outside user-editable callflow JSON |
| DISA | The installed public schema declares only `skip_module`, but the runtime and Monster workflow consume undeclared `pin`, `retries`, `interdigit`, `max_digits`, `preconnect_audio`, `use_account_caller_id`, and `enforce_call_restriction` values. A missing PIN explicitly permits dialing, restriction enforcement defaults to false, and a matched account callflow is executed with retained or account caller ID | Capability-gated. This schema/runtime drift cannot support a safe typed editor. Enabling requires a mandatory write-only PIN or stronger identity, bounded retry/timeout/digit policy, rate limiting and lockout, default-on destination classification restrictions, emergency/premium/international policy, source-number authorization, immutable audit records, and live abuse tests |
| Conference Service | Monster writes the existing `conference` module without an `id`. `cf_conference` omits `Conference-ID`; the installed conference discovery worker then prompts up to three times for a 1–16 digit account conference number and applies the selected conference's existing member/moderator PIN rules | Guided as a distinct resource-free variant. Public writes carry only `service_mode: true` and `skip_module`; Laravel strips the discriminator and the raw node never contains a conference resource ID. Configured Conference remains UUID-backed. Focused preservation/collision tests and a disposable live Switch configuration lifecycle passed; no media-leg prompt call was originated during that run |
| Check Voicemail | `callflows.voicemail` supports `action: check`, optional mailbox `id`, caller-ID matching, single-mailbox login, timeouts, and message limits. Installed `cf_voicemail` runtime inspection confirmed that resource-free check mode discovers an account mailbox by feature-code capture or prompts for its number, bounds retries, and enforces the selected mailbox PIN policy; an authenticated owner may intentionally bypass the PIN only when that mailbox has `require_pin = false` | Guided only as resource-free `{ action: check, skip_module }`. GridPBX rejects mailbox IDs, compose mode, caller-ID matching, and single-mailbox auto-login at its public write boundary. Those private fields and unknown data are redacted and preserved. Focused tests and a disposable Switch lifecycle passed; Kazoo materialized both private login flags as `false`, no raw `id` existed, and the injected unknown marker survived the edit |
| Global Carrier | Monster adds an empty `offnet` node with no editor. The installed `cf_offnet` runtime forces `use_local_resources = false` and delegates to `cf_resources`, which publishes a paid external bridge request containing call/account identity, caller IDs, channel/application variables, SIP headers, a final `to_did`, normalization controls, resource flags/type, and timeout/media settings. Installed route-entry code classifies the original request before the callflow runs, while the later module may replace the final DID. The global emergency path defaults `deny_invalid_emergency_cid` to false and can continue with an anonymous caller ID | Capability-gated. Even empty data can originate through system-wide carriers from an arbitrary inbound or internal tree position, enabling hairpin loops and unbounded spend. GridPBX must require an authenticated outbound-only context, classify and authorize the final normalized destination, deny unsafe emergency/premium/international classes by default, prevent loops, enforce spend/rate/concurrency controls, emit immutable audit events, and keep DID overrides, SIP headers, resource types, and carrier flags server-owned |
| Account Carrier | Monster adds `resources` and optionally writes an operator-entered raw `hunt_account_id`. The installed runtime defaults local-resource hunting to the current account and accepts the same DID, normalization, SIP, caller-ID, and resource-selection controls as `offnet`. Installed StepSwitch validates a requested hunt account against the caller's account hierarchy, but any present hunt account takes the local-resource emergency branch, which explicitly skips emergency caller-ID validation | Capability-gated. A hierarchy check does not make raw Switch account IDs or arbitrary local-carrier selection safe for the public API. Enabling requires the same final-destination and toll-fraud controls as Global Carrier, account-scoped public UUID resolution with explicit reseller entitlement, projected carrier-pool capability, server-owned routing metadata, and an emergency policy that does not rely on the local-resource validation bypass |
| Webhook | `callflows.webhook` accepts an operator URI using HTTP or HTTPS, GET or form-encoded POST, arbitrary `custom_data`, integer retries, and `skip_module`; unlike Monster's legacy form, the installed callflow schema does not support PUT or a JSON format control. `cf_webhook` asynchronously continues the callflow, then publishes a broad normalized call snapshot containing raw account, authorizing, owner, call, caller/callee, SIP-header, application-variable, and Switch-host data. Delivery adds raw `X-Account-ID` and `X-Hook-ID` headers but no signature or authentication secret. The active URL blacklist contains only literal `localhost`, `127.0.0.1/32`, and `0.0.0.0/32`; hostnames are not resolved during validation. Installed HTTP defaults follow up to four redirects, have an infinite total request timeout, and use TLS `verify_none`. Runtime retries are clamped to 1–5 and occur only for client/network errors after a fixed two-second delay; only HTTP 200 is success, while non-200 responses are not retried. Failed attempts persist the URI, request headers/body, response headers/body, and errors in the account MODB | Capability-gated. No live callback was sent because the installed path is not safe for an operator-controlled destination. Enabling requires server-owned HTTPS origin allowlists, DNS resolution and private/link-local/metadata rejection on every connection and redirect, verified certificates and hostnames, a bounded total timeout and response size, signed minimal payloads with replay protection and secret rotation, public-safe identifiers, redacted attempt records with retention limits, bounded idempotent retry/backoff policy, account rate/circuit controls, immutable audit events, and an emergency kill switch |
| Dynamic CID | Monster creates an empty `dynamic_cid` node with no editor. The installed runtime treats an omitted action as `manual`, prompts the caller for a replacement caller-ID number, and by default accepts any ten digits matching `\d+`; it does not prove that the number belongs to the account. `static` accepts arbitrary name/number data. `list` and `lists` consume raw list identifiers and can also select a new destination from the feature-code capture. Destination call restrictions default on only for that list-routing path, are bypassed if endpoint lookup fails, and can be explicitly disabled; `permit_custom_callflow` can allow a matched custom route. The downstream caller-ID layer validates dynamic external numbers only when the system-wide `callflow.ensure_valid_caller_id` setting is true. It is unset in this deployment, so the installed default is false | Capability-gated. GridPBX must not expose manual arbitrary-number entry, raw list IDs, static arbitrary caller ID, `enforce_call_restriction = false`, or `permit_custom_callflow`. A safe variant requires an account-scoped public Phone Number UUID or a dedicated projected caller-ID profile, server-side ownership/E911 verification and raw mapping, immutable anti-spoofing audit, authenticated feature-code context, final-destination restrictions that fail closed when endpoint resolution fails, rate limits, and live carrier-level caller-ID verification before enablement |

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
form-only array. The live branching walkthrough creates a
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

The remaining work adds
additional non-reference module forms and explicitly designed module-specific
branch editors.
Direct temporal and Rule Set branches already use typed, server-mapped
operations. Unsupported nodes remain locked and lossless.
GridPBX uses its Tailwind visual language rather than copying Monster's styling.

Focused Switch package, Laravel feature, Vue schema/store, and isolated
authenticated headless Playwright checks cover these boundaries. The live case
uses a uniquely named disposable non-inventory-number route and verifies its
cleanup.

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

## Delivery order

After Device, audit mutation-capable entities in dependency order:

1. User/Extension, Voicemail, Directory, and LineKey
2. Callflow, Group, Menu, Queue/Agent, Conference, and Temporal routing
3. Blacklist, Fax box, and Phone number management
4. Media and account configuration

CDRs, recordings, services, and system status use a read/display audit rather
than artificial create/edit operations.
