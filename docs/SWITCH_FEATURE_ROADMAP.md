# Switch Feature Implementation Roadmap

Status: Active delivery roadmap
Last updated: 2026-08-30

## 1. Purpose

This document defines the Switch-backed capabilities planned for GridPBX. It is
the product feature catalog that complements the architecture and delivery
rules in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

The field-level parity contract for every included entity is maintained in
[SWITCH_SCHEMA_PARITY.md](SWITCH_SCHEMA_PARITY.md). A feature cannot move to
`Complete` while public schema fields remain silently unclassified.

The catalog was prepared from the locally available Switch Crossbar
documentation, the Monster UI applications, and the legacy GridPBX routes and
screens. Those projects are reference material only and are intentionally not
included in this repository. Their source code and proprietary assets must not
be copied into the new application.

This roadmap is not a promise to expose every Crossbar endpoint. Features are
selected according to client workflows, the capabilities enabled in the
target Switch deployment, security requirements, and delivery priority.

## 2. Scope and status vocabulary

Delivery priority:

| Priority | Meaning |
| --- | --- |
| P0 | Platform foundation required by every Switch feature |
| P1 | First usable PBX-management release |
| P2 | Core operational parity for common customer workflows |
| P3 | Advanced operations, reseller, and specialist workflows |
| Deferred | Requires a separate decision, integration, or threat model |

Implementation status:

| Status | Meaning |
| --- | --- |
| Foundation | Supporting code exists, but the end-to-end feature is incomplete |
| Planned | Accepted into the roadmap but not implemented |
| Conditional | Implement only when the target Switch deployment supports it and the client confirms the need |
| Deferred | Explicitly outside the current delivery commitment |
| Complete | Implemented, synchronized, tested, documented, and accepted |

No Switch-backed product feature is currently marked complete pending client
acceptance. People and Extensions, Devices, Voicemail, and Phone Numbers are at
Foundation status with the exact implemented scope documented in their
sections below.

## 3. Feature architecture rules

Every Switch feature follows these rules:

1. Vue communicates only with Laravel; it never calls Crossbar directly.
2. Laravel validates account access and permissions before every operation.
3. Switch remains authoritative for PBX configuration.
4. MySQL stores normalized, searchable projections of selected Switch data.
5. PBX writes go to Switch first and then update or invalidate the projection.
6. Imports and reconciliation jobs are idempotent and account-scoped.
7. The UI displays projection freshness and synchronization failures.
8. Credentials, authentication hashes, SIP passwords, API keys, and Switch
   tokens are centrally redacted from projection payloads.
9. Every mutation creates an application audit record.
10. Feature availability is capability-driven; unsupported modules are hidden
    rather than presented as broken screens.
11. A projected entity stores the latest full detail response `data` object in
    `switch_json` after sensitive-field redaction; normalized columns remain
    the query and relationship contract.

### 3.1 Switch coverage register

This register is the completeness checklist for the Switch boundary. Every
resource named below is included in the target scope, even when its delivery is
conditional or scheduled for a later phase. A resource is not considered
implemented merely because a legacy class or an empty folder exists; it moves
to `Complete` only through the delivery checklist in section 11.

| Requested area | GridPBX treatment | Persistence and delivery rule | Status |
| --- | --- | --- | --- |
| Account | Account mapping, hierarchy, settings, and capability discovery | Safe detail/counts, redacted `switch_json`, typed identity/calling settings, restrictions, recording defaults, guided dial plans/formatters, public-UUID caller-ID/preflow/metaflow resources, supported recursive metaflow editing with locked-tree preservation, E911 enforcement, and confirmed audited enable/disable delivered; hierarchy and remaining advanced settings remain | Foundation |
| Blacklist | Blacklist CRUD, number entries, and account-level inbound activation | Normalized searchable entries, active state, redacted `data`, schema-aligned form validation, and external-flag preservation delivered | Foundation |
| CallDetailRecord | Call history, filters, interaction detail, and Recording links | Project only approved fields and allowlisted `switch_json`; validated filters and reciprocal public-UUID Recording links delivered; retention and partitioning must be agreed before production scheduling | Foundation |
| Callflow | Routing inventory, dependency resolution, guided editing, and safe unknown-branch preservation | Project routing summaries and a redacted detail snapshot | Foundation |
| Conference | Conference configuration, role access numbers, participant behavior, last-observed runtime status, and guided routing | Normalize role-number relationships, retain the full redacted `data` snapshot, and store only PIN-configured flags | Foundation |
| Device | Device CRUD, user assignment, registrations, and provisioning metadata | Project safe device and registration state; never persist or return SIP secrets | Foundation |
| Directory | Directory CRUD and user membership | Project directory metadata and relationships | Foundation |
| Fax | Fax-box configuration, inbound/outbound message inventory, authorized document access, and guided routing | Schema-aligned validated form, safe owner/number/timezone choices, hidden notification/flag preservation, normalized metadata, redacted `data`, and protected document streaming delivered | Foundation |
| Group | Group and ring-group configuration, membership, endpoints, and strategy | Project group/member relationships and safe configuration | Foundation |
| LineKey | Device line-key configuration and provisioning preview | Device-owned normalized projection plus redacted device `switch_json`; safe preview is always available and upstream apply is capability-gated | Foundation |
| Media | Media metadata, prompts, music-on-hold, upload, and authorized streaming | Schema-aligned upload metadata form, protected audio replacement/streaming, Headless UI music-on-hold choice, hidden prompt/source/TTS preservation, and redacted detail delivered; generated TTS/recording remains capability-gated | Foundation |
| Menu | IVR menu CRUD, prompt, timeout, retry, and key destinations | CRUD, prompt/media relationships, sync, dependency-safe delete, guided routing, and safe root-level DTMF/timeout branches delivered; deeper branch trees remain part of the visual editor | Foundation |
| PhoneNumber | Number inventory, features, assignment, and approved carrier workflows | Version-aware feature availability, allowlisted CNAM/E911/porting detail, explicit operation capability gates, and redacted snapshots delivered; carrier mutations remain policy-gated | Foundation |
| Queue | Queue CRUD, agents, membership, state, and statistics | Capability-driven projection; configuration requires the target ACD/queue application | Foundation |
| Recording | Recording search, metadata, and authorized playback/download | Bounded metadata-only projection, validated advanced filters, redacted source snapshot, reciprocal CDR relationship resolution, audited range streaming, and right-side detail UI delivered; deletion remains disabled pending retention policy | Foundation |
| Services | Account service-plan, limits, quantities, standing, billing-cycle, and billing-impact summaries exposed by Switch | Administrator-only normalized read projection with redacted `switch_json`; all billing mutations remain disabled | Foundation |
| SystemStatus | Connectivity, capability, and relevant telephony health summaries | Account-scoped live probes with a ten-second cache now expose only presence-diagnostic availability, parked-call summary availability, and an aggregate parked-call count; raw infrastructure and call payloads are discarded and are not durably projected | Foundation |
| TemporalRule | Business-hours, holiday, time-of-day CRUD, effective status, and operational override | Validated normalized schedules, redacted source snapshots, CRUD, sync, dependency-safe deletion, timezone-aware status, and audited force-active/force-inactive/reset commands delivered | Foundation |
| TemporalRuleSet | Rule-set CRUD, ordering, effective status, enable/disable, and reset workflows | Ordered membership, CRUD, sync, guided `temporal_route` routing, aggregate status, and compensating member-rule command fan-out delivered | Foundation |
| User | User CRUD, identity, caller ID, forwarding, recording, restrictions, feature state, resource assignments, and supported recursive metaflows | Managed edits now include public-UUID caller ID/MOH/pronounced-name Media, current endpoint media/ringtones, safe dial plans/formatters, bounded profiles, E911 enforcement, and read-only preserved policy state | Foundation |
| Voicemail | Mailbox CRUD, assignments, greetings, messages, and permitted lifecycle actions | Project mailbox/message metadata; stream audio and keep PINs write-only | Foundation |

Internal contracts, repositories/gateways, and PHP or TypeScript enums may be
introduced when useful for clean boundaries and type safety, but Contracts,
Repositories, and Enums are not Switch entities or roadmap deliverables.

For each actual Switch resource, the implementation slice consists of an
entity-organized DTO/resource client in `grid-api-switch`, a simple Laravel DDD
domain with services and any required MySQL projection, and a Vue domain when
the capability is user-facing. Folders and abstractions are created when their
slice begins; the project does not add empty placeholder classes merely to
mirror the legacy tree.

### 3.2 Confirmed advanced scope

The client has confirmed that the following advanced capabilities belong in
the target product scope. Confirmation means they must be designed and
delivered in an appropriate phase; it does not enable high-risk mutations
before their integration, authorization, compliance, and audit conditions are
satisfied.

| Confirmed capability | Owning domains | Delivery gate | Status |
| --- | --- | --- | --- |
| Number purchasing, porting, releasing, CNAM, and E911 changes | Phone numbers, carriers, auditing | Carrier APIs and charges, porting workflow, emergency-service compliance, privileged confirmation, and reconciliation | Planned |
| Advanced visual callflow editing | Call routing and referenced PBX domains | Full main-page drag-and-drop graph and action palette, selected-node-only right panel, version-safe writes, public-ID reference resolution, schema validation, dependency checks, and lossless preservation of unknown branches | Planned |
| Queues and agents | Queues, users, devices, and call routing | Foundation delivered for configuration, roster, live status commands, and guided routing; statistics remain conditional | Foundation |
| SMS/MMS | Messaging, phone numbers, users, and auditing | Enabled carrier capability, consent, retention, attachment storage, delivery events, and abuse controls | Conditional |
| Recordings | Recordings, call history, storage, and auditing | Retention policy, legal authorization, access audit, encryption, and streaming/storage decision | Planned |
| Provisioning | Devices, line keys, templates, and vendor integrations | Supported vendors/models, template ownership, credential protection, and preview/rollback workflow | Conditional |
| Billing and reseller management | Organizations, accounts, services, billing, and authorization | Authoritative billing source, tenant hierarchy, financial permissions, immutable audit, and payment compliance where applicable | Planned |
| Trunks, carriers, resources, and connectivity | Connectivity, routing, accounts, and system status | Administrator-only threat model, deployment-specific schemas, secret handling, validation, and rollback | Planned |
| Webhooks and advanced administration | Webhooks, notifications, security controls, accounts, and auditing | Event allow-list, signing secrets, delivery observability, SSRF protection, least privilege, and separate security review | Conditional |

Every user-facing create or edit workflow in this scope uses the standard
right-side slide-over panel. Large visual editors may use a dedicated workspace
for the canvas, while node settings and CRUD forms still open from the right.
The shared interaction layer uses `@headlessui/vue` for dialogs, listboxes,
menus, switches, tabs, and disclosures, with Tailwind retaining full ownership
of the visual design. Native multi-select checkboxes remain semantic inputs
because Headless UI for Vue does not provide a checkbox primitive.

## 4. Platform and account foundation

| Capability | Switch boundary | MySQL/application responsibility | Priority | Status |
| --- | --- | --- | --- | --- |
| Server-side authentication | API-key authentication and `X-Auth-Token` | Secure configuration, Redis token cache, refresh lock | P0 | Foundation |
| Switch connectivity health | Crossbar root/auth request | Health state, failure reason, last successful check | P0 | Foundation |
| Account mapping | Accounts | Organization-to-Switch-account mapping and authorization | P0 | Foundation |
| Account hierarchy | Accounts and descendants | Searchable account projection and allowed account tree | P1 | Planned |
| Capability discovery | Authentication response plus audited account/resource capabilities | The Switch token provider retains only typed, allowlisted feature booleans for application consumers. Voicemail transcription availability/default is delivered; broader module and account capability discovery remains | P1 | Foundation |
| Initial import | Account-scoped resource APIs | Sync runs, checkpoints, counts, errors, and timestamps | P1 | Foundation (extensions) |
| Incremental synchronization | Events/webhooks where supported; polling otherwise | Queue jobs, idempotency, locks, tombstones | P1 | Planned |
| Full reconciliation | Account-scoped list/detail APIs | Repair projections and detect deletions | P1 | Foundation (extensions) |
| Global search | Multiple account resources | Authorized MySQL search index/projections | P2 | Planned |

Acceptance baseline:

- A user can see only mapped accounts.
- A failed Switch connection does not expose credentials or internal payloads.
- Imports resume from checkpoints and can be replayed without duplicates.
- Every projected domain reports `healthy`, `syncing`, `stale`, or `error`.

## 5. P1 core PBX features

### 5.1 Dashboard

User-facing capabilities:

- Account summary and synchronization health
- Extension, device, number, and voicemail counts
- Registered/unregistered device summary
- Recent call activity after the CDR feature is available
- Clear stale-data and upstream-outage indicators

Primary sources include accounts, users, devices, registrations, phone
numbers, voicemail boxes, and later CDRs. Dashboard values come from MySQL
projections and include their freshness time; the dashboard does not fan out
into many live Switch calls on every page load.

### 5.2 People and extensions

An extension is a guided GridPBX workflow composed from several Switch
resources rather than a single Crossbar document.

The authoritative relationship, creation-order, compensation, update, and
dependency-aware deletion design is documented in
[SWITCH_ENTITY_RELATIONSHIPS.md](SWITCH_ENTITY_RELATIONSHIPS.md). All composite
workflows in this roadmap must follow those rules.

| Capability | Switch resources | Projected data |
| --- | --- | --- |
| List, search, and filter extensions | Users, callflows, devices, voicemail boxes | Identity, extension numbers, assigned resources, enabled state |
| View extension details | User plus related resources | Normalized relationship summary |
| Create extension | User, device, voicemail box, basic callflow as selected | Resulting IDs and relationship state |
| Edit profile and caller ID | Users and account caller-ID settings | Safe display fields and caller-ID summary |
| Assign devices | Users and devices | User-device relationships |
| Configure voicemail | Users and voicemail boxes | Mailbox assignment and non-secret settings |
| Call forwarding, DND, and hotdesk | User/callflow features when supported | Effective feature state |
| Delete extension | Coordinated dependency-aware deletion | Tombstones and audit outcome |
| Bulk changes | Bulk or bounded individual Switch operations | Per-item result and reconciliation status |

The create/update workflow must report partial failures and support safe
recovery when one Switch resource succeeds and a later resource fails.

Implementation status: Foundation. Account-scoped extension list/detail,
managed create/update, and Vue right-side create/edit panels are implemented.
Create provisions a typed Switch user, optional owned voicemail box and device,
and a managed user callflow with an optional voicemail fallback. Update changes
the owned user, mailbox, and callflow in dependency order while preserving
independent devices and callflow branches. Successful snapshots are projected
in one local transaction, workflow ownership is explicit, secrets are redacted
from `switch_json`, and aggregate outcomes are audited. Create compensates new
remote resources in reverse order; update returns a stable repair-required
error after partial upstream completion. A right-side deletion review
reports managed/shared resources, phone-number and voicemail blockers, and
referencing or unresolved callflows without exposing internal or upstream IDs.
When the preview is clear, the operator must type the exact extension number.
Laravel rechecks every blocker, then deletes only workflow-owned callflows,
devices, voicemail boxes, and the user in reverse dependency order. Each
upstream step is persisted under a public operation UUID, allowing an
interrupted deletion to resume without repeating completed steps. Success
soft-deletes the MySQL projections and writes an audit outcome. Create, update,
and delete now share persisted lifecycle records. A manager-only right-side
recovery queue retries only failed create-compensation steps, reconciles a
partial update from Switch, or resumes a partial deletion after exact-number
confirmation. Recovery responses expose public operation and extension UUIDs
plus safe step names; upstream resource IDs, raw context, PINs, and credentials
remain server-side. Fully compensated creates are marked rolled back and do not
enter the queue. User hotdesk profiles are now edited in both Extension
slide-overs with schema-aligned ID, enabled state, PIN requirement, and
multi-device login controls. PINs are write-only, redacted from MySQL and API
responses, preserved privately when unchanged, and removable only after PIN
protection is disabled. A disposable live Switch lifecycle verifies profile
create/edit/preserve/clear and Device sign-in/sign-out behavior.
The same Extension slide-overs now own the optional Switch portal-login
workflow. A password is required for login creation or username changes,
confirmation is validated in Vue and Laravel, unchanged usernames never resend
the write-only password, and deleting credentials requires an explicit removal
state. `require_password_update` is exposed only with a username, and plaintext
passwords remain absent from responses, lifecycle context, and MySQL.

### 5.3 Devices

Planned capabilities:

- List, search, view, create, update, and delete devices
- Assign or unassign a device from an extension/user
- Registration status and last-known registration summary
- Device type, make/model, MAC address, and enabled state
- SIP username/password generation without returning stored secrets later
- Bulk settings where the target deployment supports them
- Line-key configuration and provisioning preview
- Provisioning brand/model selection when a provisioner is configured

Primary Switch boundaries: devices, registrations, users, provisioner
templates, and any deployment-specific line-key storage. MySQL stores device
metadata and status summaries but never SIP passwords.

Implementation status: Foundation. Account-scoped list/detail/CRUD,
registration projection, role authorization, audit logging, credential
redaction, and Vue management screens are implemented. The LineKey foundation
also projects `provision.combo_keys` and `provision.feature_keys`, exposes a
credential-free payload preview, and provides a right-side editor. Applying a
full key-map replacement uses a live-verified read-modify-POST boundary because
Crossbar PATCH recursively merges old key maps. Apply remains disabled unless
`SWITCH_LINE_KEY_MUTATIONS_ENABLED=true` and the device has a confirmed endpoint
brand, model, and MAC address. A typed Monster-compatible `/phones` catalog is
used when `SWITCH_PROVISIONER_URL` is configured; otherwise the UI states that
discovery is unavailable and permits manual hardware values. Generated provisioning
documents, bulk settings, and zero-touch provisioning remain conditional.
The first schema-parity form slice now adds all eight upstream device types,
Basic/Advanced conditional controls, nested SIP/forwarding/media/caller-ID and
common endpoint options, typed Switch DTOs, and safe configuration hydration.
The remaining field groups and exact acceptance checklist are tracked in
[SWITCH_SCHEMA_PARITY.md](SWITCH_SCHEMA_PARITY.md#6-device-implementation-acceptance-criteria).

### 5.4 Voicemail

Planned capabilities:

- Mailbox list, search, create, update, and delete
- Assign mailbox to an extension/user
- Configure mailbox number, owner, timezone, notifications, and transcription
  options supported by the deployment
- Show message counts and list message metadata
- Download or stream a message through an authorized Laravel endpoint
- Bulk mailbox settings

Primary Switch boundaries: voicemail boxes, voicemail messages, users, media,
and notifications. Message audio is streamed on demand and is not duplicated
into MySQL by default.

Implementation status: Foundation. Account-scoped mailbox list/detail/CRUD,
extension assignment, timezone, notification emails, transcription settings,
write-only PIN handling, role authorization, audit logging, redacted
`switch_json`, message counts and metadata projection, authorized range-aware
audio streaming, and Vue management panels are implemented. Audio stays in
Switch and is streamed on demand; it is not stored in MySQL. Manager-only
single and bulk message lifecycle actions now support new, saved, recoverable
deleted, and restored states with partial-failure reporting and audit logs.
Permanent upstream deletion is intentionally not exposed. The supported
unavailable greeting can be discovered, uploaded/replaced through a right-side
panel, streamed through Laravel, and safely detached. Greeting metadata and
redacted `switch_json` are projected while audio remains in Switch. Additional
prompt types and bulk mailbox settings remain planned. The installed Kazoo
authentication response now supplies typed voicemail transcription availability
and default booleans without exposing its auth token or raw capability payload.
Unavailable transcription cannot be newly enabled through the API or UI, while
an existing enabled value can be preserved or turned off. A 2026-08-31 live
read and isolated non-mutating browser check confirmed this cluster reports
`available = false` and `default = false`.

### 5.5 Phone numbers

Planned capabilities:

- Inventory list with search, filters, assignment, and state
- View number details and enabled features
- Assign or unassign a number from a callflow
- Update caller-name/E911-related metadata only when the carrier integration
  and compliance requirements are confirmed
- Acquire, port, release, or reserve numbers only through explicitly approved
  carrier workflows
- Bulk assignment and safe release confirmation

Primary Switch boundaries: phone numbers, callflows, number features, and
optionally port requests. MySQL stores normalized inventory and assignment
projections.

Implementation status: Foundation. The typed Switch boundary hydrates the
keyed account collection through per-number detail requests. Laravel projects
normalized inventory, features, caller-name and E911 status, current callflow
assignment, freshness metadata, and a redacted `switch_json` snapshot into
MySQL. Account-authorized list/detail and explicit queued synchronization APIs
use public UUIDs, while Vue provides search/state/assignment filters and a
right-side detail panel. Acquisition, release, porting, caller-name changes,
E911 changes, and assignment mutations are intentionally unavailable until
deployment capabilities, carrier charges, permissions, and compliance rules
are approved.

### 5.6 Basic call routing

The first routing release provides guided workflows rather than exposing the
complete Switch callflow document editor.

- Route a phone number to an extension
- Ring a group of users/devices
- Route to voicemail
- Play media
- Route to an IVR menu
- Apply business-hours/time-of-day routing
- Route to another callflow
- Route off-net when account/carrier configuration permits it
- Preview the route and identify missing referenced resources

Primary Switch boundaries: callflows, menus, temporal rules and rule sets,
users, devices, groups, voicemail boxes, media, and phone numbers.

Implementation status: Foundation. Callflows are hydrated from their detail
documents and projected with entry numbers, patterns, flags, feature-code
metadata, ordered modules, root module, node count, maximum depth, and a safe
structural tree containing modules, branches, resolution state, and public
GridPBX target UUIDs. Laravel owns the account-authorized list/detail/editor
API and never returns raw node data, upstream identifiers, or `switch_json`.
Vue provides search, route-type and module filters plus a recursive tree and a
guided right-side editor. The guided form uses Zod, Headless UI selectors,
inline field errors, shared invalid-control styling, and account-scoped public
UUID choices. Unsupported root modules and supported modules whose target is
not resolved in the current projection are locked in both the editor response
and mutation service, preventing a stale or unknown root from being silently
replaced. The first safe mutation can rename a non-feature-code
route and replace its root destination with an account-scoped extension,
device, voicemail box, callflow, or projected media item. Laravel fetches the
latest Switch detail before writing, resolves the public UUID server-side,
preserves every child and unknown branch, retains module-specific data when the
module itself is unchanged, refreshes the projection, and audits the result.
The editor can also create, replace, or clear the root wildcard (`_`) fallback
through account-scoped public UUIDs. Same-module fallback data and all sibling
branches are preserved; nested, unsupported, or unresolved fallback subtrees
are locked in both the editor and mutation service.
For Menu roots, explicit branch operations cover `timeout`, digits `0–9`, and
`*`. Numeric keys are normalized as JSON object properties across Switch
writes, MySQL snapshots, and API responses. Existing legacy `#`, unknown
vendor keys, and unsafe nested or unresolved key branches remain read-only and
losslessly preserved.
For `temporal_route` Rule Set roots, the editor follows Kazoo's documented
runtime contract: ordered member rules are shown using public GridPBX UUIDs,
any matching member follows the literal `children.rule_set` branch, and no
match follows `children._`. The match destination can be created, replaced, or
cleared without exposing Rule, Rule Set, or destination Switch identifiers.
Additional legacy temporal branches remain counted and preserved read-only.
The same guided form assigns or removes projected phone-number
entry points using public UUIDs. It preserves extension numbers and patterns,
blocks numbers owned by another route, updates Switch first, and reconciles the
phone-number projections in the same database transaction. The existing PBX
reconciliation refreshes callflows and resolves
references together with their extension, voicemail, device, callflow, and
projected media dependencies. A guided create panel builds a new single-root
phone-number route in Switch before projecting it. Deletion is available only
for ordinary routes with no extension, phone-number, feature-code, resolved
callflow, or unresolved callflow dependencies; the API rechecks these guards
before deleting from Switch. Focused package, Laravel, Vue, and isolated
authenticated headless Playwright checks cover the guarded root editor,
wildcard fallback, Menu-key controls, and Rule Set match routing,
module-data preservation, numeric-key JSON shape, inline validation, and
viewport-bounded selectors. The detail panel now renders the full projected
recursive structure as a scroll-bounded node-and-connector map with semantic
branch badges. Keyboard-accessible node selection drives a safe inspector
showing the public branch path, module, resolved destination label, reference
state, child count, and editing status. Unknown child-map keys are replaced in
the public response by numbered preserved-branch labels so upstream Switch
resource IDs cannot leak; the original keys remain internal for lossless
writes. A searchable palette uses the installed Kazoo section names, membership,
and action order; there is no GridPBX-only “Schema extensions” section. The
expanded categories retain the exact installed Kazoo membership and order. Guided current-schema
actions that are absent from that installed palette remain explicitly
searchable without changing the native category lists. Guided, planned, and
capability-gated actions remain visually distinct without presenting unsupported
mutations as available. Guided palette drag-and-drop, recursive subtree moves,
insert-before, and safe subtree swaps are now implemented. Bounded inline forms
cover Sleep, TTS, DTMF collection,
send and flush, Dead Air, Language, recording actions, Missed Call Alert, Set
Caller ID, Prepend Caller ID, Set Alert Info, regex-mode Check Caller ID, and
Caller-ID List Match, terminal SIP Response and Hangup, and Kazoo-supported Call
Priority.
Check Caller ID validates a portable regex, maps optional identity overrides
through public Extension UUIDs, and uses typed `match`/`nomatch` branches;
dynamic absolute-number branches are preserved read-only.
Caller-ID List Match resolves an account-scoped public List UUID to its private
Switch ID. List metadata and list entries are projected independently with
redacted `switch_json`; they remain a separate resource from Blacklists.
Set Variable is deliberately limited to `call_priority`, the only variable
mapped by the checked-in Kazoo runtime. Values are bounded to the queue-supported
range `0`–`255`; arbitrary channel-variable names remain redacted and read-only.
Branch Variable follows the same contract boundary: the guided editor accepts
only `custom_channel_vars.call_priority`, exposes the fallback plus priority
`0`–`255` branches, and preserves unknown data and children losslessly. Its
API, Switch DTO, Zod form, condition rendering, and disposable live
create/edit/reopen/Priority-42/delete lifecycle are verified end to end.
Branch Bnumber follows Kazoo's documented branching-versus-hunting contract.
Branch mode accepts bounded exact captured dial strings and exposes them as
condition branches; hunt mode accepts optional safe allow/deny patterns and
uses only the normal continuation when lookup fails. Enabling hunt is blocked
until exact child branches are removed. The API, Switch DTO, Zod form, public
tree labels, create/edit/filter-clear/exact-1000 branch lifecycle, deletion,
and independent Switch/MySQL cleanup are verified end to end.
Set CAV follows the checked-in `set_variables` schema and Monster row workflow.
GridPBX exposes repeatable validated key/value rows while its API boundary writes
the exact `custom_application_vars` object plus `export` and `skip_module`.
Duplicate or unsafe keys and control characters are rejected, and unsupported
existing maps remain redacted and losslessly preserved. Focused package, API,
Zod, component, and disposable live Switch create/edit/reopen/delete coverage
protects the complete round trip.
Manual Presence follows the checked-in schema and runtime mappings. GridPBX
accepts a local presence ID or one realm-qualified ID, exposes only `idle`,
`ringing`, and `busy`, and supports `skip_module`. New forms explicitly use
Monster's `busy` default; omitted legacy status is interpreted as the schema
default `idle`. Focused package, API, Zod, component, and disposable live Switch
create/edit/reopen/delete coverage protects the complete round trip.
Group Pickup follows the checked-in schema and Monster's single endpoint
selector. GridPBX accepts exactly one account-scoped public Device, Extension,
or Group UUID and maps it server-side to Kazoo's mutually exclusive `device_id`,
`user_id`, or `group_id`. Private `approved_*` restrictions and unknown node
properties never cross the public boundary and remain losslessly preserved;
ambiguous or unresolved existing targets are read-only. Focused package, API,
Zod, component, and disposable live Switch Extension-to-Device create/edit/
reopen/delete coverage protects the complete round trip.
Page Group now has a verified guided Device-only foundation. GridPBX accepts
one to twenty distinct account-scoped public Device UUIDs, resolves them to raw
Kazoo `device` endpoint IDs only at the server boundary, and exposes the schema
audio choices `one-way` and `two-way` plus `skip_module`. Kazoo's materialized
top-level and endpoint timing values remain private, bounded, and preserved;
unknown endpoint fields are also preserved by the Switch DTO and covered by a
focused regression test. Existing user/group endpoint expansion,
`barge_calls = true`, unsafe timings, or unresolved endpoints remain read-only because their
runtime fan-out and call-interruption effects are not yet safely modeled. A
2026-08-30 disposable live run verified create/edit/reopen/delete, public/raw
Device mapping, `one-way`-to-`two-way`, `skip_module`, preservation of Kazoo's
materialized timing defaults, MySQL soft deletion, and no remaining active
Switch callflow. No media-leg page was originated, so this is not yet the
globally complete Page Group feature.
Ring Group now has a verified guided Device-only foundation. GridPBX accepts
one to twenty ordered account-scoped public Device UUIDs and resolves raw
Kazoo endpoint IDs only at the server boundary. The public form exposes
`simultaneous`, `single`, and `weighted_random` strategies, endpoint delay and
timeout, one to three attempts, `ignore_forward`, `fail_on_single_reject`, and
`skip_module`; weighted-random requires an explicit `1`–`100` weight for every
Device. `ignore_forward` defaults to `true` and makes endpoint forwarding fatal,
while `fail_on_single_reject` defaults to `false` and stops the remaining legs
after one rejection when enabled. Laravel computes Kazoo's top-level attempt
`timeout` and enforces a 120-second cap. Sequential strategies cannot use delay.
The installed Monster form has no weighted-random or bridge-flag controls, so
the installed schema and compiled runtime are authoritative: each weighted
attempt orders all endpoints without replacement and each retry reshuffles.
Existing user/group expansion, unresolved endpoints, unsafe timings, and
malformed legacy flag values remain private and read-only unless the
configuration fits the guided subset. Ringback is guided only through an
account-scoped public UUID for synchronized streamable `audio/*` Media; raw
Media IDs are mapped only at the Switch boundary. Arbitrary URLs, special
streams, system paths, unresolved Media, and non-audio Media are rejected or
lock existing nodes. Optional internal/external phone alerts are bounded SIP
`Alert-Info` strings with CR/LF/NUL rejection. The Switch DTO merges managed
values into the current node and preserves private endpoint/node fields,
including unknown nested ringtone keys. Ring Group ringback references also
block Media deletion. A
2026-08-30 disposable live run
named `E2E Ring Group 1788090166193` verified creation below Page Group,
public-to-raw Device mapping, simultaneous delay `5`/timeout `20` with two
attempts, edit to in-order delay `0`/timeout `30` with one attempt,
`skip_module`, authoritative reopen, raw computed timeout `30`, browser
deletion, MySQL soft deletion, and no remaining active Switch callflow. Crossbar
sanitized attempted live private markers, so lossless private/unknown-field
preservation is claimed from the focused SDK regression test rather than a
direct CouchDB write. No media leg was originated, so this is not yet the
globally complete Ring Group feature.

A focused weighted-random live run used disposable route
`E2E Ring Group Weighted 20260830150119890` (public UUID
`1a1b4319-5b70-4290-9c90-511c20324f41`). It created simultaneous routing with
delay `5`, timeout `20`, and two attempts, edited to weighted-random with zero
delay, timeout `30`, weight `75`, one attempt, and `skip_module`, then reopened
the authoritative values. The public contract contained only the account-scoped
Device UUID; an independent SDK watcher captured the expected private Device
ID, endpoint weight, and computed top-level timeout. The single isolated
headless test passed in 3.6 seconds. Browser deletion, independent
synchronization, MySQL soft deletion, and zero active Switch matches were all
confirmed. Unknown-field preservation remains focused SDK evidence.

A final bridge-flag lifecycle used disposable route
`E2E Ring Group Flags 1788104697523`. The form created the installed defaults
`ignore_forward = true` and `fail_on_single_reject = false`, then edited them to
`false` and `true` while also setting weighted-random weight `75`, endpoint
timeout `30`, and `skip_module = true`. The reopened public contract retained
the account-scoped Device UUID and omitted the raw Device ID, a private
`ringtones.external` value, and an injected unknown node marker. An independent
watcher observed the expected raw Device mapping and proved that both private
values survived the typed edit. The single isolated headless test passed in
4.4 seconds. Browser deletion and an independent synchronization confirmed a
soft-deleted MySQL projection and zero active Switch matches. No media-leg call
was originated.

The 2026-08-31 ringback/phone-alert lifecycle used disposable route
`E2E Ring Group Media 1788127297` with unique number `88127297` and a
disposable synchronized silent WAV. The isolated headless test created a
Device-only Ring Group, selected the Media by public UUID, set internal and
external phone-alert values, then edited to weighted-random, changed both
alerts, enabled `skip_module`, and reopened every authoritative value. Public
requests and responses contained only account-scoped Device and Media UUIDs.
An independent raw observer captured the expected raw Device and Media IDs,
computed timeout `30`, weight `75`, both edited ringtone values, and retention
of an injected unknown nested ringtone key plus unknown node key. The focused
browser test passed one test in 5.1 seconds. Browser deletion and an independent
reconciliation confirmed the Callflow projection was soft-deleted, zero active
Switch callflows matched, and the disposable Media projection was soft-deleted
after its Switch resource was removed. No media leg was originated.

Ring Group Toggle now has a guided Login/Logout implementation aligned with the
installed `callflows.ring_group_toggle` schema and compiled runtime. The UI/API
accept only an account-scoped public Callflow UUID whose synchronized module
summary contains `ring_group`; Laravel resolves the raw `callflow_id` only at
the Switch boundary and rejects cross-account, feature-code, and non-ring-group
targets. Public readback includes the public UUID and a safe capability flag,
never the raw target or unknown node fields. Runtime inspection confirmed that
the module opens the target only in the caller's account database and changes
`disable_until` only for `user` endpoints matching the authenticated call owner:
`0` for login and `66269664000` for logout. Focused SDK/API/resolver/Zod/component
coverage protects validation, mapping, redaction, and lossless unknown-field
merging. A disposable isolated headless run created, edited, and reopened both
actions; an independent raw watcher confirmed both mapped to the expected raw
target with `skip_module = true`. The source and target projections were
soft-deleted and no matching active Switch callflow remained. Crossbar sanitized
an attempted private marker, so live unknown-field preservation is not claimed;
the focused SDK regression supplies that evidence. No media leg was originated,
so owner membership mutation and prompts are verified from the installed runtime,
not a live call.

Hotdesking now has a verified resource-free guided implementation aligned with
the installed `callflows.hotdesk` schema, Monster workflow, and compiled
`cf_hotdesk` runtime. The public UI/API expose only `action` (`login`, `logout`,
or `toggle`) and `skip_module`; they never accept or return a design-time user,
raw Switch `id`, or `interdigit_timeout`. The runtime prompts for an account
Hotdesk ID when needed, enforces the selected user's configured PIN on login,
and changes `hotdesk.users` only on raw Device documents. Logout and toggle's
logout path do not request the PIN, so the guided form explicitly warns that
the route must be trusted. Focused SDK/API/resolver/Zod/component coverage
protects validation, public redaction, and lossless merging of server-owned and
unknown node fields. A 2026-08-30 disposable isolated headless run named
`E2E Hotdesk 1788094232589` created, edited, and reopened login, logout, and
toggle. An independent raw watcher confirmed all three actions with
`skip_module = true` and no `id` or `interdigit_timeout`; browser cleanup and a
separate reconciliation left the MySQL projection soft-deleted with zero active
Switch matches. No media leg was originated, so prompts, PIN entry, and Device
session mutation remain compiled-runtime evidence.

Do Not Disturb now has a verified resource-free guided implementation aligned
with the installed `callflows.do_not_disturb` schema, Monster workflow, and
compiled `cf_do_not_disturb` runtime. The public UI/API expose only `action`
(`activate`, `deactivate`, or `toggle`) and `skip_module`; there is deliberately
no public-to-raw target mapping, and raw `id` plus unknown node data remain
server-owned, private, and losslessly preserved. At runtime Kazoo resolves the
authenticated caller's owner, falling back to the authorizing Device, and
updates only `do_not_disturb.enabled` on that account-local User or Device.
Because the module has no PIN challenge, the form warns operators to keep it
behind a trusted feature-code route. Focused SDK/API/resolver/Zod/component
coverage protects validation, redaction, and preservation. A 2026-08-30
disposable isolated headless run named `E2E Do Not Disturb 1788096546218`
(public UUID `6d04749b-5d2f-480d-9e95-264e0b2e4fd6`, raw Switch ID
`f896fdf9fe2eef4fd81c39c29b8bd898`) created, edited, and reopened all three
actions. An independent raw watcher confirmed the exact actions with
`skip_module = true` and no `id`; browser cleanup and a separate reconciliation
left the MySQL projection soft-deleted with zero active Switch matches. No media
leg was originated, so the owner/device mutation is compiled-runtime evidence.

Call Forwarding remains capability-gated after auditing the installed
`callflows.call_forward` schema, Monster workflows, feature-code patterns, and
compiled `cf_call_forward` runtime. The schema also permits `toggle` and `menu`,
while Monster's normal palette exposes activate, deactivate, and update. Kazoo
resolves the authorizing endpoint's owner, falling back to that endpoint, but
does not prompt for a PIN. Activate and update accept an arbitrary captured or
caller-entered 3–20 digit destination; toggle can reuse a stored destination or
collect a new one. The module performs no account-number ownership check,
destination classification, emergency/premium/international restriction,
rate/lockout control, or forwarding-loop check before writing `enabled` and
`number` to the complete account-local User or Device document. Deactivate
retains the destination and also has no authentication challenge.

GridPBX therefore marks all three installed palette actions as restricted,
rejects public API and direct Switch DTO writes, locks existing forwarding nodes
and their subtrees in the guided editor, and exposes only the safe action/skip
summary needed for labeling. Raw destinations and unknown data remain private;
a focused SDK regression proves they survive an unrelated typed tree edit.
Focused API, validator, resolver, public-tree, catalog, detail-panel, and type
checks pass. An isolated headless UI-only walkthrough confirmed the three
disabled actions, visible security explanation, read-only existing node, and
zero Callflow mutation requests. No disposable Switch write or media leg was
originated because doing so would exercise the unsafe capability being gated.

The installed-palette classification sweep is complete. All 49 visible Monster
actions are explicitly classified: 40 guided actions have either an
account-scoped public destination contract or a typed inline mutation contract,
and nine variants are capability-gated across Pivot, DISA, Global Carrier,
Account Carrier, Webhook, Dynamic CID, and the three Call Forwarding actions.
No visible action remains `planned`. A focused catalog contract enforces the
counts, exact restricted IDs, and guided implementation boundary; a focused API
test rejects all seven visible restricted module families before the Switch
gateway; and an
isolated headless sweep reopened all nine categories, observed the same 40/9/0
classification, confirmed restricted buttons were disabled, and emitted zero
Callflow mutations.

The installed `acdc_agent` callflow action remains capability-gated after its
schema, compiled runtime, message validation, and available GridPBX workflow
were audited. The schema permits `login`, `logout`, `paused`, and `resume`, plus
optional `presence_id`, presence state, integer pause timeout, and
`skip_module`. Runtime derives the raw Agent ID from the authorizing endpoint's
single Hotdesk user or owner, with no PIN challenge and no queue ID or
queue-membership check at the callflow handler boundary. Login may start the
account-local raw User document, pause defaults to 600 seconds when omitted and
accepts any non-negative integer without an upper bound, and pause/resume/logout
can update live agent and presence state.

The action is absent from the installed Monster palette. GridPBX exposes its
four schema actions only as disabled search results, rejects public API and
direct Switch DTO writes, locks existing nodes and descendants, and projects
only safe action/skip metadata. Raw inferred Agent IDs, presence fields,
timeouts, and unknown data remain private; unknown Switch JSON is preserved.
The supported alternative is the existing authenticated and authorized Queue
Agent status panel, which resolves an account-scoped public Extension UUID,
verifies queue membership, bounds pause time to 86,400 seconds, and audits the
operation. Focused SDK/API/resolver/public-tree/catalog/type checks and one
isolated headless no-mutation test passed. No disposable Switch write or live
agent-state change was attempted because that unsafe capability is the reason
the callflow action is gated.

ACDC Queue now has a guided search-only Login/Logout implementation aligned
with the installed `callflows.acdc_queue` schema and compiled runtime. The
public UI/API accept only `action`, an account-scoped public Queue UUID, and
`skip_module`; Laravel resolves the Queue's raw `id` only at the Switch
boundary and rejects unsynchronized or cross-account references. Public
readback returns the Queue UUID and label but never its raw Switch ID. The
runtime answers the call, infers the Agent from the authorizing endpoint's
single Hotdesk user or owner, updates that account-local User's Queue membership,
publishes the matching login/logout event, and continues. It has no PIN
challenge, so the guided form warns that these self-service actions belong only
behind a trusted feature-code route. Unknown node fields remain private and are
losslessly preserved by typed SDK edits.

A 2026-08-30 isolated headless run used disposable route
`E2E ACDC Queue 20260830142404451` (public UUID
`61854d2b-0195-4277-bc8b-50201d929608`). It created Queue login, selected a
projected account Queue, enabled `skip_module`, reopened it, then created and
reopened Queue logout beneath it. Public responses contained only the public
Queue UUID and safe settings. An independent SDK read confirmed both raw nodes
used the expected private Queue resource ID with `skip_module = true`. Browser
cleanup deleted the Switch route; a separate reconciliation left the MySQL
projection soft-deleted and found zero active Switch callflows with the name.
No media leg was originated, so Agent inference, prompts, and live membership
mutation remain installed-runtime evidence rather than a live-call claim.

The installed Eavesdrop compatibility family remains capability-gated after
auditing `callflows.eavesdrop`, `callflows.eavesdrop_feature`, compiled runtime,
and the installed Monster registry. Direct Eavesdrop targets a raw Device or
User and accepts raw approved Device, User, or Group IDs. Eavesdrop Feature
captures a destination extension, resolves its callflow to a Device or User,
optionally applies a raw Group restriction, and delegates to direct Eavesdrop.
Both runtime paths deny access when no approval field is configured, but when
several are present only the first configured field in Device/User/Group
precedence is evaluated. Group approval expands User members to Devices, while
the feature target restriction checks direct Group endpoint keys. Successful
monitoring enables DTMF control, may redirect to the target media server, and
stops the current callflow. Neither action appears in the installed Monster
palette.

GridPBX exposes the two actions only as disabled search results, rejects public
API and direct Switch DTO writes, and locks existing nodes and descendants.
Public projection contains only `skip_module`; raw target, approval, and Group
IDs plus unknown data remain private and losslessly preserved. Focused SDK,
API, resolver, public-tree, catalog, and type checks passed, along with one
isolated headless no-mutation test. No disposable Switch node or live monitored
call was created because the missing supervisor entitlement, immutable audit,
privacy/consent policy, and bounded monitoring controls are the reasons the
capability is gated.

The default Basic Ring Group User/Group expansion audit is complete and remains
capability-gated. The installed runtime resolves Users through the caller
account's ownership view and opens Groups only in the caller account database,
so identifier scope is account-local. It then expands memberships dynamically
at call time: Users become every currently owned Device, Groups recursively
become Devices, Users, and nested Groups, and one endpoint builder is started
for every resolved Device. There is no resolved-device cap. Deduplication
removes only the same Device with the same delay and timeout, so overlapping
memberships with different timing can ring one Device more than once. Only
top-level `disable_until` is filtered before expansion; endpoint creation later
drops deleted, disabled, self, or do-not-disturb Devices, while a disabled User
does not suppress its owned Devices. The installed Group schema accepts an
unconstrained endpoint object and Crossbar does not reject recursive membership;
`cf_ring_group` recurses before recording the parent Group, so externally
created cycles have no visited-set guard. Monster offers User/Device/Group tabs
and a direct User-versus-owned-Device warning, but it does not preview nested
fan-out, Group overlap, cycles, or later membership changes. GridPBX therefore
keeps creation Device-only, rejects public and direct SDK User/Group writes,
and redacts existing expanded endpoint IDs while preserving those nodes
read-only. Focused SDK, Laravel validator/resolver, Zod, and component
regressions enforce that boundary. No disposable Switch node or media call was
created because unbounded dynamic expansion is the reason the capability is
gated.

The exact next callflow priority remains within the default Basic Ring Group
palette node: perform a controlled media-leg verification of audible ringback
selection and emitted internal/external SIP `Alert-Info` without placing an
external call. A 2026-08-31 topology audit confirmed that the current local
reference environment cannot supply that evidence: its Kazoo container runs
Crossbar on TCP 8000, but the workspace has no FreeSWITCH/media-server process,
SIP or ESL listener, or RTP path. No disposable callflow, endpoint, or call was
created during that audit. Configuration remains live-verified and installed
runtime inspection remains the only evidence for media-leg semantics. The test
must remain pending until a representative, disposable FreeSWITCH/ecallmgr
environment and two account-local SIP legs are supplied; GridPBX must not embed
that infrastructure because the implementation plan defines it as an external
deployment responsibility. `intercept` and `intercept_feature` are not
installed default-palette actions and are therefore not the next parity target.
Receive Fax follows the installed Kazoo schema and runtime shape. GridPBX accepts
an account-scoped public Extension UUID, resolves it only on the server to raw
`owner_id`, nests `fax_option` under `media`, and supports the schema values
`auto`, `true`, and `false` plus `skip_module`. Unknown nested `media` properties
remain private and losslessly preserved. Focused package, API, resolver, Zod,
component, and isolated headless coverage protects the boundary. A 2026-08-30
disposable live run verified create below Group Pickup, `auto`-to-`true` edit,
`skip_module`, authoritative reopen, raw/public owner mapping, injected unknown
media preservation, deletion, MySQL soft deletion, and no remaining active
Switch callflow.
Pivot remains capability-gated after inspection of the installed schema and
compiled runtime. It can send caller, account, call, custom-variable, SIP-header,
recording, and transcription data to operator-entered HTTP(S) endpoints; accept
response-driven Kazoo or TwiML call control and follow-up URLs; persist debug
request/response bodies; and issue an unauthenticated end-of-call CDR POST. The
runtime provides no destination allowlist, private-network/DNS-rebinding guard,
application authentication header, callback signature, or explicit TLS policy.
It must not become editable until those controls are server-owned and enforced
outside callflow JSON.
DISA also remains capability-gated. Its installed public schema exposes only
`skip_module`, while the runtime consumes undeclared legacy dialing controls,
explicitly permits access when the PIN is empty, and defaults call-restriction
enforcement off. Mandatory authentication, lockout/rate limiting, default-on
destination restrictions, abuse auditing, and live toll-fraud tests are required
before a guided form is safe.
Conference Service is guided as a distinct resource-free variant of the existing
`conference` module. The UI and public API use only `service_mode: true` and
`skip_module`; Laravel removes the public discriminator, Switch receives no
conference `id`, and configured Conference routing continues to resolve an
account-scoped public UUID server-side. The installed runtime treats the missing
ID as account-scoped discovery, prompts up to three times for a 1–16 digit
conference number, and applies the selected conference's existing member or
moderator PINs. Focused SDK/API/resolver/Zod/component tests cover variant
collision and unknown-field preservation. A 2026-08-30 disposable isolated
headless run verified create below Receive Fax, skip edit, authoritative reopen,
raw absence of `id`, injected private-field preservation and public redaction,
delete, MySQL soft deletion, and zero matching active Switch callflows. The run
validated configuration lifecycle and cleanup; it did not originate a media-leg
prompt call. The connected local topology currently has Kazoo applications and
CouchDB but no FreeSWITCH/media process, so prompt observation remains pending
until a disposable media leg can be originated without expanding repository
scope.
Check Voicemail is guided only as Kazoo's resource-free `voicemail` action with
`action: check` and `skip_module`; it never accepts or emits a mailbox `id`.
Installed runtime inspection confirmed account-scoped mailbox-number discovery,
bounded login retries, PIN enforcement, and the existing authenticated-owner
`require_pin = false` exception. GridPBX does not expose or accept
`single_mailbox_login` or `callerid_match_login`. A 2026-08-30 disposable live
run verified create below Conference Service, skip edit, authoritative reopen,
raw absence of `id`, public redaction, unknown-field preservation, deletion,
MySQL soft deletion, and zero matching active Switch callflows. Kazoo
materialized both private login flags as `false` in the raw node; they remained
private and unchanged.
Global Carrier and Account Carrier remain capability-gated after inspection of
the installed `offnet`/`resources`, route-entry, selector, and StepSwitch bridge
runtime. Both schemas can override the final DID and normalization, caller-ID
source, SIP headers, resource type, and carrier-selection flags after the
originating endpoint restriction check classified the original request.
`offnet` forcibly selects system-wide resources. `resources` defaults to the
current account's local pool and permits a raw `hunt_account_id`; StepSwitch
checks account hierarchy, but raw account identifiers cannot cross GridPBX's
public boundary. Installed StepSwitch also bypasses emergency caller-ID
validation whenever a hunt account is present, while global routing defaults to
continuing with an anonymous caller ID when invalid emergency CID denial is not
explicitly enabled. Enabling either action requires an authenticated outbound-
only route context, classification of the final normalized destination,
default-deny premium/international/emergency policy, loop prevention, spend/
rate/concurrency limits, immutable audit events, server-owned SIP/resource
settings, and public-UUID account authorization for any local carrier pool.
Webhook remains capability-gated after inspection of the installed callflow
schema, compiled `cf_webhook`/webhooks runtime, active URL blacklist, and Erlang
HTTP/TLS defaults. It asynchronously sends a broad call snapshot with raw
Switch identifiers, caller/callee data, SIP headers, application variables, and
Switch-host details to an operator-entered HTTP(S) URI. Delivery has no signed
authentication, exposes raw account and hook IDs in headers, follows up to four
redirects, has no total request timeout, and does not verify TLS certificates or
hostnames. The deployment blocks only literal `localhost`, `127.0.0.1`, and
`0.0.0.0`; hostname resolution, private/link-local/metadata ranges, DNS
rebinding, and redirect destinations are not safely enforced. Failed attempts
persist complete request and response bodies in the account MODB. GridPBX did
not issue a live callback. A guided form requires server-owned HTTPS allowlists,
per-hop DNS/IP enforcement, verified TLS, bounded request/response handling,
signed minimal public-safe payloads with replay protection, redacted retention-
bounded delivery records, safe retry/rate/circuit policy, audit events, and a
kill switch.
Dynamic CID remains capability-gated after inspection of the installed schema,
compiled runtime, Monster workflow, downstream caller-ID selection, and active
system configuration. Monster creates an empty node, which the runtime treats
as manual mode and uses to collect any replacement caller-ID number matching the
default ten-digit `\d+` policy. The runtime does not establish that the asserted
number belongs to the account. Static mode also accepts arbitrary caller-ID
name/number data, while list modes consume raw list IDs and may reroute the
captured destination. Their destination restriction check fails open when the
endpoint cannot be loaded and can be explicitly disabled. Downstream dynamic
external caller-ID ownership validation depends on
`callflow.ensure_valid_caller_id`; it is unset in this deployment and therefore
uses the installed false default. Enabling a safe variant requires only
account-scoped public Phone Number UUIDs or projected caller-ID profiles,
server-side ownership and E911 validation, authenticated feature-code context,
fail-closed final-destination restrictions, anti-spoofing audit and rate limits,
and live carrier-level verification. Raw list IDs, arbitrary static/manual
numbers, restriction bypasses, and custom-route overrides remain private and
unavailable.

## 6. P2 operational features

| Domain | User-facing capabilities | Switch boundary | Projection notes | Status |
| --- | --- | --- | --- | --- |
| Advanced callflows | Node canvas, categorized action palette, recursive branches, module forms, validation, version-safe updates, and dependency view | Callflows and referenced resources | Interactive recursive canvas, safe node inspector, Kazoo-grouped version-aware palette, guided root/fallback/Menu/Rule Set/Branch Bnumber writes, palette drag/drop, guarded subtree moves/reorders, public condition branches, resource forms, and bounded inline action forms delivered. All installed default-palette actions are guided or explicitly capability-gated, and the installed registry plus a redacted active-account inventory found no additional default-palette keyed branch contract. Future schema/search-only modules and unknown branch shapes remain read-only until separately audited | Foundation |
| IVR menus | CRUD, prompts, retries, timeout, key destinations | Menus, media, callflows | CRUD, prompt/media options, projection/sync, dependency-safe delete, guided routing, and safe root-level DTMF/timeout branches delivered; deeper recursive editing remains planned | Foundation |
| Time-of-day | Rules, holidays, rule sets, enable/disable/reset | Temporal rules and rule sets | Rule and ordered Rule Set CRUD, projection/sync, safe deletion, effective status and controls, plus schema-correct `rule_set`/`_` guided routing delivered | Foundation |
| Media and music on hold | Upload, stream, rename, delete, assignment | Media and account settings | Validated upload/audio panels, protected range streaming, dependency-safe deletion, non-clipping account-default choice, hidden schema-field preservation, and metadata-only MySQL projection delivered | Foundation |
| Directories | CRUD and user membership | Directories and users | Directory membership projection | Foundation |
| Groups and ring groups | CRUD, membership, endpoints, ring strategy | Groups, users, devices, callflows | Group/member relationships | Foundation |
| Conferences | CRUD, role numbers, write-only PIN replacement/removal, participant behavior, and runtime summary | Conferences and callflows | Normalized role-number projection, owner relationship, redacted source snapshot, dependency-safe deletion, guided routing, and right-side panel delivered; live participant commands remain planned | Foundation |
| Fax boxes | CRUD, owner assignment, inbound/outbound message metadata, protected document access, and guided callflow destinations | Fax boxes, faxes, users, and callflows | Normalized fax-box/message projections, redacted `switch_json`, bounded import window, dependency-safe deletion, right-side panels, and audited document streaming delivered; sending, forwarding, resubmission, and message deletion remain policy-gated | Foundation |
| Blacklists | CRUD, number entries, anonymous-caller policy, and account activation | Blacklists and account settings | Normalized entries, redacted source snapshot, safe activation/deactivation, sync, and right-side UI panel delivered | Foundation |
| Caller-ID Lists | Reusable number/pattern lists for conditional routing | Lists, list entries, and `cidlistmatch` callflows | Account-scoped list and entry projection, separate redacted `switch_json`, queued sync, public UUID selector, private Switch-ID resolution, compensated API mutations, and standalone slide-over CRUD UI delivered. Authenticated create, edit, authoritative reopen, entry clear, and delete are verified against the local Switch; the deployment must autoload `cb_lists` | Foundation |
| Phone numbers | Inventory, routing assignment, CNAM, E911, porting, purchasing, and release | Phone numbers, number manager, callflows, and carrier providers | Safe inventory/detail projection and runtime feature-availability matrix delivered; billable and regulated mutations remain disabled until provider, billing, compliance, and confirmation policies are configured | Foundation |
| Feature codes | View and manage supported star-code callflows for DND, hotdesk, voicemail, and related actions | Callflows | Code, action, enabled state, and dependency summary | Planned |
| Account voice settings | Caller ID, timezone, language, music on hold, restrictions, recording defaults, dial plans, request formatters, preflow, metaflow activation/actions, and supported account defaults | Accounts, media, phone-number classifiers, callflows, devices, extensions, and configs | Typed virtual settings from redacted `switch_json`; safe regex rules, shared recursive action editor, locked-tree/unknown-option preservation, public UUID references, unresolved-reference controls, and protected storage URL preservation | Foundation |
| Call history | Search, direction/date/duration/outcome/cause filters, interaction detail | CDRs | Bounded, indexed CDR projection | Foundation |
| Recordings | Search, metadata, authorized playback/download | Recordings and storage | Bounded metadata-only projection, audited protected playback/download, and no GridPBX deletion until retention/provider cleanup is approved | Foundation |
| Active channels | Current calls and account activity | Channels | Short-lived cache, not durable projection | Conditional |
| Services and billing visibility | Assigned plans, account/cascade/manual quantities, standing, billing cycle, current limits, aggregate billing impact, ledger-source usage, ledger total, and recent Switch transactions | Services summary, limits, ledgers, ledger total, and transactions | Administrator-only normalized read projection, explicit version-aware endpoint availability, immutable transaction retention, payment/bookkeeper redaction, queued sync, and right-side detail panel delivered; plan/limit/top-up/credit/debit/sale/refund/charge mutations remain disabled | Foundation |
| Line keys and provisioning preview | Device combo/feature key inventory, safe full-replacement preview, and capability-gated apply | Device `provision.combo_keys` / `feature_keys` PATCH | Device-owned normalized rows plus the redacted device snapshot; no SIP credentials, provisioning URLs, templates, or generated documents exposed | Foundation |

Implementation status: Foundation. The entity-organized Switch client now
paginates media inventory, hydrates full metadata, creates and updates bounded
media documents, uploads and range-streams audio, and reads or patches the
account `music_on_hold.media_id` reference. Laravel projects redacted metadata
to `switch_media` with a named internal ULID primary key and public UUID,
stores the selected MOH relationship on the account, synchronizes through a
unique queued job, and never stores binary audio in MySQL. Authenticated API
operations cover list/detail, upload, rename, audio replacement/streaming,
MOH assignment, and deletion. Deletion is blocked when the projection detects
account MOH, voicemail-greeting, or callflow play-node dependencies. The Vue
domain uses Axios envelope unwrapping and ArchitectUI-style Tailwind inventory
and right-side panels for every mutation. Broader dependency detection for
menus, groups, conferences, and queues becomes active with those projections;
production acceptance against the client Switch remains required.

### CDR and recording constraints

Before enabling production CDR projection, define:

- Retention and archival periods
- Expected account volume and import window
- Required indexes and partitioning strategy
- Personally identifiable information controls
- Recording authorization and download audit rules
- Whether recordings stay in Switch storage or use an external object store

Implementation status: Foundation. `grid-api-switch` now reads normalized CDR
list pages for a bounded Gregorian timestamp range without hydrating sensitive
full CDR documents. Laravel imports an on-demand window configured by
`SWITCH_CDR_IMPORT_WINDOW_DAYS` (default seven, maximum 31), upserts call legs
idempotently, links known owner IDs to extension projections, and indexes the
account/date, direction, owner, interaction, cause, and duration query paths.
The JSON snapshot is an explicit allowlist from each response `data` item and
omits costs, rates, authorization IDs, recording URLs/media lists, SIP headers,
DTMF, and SDP. Vue provides responsive filters and a read-only right-side
detail panel. The separate Recording foundation uses a bounded 31-day default
window, projects normalized metadata plus the complete redacted recording
`data` object, resolves extension/CDR relationships, and streams audio through
an authorized, audited API with byte-range support. Binary content is never
copied to MySQL. Recording deletion, automatic retention, production
scheduling, and object-provider cleanup remain disabled until those policies
are approved.

## 7. Callflow module catalog

The visual callflow editor will support modules in controlled groups. A module
is enabled only after its schema, references, validation, projection behavior,
and UI editor have automated tests.

### Initial modules

- User/extension
- Device
- Voicemail
- Ring group
- IVR menu
- Play media
- Another callflow
- Time-of-day route
- Off-net route
- Conference
- Directory
- Fax box when fax is enabled

### Advanced or conditional modules

- Page group and group pickup
- Hotdesk login/logout/toggle
- Do-not-disturb activate/deactivate/toggle
- Ring-group login/logout
- Caller-ID manipulation
- Set custom channel variables
- Distinctive ring/alert info
- Text to speech
- Collect DTMF
- DISA
- Manual response, language, delay, and manual presence
- Webhook and Pivot
- Call recording and missed-call alerts
- Account carrier/resource selection

Unknown or unsupported modules must be preserved during a safe edit and shown
as read-only nodes. GridPBX must never silently remove a callflow branch it
does not understand.

## 8. P3 advanced and reseller capabilities

These capabilities are confirmed target scope where listed in section 3.2,
but their sequence still requires deployment discovery and dependency design.

| Domain | Candidate capabilities | Status/constraint |
| --- | --- | --- |
| Queues and agents | Queue CRUD, membership, agent state, call statistics | Foundation for CRUD, roster, live status, sync, and guided routing. Read-only account probes now distinguish configuration, live controls, and statistics; the installed deployment reports `true`, `false`, and `false`. Live controls require ACDc, and statistics remain capability-gated |
| Presence and parked calls | Presence status, parked-call visibility and actions | Read-only foundation delivered: subscription-diagnostic capability plus aggregate parked-call count. Live presence state, presence commands, slot detail, and park/retrieve actions remain capability-gated |
| Webhooks | CRUD, event selection, delivery health, secret-safe configuration | Conditional; security review required |
| SMS/MMS | Message threads, send/receive, number capability | Conditional; carrier and retention requirements |
| Number porting | Port requests, documents, status workflow | Conditional; compliance and carrier integration |
| Connectivity/trunks | Resources, gateways, limits, routing and failover | P3; high-risk administrator-only feature |
| Account administration | Create/update accounts, descendants, limits, service plans | P3; reseller authorization required |
| White-labeling | Brand, domain, logo, colors, email identity | P3; split Switch and GridPBX ownership explicitly |
| Provisioning templates | Global/local templates, model capabilities, zero-touch provisioning | Conditional; vendor integration required |
| Notifications | Template and destination management | Conditional; protect system templates |
| Security controls | Access lists, IP authentication, MFA, token restrictions | P3; separate threat model required |

## 9. Business features adjacent to Switch

The legacy application includes clients, branding, billing groups, payment
methods, payments, invoices, and customer provisioning. These are not assumed
to be Switch-owned PBX features. Switch-calculated services, ledgers, and
transactions are projected read-only for operational visibility, but they do
not make GridPBX a second accounting ledger or payment processor.

They remain separate GridPBX business bounded contexts:

- Reseller and client CRM
- Billing groups and pricing
- Invoices and payments
- Payment methods and gateways
- Customer onboarding and account provisioning
- Branding and tenant preferences

Before implementation, the client must identify the authoritative system for
each dataset. Payment features require a dedicated security and compliance
design and must not be inferred from the legacy code. Any future provider
adapter must use hosted/tokenized card capture, idempotent payment attempts,
signed-webhook reconciliation, immutable audit, and sandbox-only credentials
before live processing is considered.

## 10. Explicitly excluded from the management application

The following are infrastructure responsibilities, not features implemented by
the Laravel/Vue management repository:

- Running or embedding Switch itself
- FreeSWITCH installation and `ecallmgr` operation
- SIP edge, SBC, carrier network, RTP, NAT, and firewall operation
- CouchDB or RabbitMQ administration
- Rebuilding Monster UI or its application store
- Direct database writes into Switch/CouchDB documents

GridPBX may display health or diagnostic summaries for separately managed
telephony infrastructure, but it does not replace that infrastructure.

## 11. Feature delivery checklist

A Switch feature can move to `Complete` only when:

1. Target-deployment capability and Crossbar schemas are confirmed.
2. User stories, permissions, and destructive actions are documented.
3. The owning DDD module and anti-corruption mappings are implemented.
4. MySQL projection schema, indexes, import, refresh, reconciliation, and
   tombstone behavior are implemented.
5. Switch-first writes handle partial failure and schedule repair.
6. Secrets and unnecessary raw fields are excluded from persistence and logs.
7. API endpoints have stable validation and error contracts.
8. UI loading, empty, stale, syncing, success, validation, and failure states
   are implemented responsively and accessibly.
9. Unit, feature, integration, projection-rebuild, and browser tests pass.
10. Audit events, operational metrics, and support documentation exist.
11. The client accepts the workflow against a representative Switch account.

## 12. Decisions required from the client

- Which P1 workflow must be delivered first after extensions
- Required Switch version and enabled Crossbar applications in production
- Single-account, reseller hierarchy, or both
- Acceptable projection freshness for each domain
- CDR and recording retention requirements
- Required callflow modules for the initial editor
- Device provisioning vendors and supported phone models
- Target deployment support and integration details for fax, SMS/MMS, queues,
  provisioning, and number porting
- Carrier and emergency-services ownership, credentials, charges, and
  compliance responsibilities
- Authoritative billing/CRM system and integration boundary for GridPBX
- Required migration/parity threshold before Monster UI is retired

These decisions should update this roadmap before the affected feature enters
implementation.
