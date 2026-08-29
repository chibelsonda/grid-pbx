# Switch Feature Implementation Roadmap

Status: Active delivery roadmap
Last updated: 2026-08-29

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
| Account | Account mapping, hierarchy, settings, and capability discovery | Safe detail/counts, redacted `switch_json`, typed identity/calling settings, public-UUID caller-ID choices with E911 enforcement, and confirmed audited enable/disable delivered; hierarchy and advanced settings remain | Foundation |
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
| SystemStatus | Connectivity, capability, and relevant telephony health summaries | Live checks or short-lived cache; do not persist full infrastructure payloads as durable projections | Foundation |
| TemporalRule | Business-hours, holiday, time-of-day CRUD, effective status, and operational override | Validated normalized schedules, redacted source snapshots, CRUD, sync, dependency-safe deletion, timezone-aware status, and audited force-active/force-inactive/reset commands delivered | Foundation |
| TemporalRuleSet | Rule-set CRUD, ordering, effective status, enable/disable, and reset workflows | Ordered membership, CRUD, sync, guided `temporal_route` routing, aggregate status, and compensating member-rule command fan-out delivered | Foundation |
| User | User CRUD, identity, caller ID, feature state, and resource assignments | Project safe identity/settings and redacted detail snapshots | Foundation |
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
| Capability discovery | Enabled Crossbar modules and account capabilities | Per-account feature flags | P1 | Planned |
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
prompt types and bulk mailbox settings remain planned.

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
writes. A searchable, categorized reference palette covers all 73 primary
module schemas in the checked-in Switch source and distinguishes guided,
planned, and capability-gated actions without presenting unsupported mutations
as available. General multi-node creation, number acquisition/release, and
editable canvas interactions remain planned and module-gated.

## 6. P2 operational features

| Domain | User-facing capabilities | Switch boundary | Projection notes | Status |
| --- | --- | --- | --- | --- |
| Advanced callflows | Node canvas, categorized action palette, recursive branches, module forms, validation, version-safe updates, and dependency view | Callflows and referenced resources | Selectable read-only recursive canvas, safe node inspector, complete schema-backed reference palette, and guided root/fallback/Menu/Rule Set writes delivered; drag/drop, arbitrary node mutation, and module forms remain planned | Foundation |
| IVR menus | CRUD, prompts, retries, timeout, key destinations | Menus, media, callflows | CRUD, prompt/media options, projection/sync, dependency-safe delete, guided routing, and safe root-level DTMF/timeout branches delivered; deeper recursive editing remains planned | Foundation |
| Time-of-day | Rules, holidays, rule sets, enable/disable/reset | Temporal rules and rule sets | Rule and ordered Rule Set CRUD, projection/sync, safe deletion, effective status and controls, plus schema-correct `rule_set`/`_` guided routing delivered | Foundation |
| Media and music on hold | Upload, stream, rename, delete, assignment | Media and account settings | Validated upload/audio panels, protected range streaming, dependency-safe deletion, non-clipping account-default choice, hidden schema-field preservation, and metadata-only MySQL projection delivered | Foundation |
| Directories | CRUD and user membership | Directories and users | Directory membership projection | Foundation |
| Groups and ring groups | CRUD, membership, endpoints, ring strategy | Groups, users, devices, callflows | Group/member relationships | Foundation |
| Conferences | CRUD, role numbers, write-only PIN replacement/removal, participant behavior, and runtime summary | Conferences and callflows | Normalized role-number projection, owner relationship, redacted source snapshot, dependency-safe deletion, guided routing, and right-side panel delivered; live participant commands remain planned | Foundation |
| Fax boxes | CRUD, owner assignment, inbound/outbound message metadata, protected document access, and guided callflow destinations | Fax boxes, faxes, users, and callflows | Normalized fax-box/message projections, redacted `switch_json`, bounded import window, dependency-safe deletion, right-side panels, and audited document streaming delivered; sending, forwarding, resubmission, and message deletion remain policy-gated | Foundation |
| Blacklists | CRUD, number entries, anonymous-caller policy, and account activation | Blacklists and account settings | Normalized entries, redacted source snapshot, safe activation/deactivation, sync, and right-side UI panel delivered | Foundation |
| Phone numbers | Inventory, routing assignment, CNAM, E911, porting, purchasing, and release | Phone numbers, number manager, callflows, and carrier providers | Safe inventory/detail projection and runtime feature-availability matrix delivered; billable and regulated mutations remain disabled until provider, billing, compliance, and confirmation policies are configured | Foundation |
| Feature codes | View and manage supported star-code callflows for DND, hotdesk, voicemail, and related actions | Callflows | Code, action, enabled state, and dependency summary | Planned |
| Account voice settings | Caller ID, timezone, language, music on hold, and supported account defaults | Accounts, media, configs | Safe effective-setting summary | Planned |
| Call history | Search, direction/date/duration/outcome/cause filters, interaction detail | CDRs | Bounded, indexed CDR projection | Foundation |
| Recordings | Search, metadata, authorized playback/download | Recordings and storage | Bounded metadata-only projection, audited protected playback/download, and no GridPBX deletion until retention/provider cleanup is approved | Foundation |
| Active channels | Current calls and account activity | Channels | Short-lived cache, not durable projection | Conditional |
| Services and limits | Assigned plans, account/cascade/manual quantities, standing, billing cycle, current limits, and aggregate billing impact | Services summary and limits | Administrator-only read projection, payment/bookkeeper redaction, queued sync, and right-side detail panel delivered; plan/limit/top-up/charge mutations remain disabled | Foundation |
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
| Queues and agents | Queue CRUD, membership, agent state, call statistics | Foundation for CRUD, roster, live status, sync, and guided routing; the external Switch must run ACDc and autoload `cb_queues` and `cb_agents`; statistics still require the live ACDc runtime |
| Presence and parked calls | Presence status, parked-call visibility and actions | Conditional; near-real-time behavior required |
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
to be Switch-owned PBX features.

They remain separate GridPBX business bounded contexts:

- Reseller and client CRM
- Billing groups and pricing
- Invoices and payments
- Payment methods and gateways
- Customer onboarding and account provisioning
- Branding and tenant preferences

Before implementation, the client must identify the authoritative system for
each dataset. Payment features require a dedicated security and compliance
design and must not be inferred from the legacy code.

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
