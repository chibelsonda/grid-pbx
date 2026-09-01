# Switch Feature Implementation Roadmap

Status: Active delivery roadmap
Last updated: 2026-09-01

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

| Priority | Meaning                                                    |
| -------- | ---------------------------------------------------------- |
| P0       | Platform foundation required by every Switch feature       |
| P1       | First usable PBX-management release                        |
| P2       | Core operational parity for common customer workflows      |
| P3       | Advanced operations, reseller, and specialist workflows    |
| Deferred | Requires a separate decision, integration, or threat model |

Implementation status:

| Status      | Meaning                                                                                       |
| ----------- | --------------------------------------------------------------------------------------------- |
| Foundation  | Supporting code exists, but the end-to-end feature is incomplete                              |
| Planned     | Accepted into the roadmap but not implemented                                                 |
| Conditional | Implement only when the target Switch deployment supports it and the client confirms the need |
| Deferred    | Explicitly outside the current delivery commitment                                            |
| Complete    | Implemented, synchronized, tested, documented, and accepted                                   |

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

| Requested area   | GridPBX treatment                                                                                                                                                | Persistence and delivery rule                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Status     |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| Account          | Account mapping, hierarchy, settings, and capability discovery                                                                                                   | Safe detail/counts, redacted `switch_json`, schema/workflow-aligned shared Basic/Advanced settings, restrictions, recording defaults, guided dial plans/formatters, public-UUID caller-ID/preflow/metaflow resources, supported recursive metaflow editing with locked-tree preservation, E911 enforcement, confirmed audited enable/disable, hierarchy projection, reseller diagnostics, and confirmed existing-descendant onboarding delivered; remaining high-risk settings and account-tree mutations stay gated | Foundation |
| Blacklist        | Blacklist CRUD, number entries, and account-level inbound activation                                                                                             | Normalized searchable entries, active state, redacted `data`, schema-aligned Basic-only form matching Monster's single view, and external-flag preservation delivered                                                                                                                                                                                                                                                                                                                                                | Foundation |
| CallerIdList     | Reusable caller-number/pattern matching lists and guided conditional routing                                                                                     | Schema-aligned shared Basic/Advanced form, account-scoped public List/entry UUIDs, private raw parent-ID mapping, safe-regex validation, separate redacted list/entry snapshots, and hidden/unknown-field preservation delivered                                                                                                                                                                                                                                                                                     | Foundation |
| CallDetailRecord | Call history, filters, interaction detail, and Recording links                                                                                                   | Project only approved fields and allowlisted `switch_json`; validated filters and reciprocal public-UUID Recording links delivered; retention and partitioning must be agreed before production scheduling                                                                                                                                                                                                                                                                                                           | Foundation |
| Callflow         | Routing inventory, dependency resolution, guided editing, and safe unknown-branch preservation                                                                   | Project routing summaries and a redacted detail snapshot                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Foundation |
| Conference       | Conference configuration, role access numbers, participant behavior, last-observed runtime status, and guided routing                                            | Normalize role-number relationships, retain the full redacted `data` snapshot, and store only PIN-configured flags                                                                                                                                                                                                                                                                                                                                                                                                   | Foundation |
| Device           | Device CRUD, user assignment, registrations, and provisioning metadata                                                                                           | Schema-backed outer Basic/Advanced workflow plus Monster-aligned per-type Advanced sub-tabs; public UUID relationships and safe device/registration projection never persist or return SIP secrets or raw Switch IDs                                                                                                                                                                                                                                                                                                 | Foundation |
| Directory        | Directory CRUD and user membership                                                                                                                               | Project directory metadata and public-UUID relationships; external flags and safe unknown future public fields are preserved server-side across full updates without becoming operator input                                                                                                                                                                                                                                                                                                                         | Foundation |
| Fax              | Fax-box configuration, inbound/outbound message inventory, authorized document access, and guided routing                                                        | Schema-aligned validated shared Basic/Advanced form matching Monster's Fax Box grouping, safe public owner/number/timezone choices, hidden notification/flag/unknown-field preservation, normalized metadata, redacted `data`, and protected document streaming delivered                                                                                                                                                                                                                                            | Foundation |
| Group            | Group and ring-group configuration, membership, endpoints, and strategy                                                                                          | Public-UUID Group/member relationships, Media mapping, cycle/dependency protection, external flags, safe unknown full-update preservation, and the confirmed Monster Basic-only resource workflow are delivered                                                                                                                                                                                                                                                                                                      | Foundation |
| LineKey          | Device line-key configuration and provisioning preview                                                                                                           | Device-owned normalized projection plus redacted device `switch_json`; complete installed key-type forms, public-UUID resource suggestions, retained-row unknown-field preservation, safe preview, and capability-gated upstream apply are delivered                                                                                                                                                                                                                                                                 | Foundation |
| Media            | Media metadata, prompts, music-on-hold, upload, and authorized streaming                                                                                         | Schema-aligned shared Basic/Advanced upload form matching Monster's streaming Options split, protected audio replacement/streaming, Headless UI music-on-hold choice, lossless server-side preservation of hidden and unknown safe public fields, and redacted detail delivered; generated TTS/recording remains capability-gated                                                                                                                                                                                    | Foundation |
| Menu             | IVR menu CRUD, prompt, timeout, retry, and key destinations                                                                                                      | Complete installed form, runtime-correct prompt suppression, write-only PIN replacement/removal, public Media UUIDs, unresolved/unknown-field preservation, sync, dependency-safe delete, guided routing, and safe root-level DTMF/timeout branches delivered; deeper branch trees remain part of the visual editor                                                                                                                                                                                                  | Foundation |
| PhoneNumber      | Number inventory, features, assignment, and approved carrier workflows                                                                                           | Version-aware feature availability, allowlisted CNAM/E911/porting detail, explicit operation capability gates, and redacted snapshots delivered; carrier mutations remain policy-gated                                                                                                                                                                                                                                                                                                                               | Foundation |
| Queue            | Queue CRUD, agents, membership, state, and statistics                                                                                                            | Capability-driven projection; configuration requires the target ACD/queue application                                                                                                                                                                                                                                                                                                                                                                                                                                | Foundation |
| Recording        | Recording search, metadata, and authorized playback/download                                                                                                     | Bounded metadata-only projection, validated advanced filters, redacted source snapshot, reciprocal CDR relationship resolution, audited range streaming, and right-side detail UI delivered; deletion remains disabled pending retention policy                                                                                                                                                                                                                                                                      | Foundation |
| Services         | Account service-plan, limits, quantities, standing, billing-cycle, and billing-impact summaries exposed by Switch                                                | Administrator-only normalized read projection with redacted `switch_json`; all billing mutations remain disabled                                                                                                                                                                                                                                                                                                                                                                                                     | Foundation |
| SystemStatus     | Connectivity, capability, and relevant telephony health summaries                                                                                                | Account-scoped live probes with a ten-second cache expose only safe booleans/counts for Presence diagnostics, parked calls, Webhooks, SMS/MMS inventory, Port Requests, and carrier configuration. The presentation is intentionally single-view/read-only with Refresh as its only operation; raw payloads are discarded and no mutation is inferred from endpoint availability                                                                                                                                     | Foundation |
| TemporalRule     | Business-hours, holiday, time-of-day CRUD, effective status, and operational override                                                                            | Validated normalized schedules, redacted source snapshots, CRUD, sync, dependency-safe deletion, timezone-aware status, and audited force-active/force-inactive/reset commands delivered                                                                                                                                                                                                                                                                                                                             | Foundation |
| TemporalRuleSet  | Rule-set CRUD, ordering, effective status, enable/disable, and reset workflows                                                                                   | Ordered membership, CRUD, sync, guided `temporal_route` routing, aggregate status, and compensating member-rule command fan-out delivered                                                                                                                                                                                                                                                                                                                                                                            | Foundation |
| User             | User CRUD, identity, caller ID, forwarding, recording, restrictions, feature state, resource assignments, and supported recursive metaflows                      | Managed edits now include public-UUID caller ID/MOH/pronounced-name Media, current endpoint media/ringtones, safe dial plans/formatters, bounded profiles, E911 enforcement, and read-only preserved policy state                                                                                                                                                                                                                                                                                                    | Foundation |
| Voicemail        | Mailbox CRUD, Device-style outer Basic/Advanced and Monster-aligned inner Basic/Options forms, assignments, greetings, messages, and permitted lifecycle actions | Project mailbox/message metadata; reuse one schema-backed form across standalone and embedded Extension workflows, stream audio, and keep PINs write-only                                                                                                                                                                                                                                                                                                                                                            | Foundation |

The 2026-08-31 cross-entity form pass standardizes writable multi-section
resources on one horizontal, overflow-safe Device tab control. Nested Monster
sections remain explicit for Conference, Menu, and Voicemail; Queue grouping
comes from installed schema/runtime evidence because no Monster Queue editor is
present. Confirmed Basic-only and read-only surfaces remain tabless. The final
focused pass covered 12 UI files / 55 tests, Vue and isolated E2E typechecks,
and three non-mutating isolated headless Playwright checks.

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

| Confirmed capability                                          | Owning domains                                                     | Delivery gate                                                                                                                                                                                                                                                                                                         | Status      |
| ------------------------------------------------------------- | ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| Number purchasing, porting, releasing, CNAM, and E911 changes | Phone numbers, carriers, auditing                                  | Read-only Port Request and carrier-info endpoint-shape foundations delivered; number search/provider inventory, quotes/charges, sensitive request/document handling, ownership transitions, privileged confirmation, emergency-service compliance, idempotency, compensation, and reconciliation still gate mutations | Conditional |
| Advanced visual callflow editing                              | Call routing and referenced PBX domains                            | Full main-page drag-and-drop graph and action palette, selected-node-only right panel, version-safe writes, public-ID reference resolution, schema validation, dependency checks, and lossless preservation of unknown branches                                                                                       | Foundation  |
| Queues and agents                                             | Queues, users, devices, and call routing                           | Foundation delivered for configuration, roster, live status commands, and guided routing; statistics remain conditional                                                                                                                                                                                               | Foundation  |
| SMS/MMS                                                       | Messaging, phone numbers, users, and auditing                      | Read-only endpoint-availability foundation delivered; enabled carrier capability, consent, retention, attachment policy, delivery events, billing, and abuse controls still gate content and sending                                                                                                                  | Conditional |
| Recordings                                                    | Recordings, call history, storage, and auditing                    | Bounded metadata projection, authorized audited range streaming, public-UUID CDR links, and read-only UI delivered; deletion, retention automation, production scheduling, encryption, and storage-provider policy remain gated                                                                                         | Foundation  |
| Provisioning                                                  | Devices, line keys, templates, and vendor integrations             | Safe model-catalog selection, bounded model capabilities, public-UUID line-key values, and gated Device synchronization/enrollment foundations are delivered. Global/local template administration and zero-touch rollout remain gated by missing installed field schema, template ownership/precedence, secret handling, hardened provisioner egress, dependency-safe deletion, staged rollout/rollback, and vendor integration evidence | Conditional |
| Billing and reseller management                               | Organizations, accounts, services, billing, and authorization      | Organization-scoped hierarchy/onboarding and administrator-only billing visibility, reconciliation, source-labelled invoice/receipt access, and gated payment foundations delivered; authoritative provider selection, pricing/plan, account-tree, reseller-role, and production-payment mutations remain gated         | Foundation  |
| Trunks, carriers, resources, and connectivity                 | Connectivity, routing, accounts, and system status                 | Installed Resource/Trunkstore schemas, StepSwitch runtime, Monster workflows, and current redaction boundary audited; no Resource/Gateway/Trunk projection or mutation exists. Credential vaulting, final-destination policy, emergency/failover safety, billing authority, rollback, and representative media/carrier tests gate implementation | Planned     |
| Webhooks and advanced administration                          | Webhooks, notifications, security controls, accounts, and auditing | Event allow-list, signing secrets, delivery observability, SSRF protection, least privilege, and separate security review                                                                                                                                                                                             | Conditional |

Every user-facing create or edit workflow in this scope uses the standard
right-side slide-over panel. Large visual editors may use a dedicated workspace
for the canvas, while node settings and CRUD forms still open from the right.
The shared interaction layer uses `@headlessui/vue` for dialogs, listboxes,
menus, switches, tabs, and disclosures, with Tailwind retaining full ownership
of the visual design. Native multi-select checkboxes remain semantic inputs
because Headless UI for Vue does not provide a checkbox primitive.

## 4. Platform and account foundation

| Capability                  | Switch boundary                                                    | MySQL/application responsibility                                                                                                                                                                                                                                                                       | Priority | Status                  |
| --------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | ----------------------- |
| Server-side authentication  | API-key authentication and `X-Auth-Token`                          | Secure configuration, Redis token cache, refresh lock                                                                                                                                                                                                                                                  | P0       | Foundation              |
| Switch connectivity health  | Crossbar root/auth request                                         | Health state, failure reason, last successful check                                                                                                                                                                                                                                                    | P0       | Foundation              |
| Account mapping             | Accounts                                                           | Organization-to-Switch-account mapping and authorization                                                                                                                                                                                                                                               | P0       | Foundation              |
| Account hierarchy           | Accounts and descendants                                           | Organization-scoped searchable projection, public-UUID parent/ancestor/child/descendant relationships, coverage and service-ownership diagnostics, and confirmed onboarding of an existing Switch descendant; raw tree IDs stay private and account creation/move/reseller-role mutations remain gated | P1       | Foundation              |
| Capability discovery        | Authentication response plus audited account/resource capabilities | The Switch token provider retains only typed, allowlisted feature booleans for application consumers. Voicemail transcription availability/default is delivered; broader module and account capability discovery remains                                                                               | P1       | Foundation              |
| Initial import              | Account-scoped resource APIs                                       | Sync runs, checkpoints, counts, errors, and timestamps                                                                                                                                                                                                                                                 | P1       | Foundation (extensions) |
| Incremental synchronization | Events/webhooks where supported; polling otherwise                 | Default-off Extension polling foundation reuses queued idempotent jobs, start/unique locks, checkpoints, and tombstones; interval and batch size are explicit deployment settings. Broader domain polling and event intake await approved freshness/load and signed-event policies                     | P1       | Foundation (extensions) |
| Full reconciliation         | Account-scoped list/detail APIs                                    | Repair projections and detect deletions                                                                                                                                                                                                                                                                | P1       | Foundation (extensions) |
| Global search               | Multiple account resources                                         | Authorized account-scoped search across safe projected fields; public resource UUIDs only, per-type policy filtering, bounded results, and no persistent browser result cache                                                                                                                          | P2       | Foundation              |

Installed `accounts/{id}/parents`, `tree`, `children`, and `descendants`
behavior confirms that Kazoo's `tree` contains raw account IDs ordered from
the most ancestral account to the immediate parent. GridPBX stores those IDs
only as private reconciliation references and exposes relationships through
organization-scoped public Account UUIDs. Unprojected descendants appear only
as counts and short-lived actor/scope-bound onboarding references. Onboarding
does not create, move, promote, demote, or modify a Switch account: it confirms
the exact name and inherited organization access, projects the existing
descendant, records an audit event, and queues its service projection. Focused
SDK and API tests plus an isolated authenticated browser run passed; the live
run exposed no raw account IDs and offered no reseller-role mutation control.

The Global Search foundation now queries 15 existing MySQL projection types
through one authenticated account-scoped endpoint. Every requested type is
filtered through its `viewAny` policy before querying, each result is bounded
and ranked deterministically, and the public contract contains only the
resource's public UUID plus allowlisted display metadata. Internal primary
keys, raw Switch resource IDs, `switch_json`, credentials, and provider data
are not selected or serialized. Literal SQL wildcard input is escaped and the
endpoint has a user/account/IP rate limit.

The workspace command palette uses a strict Zod response, cancellation of
stale requests, account-aware navigation, and in-memory recent results. Recent
titles and subtitles are cleared when the account or user changes and are not
written to browser storage, preventing stale permission-scoped metadata from
surviving a later session. Focused verification passed 9 Laravel tests with 58
assertions, 3 Vue files with 7 tests, Vue and isolated E2E TypeScript checks,
and 3 isolated headless browser scenarios. Two browser scenarios use mocked
search responses to verify keyboard/filter/navigation behavior, in-session
recent results, non-persistence, and public-UUID detail routing. A third
read-only scenario passed against the actual selected account and actual search
endpoint, returning a projected Callflow through the strict five-field public
contract with a public UUID. Human/client workflow acceptance is still required
before this capability can be marked Complete.

The first polling fallback is intentionally limited to the existing composite
Extension projection. A dedicated scheduler checks once per minute but exits
without reading accounts while `SWITCH_EXTENSION_POLLING_ENABLED=false`, which
is the default. When explicitly enabled, it selects only enabled accounts whose
Extension checkpoint is absent or older than the configured interval, skips
fresh and currently syncing accounts, and applies a bounded batch size. It then
reuses the ordinary start lock, unique queue job, reconciliation/tombstone
logic, and checkpoint state with a null requester to identify a system run.
Focused tests prove disabled, due, fresh, syncing, disabled-account, batch-cap,
and repeated-poll behavior. No polling was enabled against the live Switch.
Other domains and event/webhook intake remain planned until the client approves
freshness targets, Switch load, retry policy, and authenticated event delivery.

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

The optional call-geography surface now has a fail-closed aggregate projection,
account-scoped API, local SVG presentation, accessible table fallback, coverage
metric, and explicit estimated-location disclosure. Activation remains gated
until the client approves a numbering-plan enrichment source, privacy policy,
retention, and permitted roles. The dashboard never performs runtime geocoding
or sends call metadata to a tile provider. The API requires both an enabled
configuration and an available bound provider whose source exactly matches the
configured source; configuration alone cannot advertise availability. Its
coverage denominator is limited to inbound and outbound calls eligible for the
same enrichment path, excluding internal and unknown-direction records.

The operational Dashboard foundation now reads only account-scoped MySQL
projections through bounded overview, activity, missed-call, destination,
quality, and capability-gated geography endpoints. The overview distinguishes
the complete Device registration inventory from actionable registration
health: disabled Devices remain visible in totals but do not create an
unregistered-device warning. Recent missed-call navigation carries both
`direction=inbound` and `outcome=unanswered`, so its “View all” link cannot
silently broaden into answered calls. Activity and destination aggregates are
limited to inbound and outbound CDRs, keeping their totals consistent with the
only direction segments exposed by those contracts. Public responses use
Account, Sync Run, and CDR UUIDs only; internal primary keys, raw Switch
resource IDs, `switch_json`, synchronization errors, and credentials remain
private.

The complete pre-fix Dashboard baseline passed 34 Laravel tests / 180
assertions and 11 Vue files / 20 tests. The corrected Dashboard contracts
passed the affected 13 Laravel tests / 98 assertions and 3 Vue files / 10
tests, plus Vue and isolated E2E TypeScript checks. One read-only
isolated headless Playwright case passed against all six actual selected-account
Dashboard endpoints in 1.7 seconds, rendered every operational panel, and
confirmed the absence of private projection keys. Geography remains unavailable
by default, and no Switch or MySQL mutation was performed. Human/client
workflow acceptance is still required before the Dashboard can be marked
Complete.

### 5.2 People and extensions

An extension is a guided GridPBX workflow composed from several Switch
resources rather than a single Crossbar document.

The authoritative relationship, creation-order, compensation, update, and
dependency-aware deletion design is documented in
[SWITCH_ENTITY_RELATIONSHIPS.md](SWITCH_ENTITY_RELATIONSHIPS.md). All composite
workflows in this roadmap must follow those rules.

| Capability                          | Switch resources                                        | Projected data                                                 |
| ----------------------------------- | ------------------------------------------------------- | -------------------------------------------------------------- |
| List, search, and filter extensions | Users, callflows, devices, voicemail boxes              | Identity, extension numbers, assigned resources, enabled state |
| View extension details              | User plus related resources                             | Normalized relationship summary                                |
| Create extension                    | User, device, voicemail box, basic callflow as selected | Resulting IDs and relationship state                           |
| Edit profile and caller ID          | Users and account caller-ID settings                    | Safe display fields and caller-ID summary                      |
| Assign devices                      | Users and devices                                       | User-device relationships                                      |
| Configure voicemail                 | Users and voicemail boxes                               | Mailbox assignment and non-secret settings                     |
| Call forwarding, DND, and hotdesk   | User/callflow features when supported                   | Effective feature state                                        |
| Delete extension                    | Coordinated dependency-aware deletion                   | Tombstones and audit outcome                                   |
| Bulk changes                        | Bulk or bounded individual Switch operations            | Per-item result and reconciliation status                      |

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
A 2026-08-31 Advanced-tab drift re-audit found the complete managed-edit User
field set still connected to its Zod, Laravel, and typed Switch payload
boundaries. Focused SDK/API/UI checks and one isolated headless walkthrough
passed; no User implementation correction was needed.

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
brand, model, and MAC address. Retained key positions now preserve safe unknown
Switch fields during that replacement. Known Extension/User and Device values
remain public account-scoped UUIDs throughout the API/UI and are resolved to
raw provisioner values only inside Laravel immediately before the Switch
write; foreign UUIDs are rejected. A typed Monster-compatible `/phones`
catalog is
used when `SWITCH_PROVISIONER_URL` is configured; otherwise the UI states that
discovery is unavailable and permits manual hardware values. Generated provisioning
documents, bulk settings, and zero-touch provisioning remain conditional.
The schema-parity form supports all eight upstream device types,
Basic/Advanced conditional controls, nested SIP/forwarding/media/caller-ID,
recording, routing, formatter, metaflow, provisioning-event, and common
endpoint options, typed Switch DTOs, and safe configuration hydration. A
2026-08-31 drift re-audit restored shared Advanced editors that a UI refactor
had disconnected and reverified the minimal SIP URI/forwarding boundaries with
focused unit, API, SDK, and isolated headless checks. Remaining external
provisioner work and the exact acceptance checklist are tracked in
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
A 2026-08-31 Advanced-form drift re-audit found complete supported field
coverage but corrected the full-document update boundary: safe unknown public
fields and greeting/setup state are now preserved server-side, empty media is
serialized as a JSON object, and an unchanged write-only PIN is privately
recovered for the Kazoo replacement write. Focused SDK/API/UI checks and an
isolated disposable protected-mailbox create/edit/callback-clear/delete
lifecycle passed with zero active MySQL or CouchDB leftovers.

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
right-side detail panel. Acquisition, reservation, release, porting, caller-name changes,
E911 changes, and assignment mutations are intentionally unavailable until
deployment capabilities, carrier charges, permissions, and compliance rules
are approved. System Status adds separate non-sensitive Port Request and
carrier-info probes. The first uses an exact non-number filter because the
installed listing disables normal pagination; the second validates the
carrier-info envelope and immediately reduces it to one boolean. The live
routes are available, but provider names/modules, creation states, number
search results, quotes, charges, request details, documents, comments,
transitions, carrier automation, and number completion remain unavailable.
A 2026-08-31 form-drift re-audit reconfirmed that Phone Numbers intentionally
have a read-only detail panel rather than a generic Basic/Advanced editor.
Installed schema fields still cross carrier, billing, dry-run/charge,
emergency-routing, authority, and document-retention boundaries, so the
existing dedicated operation gates remain the safe workflow. Focused UI
regressions explicitly prohibit artificial Basic/Advanced tabs on this
read-only surface. Package, API, component, E2E TypeScript, and isolated
headless checks passed without a Phone Number or carrier mutation.

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
For Menu roots and nested Menu nodes, explicit branch operations cover
`timeout`, digits `0–9`, `*`, and the normal continuation. The recursive
canvas can add guided descendants to any empty supported branch and can safely
edit or move those descendants by public branch path. Numeric keys are
normalized as JSON object properties across Switch writes, MySQL snapshots,
and API responses. Existing legacy `#`, unknown vendor keys, and unsafe or
unresolved branches remain read-only and losslessly preserved. The focused
recursive-Menu reconciliation passed two Laravel validator tests / three
assertions, one Vue service file / 14 tests, and two isolated non-mutating
headless Playwright cases.
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
projected media dependencies. Guided creation now opens in the full main-page
callflow workspace rather than a slide-over. Its installed-palette catalog can
select a resource-backed root action or the bounded Extension/Device/Group Ring Group root,
while the reused Zod form collects the route name, public phone-number UUIDs,
and public destination UUIDs. The root popup can also author Menu key routes,
the Temporal Rule Set `rule_set` match route, and the wildcard `_` fallback
before the first save, and previews those branches on the canvas. The server
still builds the new phone-number route in Switch before projecting it; raw
Switch identifiers never enter the browser. The empty `_` continuation
also accepts guided resource-backed palette drops, opens the existing public
fallback selector, and rejects inline actions that the bounded create contract
cannot represent safely. A Menu root also accepts those resource-backed drops
on its first unused schema-editable key, then opens the same typed Menu form
with the projected public UUID selected. Raw Switch identifiers and arbitrary
node JSON never enter either draft path. Other inline actions become available
through their typed node forms after that authoritative root exists. The focused
create-workspace component test and one
isolated headless browser scenario verify fallback enable/disable, canvas
preview, root replacement, Menu keys, the `rule_set` match branch, guided
Voicemail fallback and Menu-key drag-and-drop, restricted inline-drop rejection,
and inline validation without a mutation.
Deletion is available only
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
insert-before, safe subtree swaps, and explicitly confirmed guided child-subtree
deletion are now implemented. Root and preserved branches remain non-removable;
the delete path refetches the authoritative Switch document and preserves
unrelated unknown data and siblings. Focused SDK/API/UI checks, an isolated
mocked browser scenario, and the disposable live `E2E Node Delete 133359`
lifecycle passed; cleanup independently confirmed one soft-deleted projection
and zero active Switch matches. Bounded inline forms
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
Ring Group now has a verified guided relationship foundation. GridPBX accepts
one to twenty distinct ordered account-scoped public Extension, Device, or
Group UUIDs and resolves raw Kazoo `user`, `device`, or `group` endpoint IDs
only at the server boundary. The public form exposes
`simultaneous`, `single`, and `weighted_random` strategies, endpoint delay and
timeout, one to three attempts, `ignore_forward`, `fail_on_single_reject`, and
`skip_module`; weighted-random requires an explicit `1`–`100` weight for every
member. `ignore_forward` defaults to `true` and makes endpoint forwarding fatal,
while `fail_on_single_reject` defaults to `false` and stops the remaining legs
after one rejection when enabled. Laravel computes Kazoo's top-level attempt
`timeout` and enforces a 120-second cap. Sequential strategies cannot use delay.
The installed Monster form has no weighted-random or bridge-flag controls, so
the installed schema and compiled runtime are authoritative: each weighted
attempt orders all endpoints without replacement and each retry reshuffles.
Unresolved endpoints, unsafe timings, and malformed legacy flag values remain
private and read-only unless the configuration fits the guided subset. User
and Group membership expands dynamically at call time, so the form describes
its limit as configured members rather than resolved Devices. Ringback is guided only through an
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

The default Basic Ring Group User/Group expansion audit is complete and the
account-scoped relationship choices are enabled. The installed runtime resolves Users through the caller
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
fan-out, Group overlap, cycles, or later membership changes. GridPBX accepts
only synchronized account-scoped public Extension and Group UUIDs, maps them
to raw Kazoo IDs at the Switch boundary, and maps authoritative results back
without exposure. Managed Group mutations reject direct and nested cycles;
unresolved or malformed legacy nodes remain read-only. Focused SDK, Laravel
validator/mutation/resolver, Zod, and component regressions protect the mixed
relationship boundary. Dynamic fan-out remains an explicit runtime caveat and
is not falsely represented as capped by the 20 configured-member limit.

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
The focused Ring Group Playwright scenario is prepared for that external
environment through `GRID_E2E_RING_GROUP_MEDIA_LEG_FILE`. Its acceptance gate
requires distinct internal/external account-local SIP call IDs, ESL-observed
`Alert-Info`, configured-Media matching, audible ringback, and zero carrier
attempts before cleanup. This gate is currently unexecuted rather than passed.
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
schema defaults an omitted response format to Kazoo while the installed worker
defaults it to TwiML. The worker collects streamed responses without a size cap,
does not enforce an iteration ceiling for response-driven follow-up requests,
and does not apply the configured Pivot timeout to the separate CDR callback.
The runtime provides no destination allowlist, private-network/DNS-rebinding
guard, application authentication header, callback signature, or Pivot-specific
redirect/TLS policy. Focused SDK, API, resolver, public-tree, and catalog
coverage proves that public writes are rejected, external identifiers and
configuration are redacted, and existing private Pivot data and descendants are
preserved. It must not become editable until the missing controls are
server-owned and enforced outside callflow JSON.
DISA also remains capability-gated. Its installed public schema exposes only
`skip_module`, while the runtime consumes undeclared legacy dialing controls,
explicitly permits access when the PIN is empty, and defaults call-restriction
enforcement off. Monster stores its optional PIN as visible node text; runtime
logs bad entered PIN digits, has no persistent lockout/rate limit, can route
exact, pattern, or account no-match Callflows, and marks either original or
account caller ID for retention. Even opt-in restriction checks are account-only
and fail open when account lookup or an explicit deny is absent. Focused SDK,
API, resolver, public-tree, and catalog coverage proves public writes are
rejected, private dialing/authentication configuration is redacted, and existing
DISA data and descendants are preserved. Mandatory secret-safe authentication,
persistent lockout/rate limiting, fail-closed destination policy, spend controls,
redacted immutable audit, and live toll-fraud tests are required before a guided
form is safe.
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
StepSwitch uses the originating endpoint's denied-classification snapshot only
when reclassifying a corrected short dial, not for an ordinary replacement
`to_did`; resource classifiers choose carriers rather than authorize callers.
`offnet` forcibly selects system-wide resources. `resources` defaults to the
current account's local pool and permits a raw `hunt_account_id`; StepSwitch
checks account hierarchy, but raw account identifiers cannot cross GridPBX's
public boundary, and a hunt account forces outbound handling even for a number
owned inside Kazoo. Installed StepSwitch also bypasses emergency caller-ID
validation whenever a hunt account is present, while global routing defaults to
continuing with an anonymous caller ID when invalid emergency CID denial is not
explicitly enabled. The action timeout controls bridge answer time, while its
event wait repeats without a module-level total deadline. The generic route
token bucket is not a final-destination, spend, or concurrency policy. Focused
SDK, API, resolver, public-tree, and catalog coverage proves both modules reject
public writes, redact private routing data and raw account IDs, and preserve
existing private data and descendants. Enabling either action requires an authenticated outbound-
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
not issue a live callback. Focused SDK, API, resolver, public-tree, and catalog
coverage proves that public writes are rejected, no URI, custom data, retries,
raw identifier, or payload is exposed, and existing private configuration,
unknown fields, continuations, and descendants remain locked, read-only, and
losslessly preserved. A guided form requires server-owned HTTPS allowlists,
per-hop DNS/IP enforcement, verified TLS, bounded request/response handling,
signed minimal public-safe payloads with replay protection, redacted retention-
bounded delivery records, safe retry/rate/circuit policy, audit events, and a
kill switch.
The broader Webhook resource API is now audited separately from that callflow
action. GridPBX exposes only a read-only event-catalog count and account-level
configured/enabled counts through System Status. The live deployment reported
nine available events and no configured hooks for the selected account. Raw
hook documents, URLs, names, custom data, modifiers, descendant/internal-leg
controls, samples, and attempts remain private. CRUD, enable/disable, bulk
re-enable, event selection, and delivery history remain capability-gated
because those operations use the same unsigned outbound runtime and Crossbar's
attempt response retains raw URI, header, body, hook-ID, and error material.
No hook mutation or callback was issued during this read-only audit.
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
uses the installed false default. Focused SDK, API, resolver, public-tree, and
catalog coverage proves that public writes are rejected; arbitrary caller-ID,
raw list ID, prompt, digit/regex, restriction-bypass, and custom-route fields are
not exposed; and existing private data, unknown fields, continuations, and
descendants remain locked, read-only, and losslessly preserved. No live call
was originated because ownership cannot be guaranteed by the installed path.
Enabling a safe variant requires only account-scoped public Phone Number UUIDs
or projected caller-ID profiles, server-side ownership and E911 validation,
authenticated feature-code context, fail-closed final-destination restrictions,
anti-spoofing audit and rate limits, and live carrier-level verification.

## 6. P2 operational features

| Domain                             | User-facing capabilities                                                                                                                                                                                                          | Switch boundary                                                                        | Projection notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Status      |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------- |
| Advanced callflows                 | Node canvas, categorized action palette, recursive branches, module forms, validation, version-safe updates, and dependency view                                                                                                  | Callflows and referenced resources                                                     | Interactive recursive canvas, safe node inspector, Kazoo-grouped version-aware palette, guided root/fallback/Menu/Rule Set/Branch Bnumber writes, palette drag/drop, guarded subtree moves/reorders, public condition branches, resource forms, and bounded inline action forms delivered. All installed default-palette actions are guided or explicitly capability-gated, and the installed registry plus a redacted active-account inventory found no additional default-palette keyed branch contract. Future schema/search-only modules and unknown branch shapes remain read-only until separately audited                                                   | Foundation  |
| IVR menus                          | CRUD, prompts, retries, timeout, key destinations                                                                                                                                                                                 | Menus, media, callflows                                                                | Complete installed form, Device-style outer Basic/Advanced disclosure with Monster-aligned inner Basic/Extension Dialing/Options tabs, exact validation-error routing, runtime-correct result-prompt suppression, write-only PIN replacement/removal, public Media UUID mapping, safe unresolved/unknown-field preservation, disposable synchronized lifecycle, dependency-safe delete, guided routing, and root or nested DTMF/timeout/continuation branches delivered; unknown and unsafe branch shapes remain preserved read-only                                                                                                                               | Foundation  |
| Time-of-day                        | Rules, holidays, rule sets, enable/disable/reset                                                                                                                                                                                  | Temporal rules and rule sets                                                           | Complete installed Rule fields, ordered public-UUID Rule Set membership, Monster/schema-aligned Basic-only compact forms, recursive PATCH preservation, explicit nullable-field clearing, projection/sync, safe deletion, effective status and audited controls, plus schema-correct `rule_set`/`_` guided routing delivered. Focused checks and a disposable Rule/Rule Set create/edit/reopen/control/delete lifecycle passed                                                                                                                                                                                                                                     | Foundation  |
| Media and music on hold            | Upload, stream, rename, delete, assignment                                                                                                                                                                                        | Media and account settings                                                             | Validated shared Basic/Advanced upload form, protected range streaming, dependency-safe deletion, non-clipping account-default choice, hidden/unknown safe-field preservation, and metadata-only MySQL projection delivered. A disposable live upload/edit/delete lifecycle verified public/raw identity separation, nested unknown-field preservation, and zero active MySQL or Switch leftovers                                                                                                                                                                                                                                                                  | Foundation  |
| Directories                        | CRUD and user membership                                                                                                                                                                                                          | Directories and users                                                                  | Monster-aligned shared Basic/Advanced tabs, Directory membership projection with public Extension UUID input, private User/Callflow mapping, schema-correct empty-object clearing, external-flag retention, safe unknown-field preservation across installed full-update behavior, guarded delete confirmation, and a disposable create/edit/reopen/final-member-clear/delete lifecycle with independent zero-active cleanup evidence                                                                                                                                                                                                                                            | Foundation  |
| Groups and ring groups             | CRUD, membership, endpoints, ring strategy                                                                                                                                                                                        | Groups, users, devices, callflows                                                      | Public-UUID Group/member relationships, private Switch-ID resolution, external-flag retention, safe unknown Group-field preservation, live create/edit/clear/delete Group verification, and Kazoo-aligned ordered Extension/Device/Group Ring Group members are delivered. Dynamic expanded-device fan-out is documented as Switch runtime behavior rather than falsely bounded by the 20 configured-member UI limit                                                                                                                                                                                                                                                     | Foundation  |
| Conferences                        | CRUD, role numbers, write-only multi-PIN replacement/removal, participant behavior, advanced named profile references, runtime summary, and room operations                                                                        | Conferences and callflows                                                              | Normalized role-number projection, public Extension UUID/private owner-reference mapping, redacted source snapshot, Device-style outer Basic/Advanced disclosure plus Monster's inner Basic/Options/Conference Server workflow, native recursive PATCH preservation for unknown/opaque fields and unchanged PINs, dependency-safe deletion, guided routing, and right-side panel delivered. Focused and disposable live create/edit/reopen/delete evidence passed. Active-room lock/unlock uses a fresh Switch runtime preflight, accepted/failed audit records, safe asynchronous response, and post-command reconciliation. The live-room panel supports single-participant mute/unmute, deaf/undeaf, kick, and confirmed bounded media playback through account/Conference-bound short-lived encrypted participant handles. Native room-wide mute/unmute and deaf/undeaf require an eligible-member preview, explicit confirmation, locked live-state revalidation, safe aggregate audits, atomic Kazoo submission, and four bounded post-command live observations; the UI distinguishes fully observed, partially/pending, and changed-room status. A reusable five-second visibility-aware poller keeps the open room current while pausing in hidden tabs and during commands/playback. Moderators are excluded and bulk kick remains disabled. Playback accepts only projected account-owned streamable `audio/*` Media UUIDs, requires confirmation in strict Zod and Laravel request contracts, resolves raw identifiers server-side, revalidates current room membership, records safe audit metadata, and treats Kazoo 202 only as acceptance; raw URLs and identifiers are never public or persisted. Focused and isolated headless command coverage passed, while audible live-room playback remains externally blocked without a media server. Dial-out remains disabled because Kazoo can originate billable raw-number/SIP legs and needs destination, caller-ID, limit, confirmation, idempotency, and reconciliation policy                                                                                                                     | Foundation  |
| Fax boxes                          | CRUD, owner assignment, inbound/outbound message metadata, protected document access, and guided callflow destinations                                                                                                            | Fax boxes, faxes, users, and callflows                                                 | Normalized fax-box/message projections, redacted `switch_json`, bounded import window, dependency-safe deletion, Monster-aligned shared Basic/Advanced presentation, right-side panels, audited document streaming, and a strict five-operation capability matrix delivered. Send, Forward, Resubmit, message deletion, and document deletion remain disabled after installed-runtime/security and live read-only audit                                                                                                                                                                                                                                            | Foundation  |
| Blacklists                         | CRUD, number entries, anonymous-caller policy, and account activation                                                                                                                                                             | Blacklists and account settings                                                        | Normalized entries, redacted source snapshot, safe activation/deactivation, sync, and right-side UI panel delivered                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | Foundation  |
| Caller-ID Lists                    | Reusable number/pattern lists for conditional routing                                                                                                                                                                             | Lists, list entries, and `cidlistmatch` callflows                                      | Account-scoped list and entry projection, separate redacted `switch_json`, queued sync, public UUID selector, private Switch-ID resolution, compensated API mutations, and schema-aligned shared Basic/Advanced slide-over delivered. Basic contains name and match entries; Advanced contains optional description/organization metadata. Focused component, type, and isolated non-mutating browser checks pass. Authenticated create, edit, authoritative reopen, entry clear, and delete were previously verified against the local Switch; the deployment must autoload `cb_lists`                                                                            | Foundation  |
| Phone numbers                      | Inventory, routing assignment, CNAM, E911, porting, purchasing, reservation, and release                                                                                                                                          | Phone numbers, number manager, callflows, port requests, and carrier providers         | Safe inventory/detail projection, runtime feature-availability matrix, Port Request and carrier-info probes, and CNAM/E911 schema/provider/runtime audits delivered. The live account has no numbers, inherits notification-only CNAM, and inherits uncredentialed Dash E911 with emergency-CID validation disabled; search inventory, carrier metadata, quotes, request data/documents, billable actions, and regulated mutations remain disabled until provider completion, verified transport, billing, retention/privacy, emergency-routing compliance, authority, duplicate-safe recovery, dependency, compensation, and confirmation policies are configured | Foundation  |
| Feature codes                      | View active star-code callflows for DND, hotdesk, voicemail, and related actions; safe lifecycle management remains gated                                                                                                         | Callflows                                                                              | Dedicated account-scoped inventory reuses the normalized Callflow projection and exposes only a public Callflow UUID, code, action/module, active state, dependency summary, and projection freshness. Enable, disable, and renumber remain unavailable because installed Monster performs whole-document create/update/delete, its registry contains schema-stale and unaudited actions, and Kazoo has no atomic feature-code mutation endpoint                                                                                                                                                                                                                   | Foundation  |
| Account voice settings             | Caller ID, timezone, language, music on hold, restrictions, recording defaults, dial plans, request formatters, preflow, metaflow activation/actions, and supported account defaults                                              | Accounts, media, phone-number classifiers, callflows, devices, extensions, and configs | Shared Basic/Advanced presentation groups ordinary identity/calling defaults separately from policy, recording, and routing automation. Typed virtual settings from redacted `switch_json`, exact installed bounds, nullable inherited privacy, safe regex rules, recursive Account-PATCH plus locked-tree/unknown-option preservation, public UUID references, unresolved-reference controls, and protected storage URL preservation remain delivered. Focused component/type/browser evidence passed without a live Account mutation                                                                                                                             | Foundation  |
| Call history                       | Search, direction/date/duration/outcome/cause filters, interaction detail                                                                                                                                                         | CDRs                                                                                   | Bounded, indexed CDR projection                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | Foundation  |
| Recordings                         | Search, metadata, authorized playback/download                                                                                                                                                                                    | Recordings and storage                                                                 | Bounded metadata-only projection, audited protected playback/download, and no GridPBX deletion until retention/provider cleanup is approved                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Foundation  |
| Active channels                    | Current calls and account activity                                                                                                                                                                                                | Channels                                                                               | Short-lived cache, not durable projection                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Conditional |
| Services and billing visibility    | Assigned plans, account/cascade/manual quantities, standing, billing cycle, current limits, aggregate billing impact, ledger-source usage, ledger total, recent Switch transactions, reconciliation checks, and safe sync history | Services summary, limits, ledgers, ledger total, and transactions                      | Administrator-only normalized read projection, explicit version-aware endpoint availability, immutable transaction retention, payment/bookkeeper redaction, queued sync, stored-versus-active count checks, billing-owner dependency checks, sanitized failure categories, recovery guidance, and right-side detail panel delivered; plan/limit/top-up/credit/debit/sale/refund/charge mutations remain disabled                                                                                                                                                                                                                                                   | Foundation  |
| Line keys and provisioning preview | Device combo/feature key inventory, safe full-replacement preview, and capability-gated apply                                                                                                                                     | Device `provision.combo_keys` / `feature_keys` PATCH                                   | Device-owned normalized rows plus the redacted device snapshot; the dedicated single-view panel represents Monster's Device-level Combo/Feature Key workflow without duplicating an empty outer tab, and no SIP credentials, provisioning URLs, templates, or generated documents are exposed                                                                                                                                                                                                                                                                                                                                                                      | Foundation  |

The 2026-08-31 Services and Billing presentation audit keeps their detail
panels intentionally single-view and read-only. Installed Kazoo models plan
assignment/removal, overrides, manual quantities, top-up, quotes, ledger
credit/debit, and transaction sale/refund as separate authorized operations,
not Advanced fields. Monster also separates service-plan/item visibility,
billing settings, and transaction history. Safe invoice/receipt PDFs remain
document operations, and the default-off hosted-tokenized sandbox verifier
remains a separate command workspace. Focused UI checks prevent artificial
Basic/Advanced tabs and financial mutation buttons from appearing in the
read-only Service and Billing-record panels.

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

The 2026-08-31 high-level status reconciliation passed four focused Laravel
tests / 24 assertions, two Vue files / four tests, and one isolated
non-mutating headless Playwright workflow covering validated filters, safe
public-UUID CDR/Recording navigation, and the absence of edit/delete controls.

The 2026-08-31 presentation audit classifies Call History and Recording detail
as intentionally single-view read-only surfaces. Their list filters—including
the “Advanced filters” disclosure—do not represent editable entity fields.
Installed CDR routes are GET-only; Recording DELETE remains a separate gated
retention/storage command. Monster's Call Logs workflow likewise provides
filters, detail, legs, and export rather than a Basic/Advanced editor. Focused
browser coverage prevents artificial Basic/Advanced tabs and edit/delete
actions from appearing on either detail panel.

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

| Domain                         | Candidate capabilities                                                                  | Status/constraint                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| ------------------------------ | --------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Queues and agents              | Queue CRUD, membership, agent state, call statistics                                    | Foundation for CRUD, roster, live status, sync, and guided routing. The complete installed Queue schema/runtime drift audit is current: full updates now authoritatively preserve safe unknown public metadata, hidden URL fields, the runtime-only call recording URL, and create-only priority without exposing raw roster/resource IDs. The schema/runtime recording-key mismatch and outbound URL/SSRF policy keep URL fields hidden. The operator form uses the shared Device-style Basic/Advanced control, grouped from installed schema/runtime semantics because this Monster checkout has no Queue editor; client/API errors route to the owning tab, and focused component, type, and isolated browser checks pass. Read-only account probes distinguish configuration, live controls, Agent statistics, and Queue statistics; the installed deployment reports true, false, false, and false. Where ACDc live controls are available, the Agent panel uses visibility-aware, non-overlapping five-second status refresh and separates accepted commands from observed state. Independent privacy-minimized server paths expose Queue metrics under public Queue UUIDs and aggregate Agent call-performance under public Agent UUIDs; their UI panels refresh every 15 seconds only while visible and retain the last good snapshot on failure. Raw call, caller, Agent, Queue, and per-Queue Agent-breakdown identifiers never cross the API. The local live feeds remain capability-gated and are not claimed as verified |
| Presence and parked calls      | Presence status, parked-call visibility and actions                                     | Read-only foundation delivered: subscription-diagnostic capability plus aggregate parked-call count. Live presence state, presence commands, slot detail, and park/retrieve actions remain capability-gated                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| Webhooks                       | CRUD, event selection, delivery health, secret-safe configuration                       | Read-only capability foundation delivered: installed-event and configured/enabled counts only. CRUD, event controls, raw attempts, and delivery health remain capability-gated pending hardened signed egress, redaction, authorization, audit, and kill-switch controls                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| SMS/MMS                        | Message threads, send/receive, number capability                                        | Read-only System Status foundation reports SMS/MMS inventory-endpoint availability without message data. Live deployment reports both unavailable; content and sending remain gated by carrier entitlement, number capability, consent, retention, attachment policy, delivery events, billing, and abuse controls                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Number porting                 | Port requests, documents, status workflow                                               | Read-only System Status foundation reports only filtered collection availability. Live deployment reports available with zero requests; details, documents, comments, LOA generation, transitions, carrier automation, and completion remain gated by sensitive-data handling, retention, malware controls, port-authority authorization, external-egress hardening, billing, confirmation, audit, and reconciliation                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Number acquisition and release | Carrier discovery, number search, purchase, reservation, and release                    | Read-only System Status foundation reports only validated carrier-info endpoint-shape availability. Live deployment falls back to local, non-billable inventory and reports the endpoint available. Search results, carrier/module names, creation states, quotes, charges, purchase, reservation, and release remain gated by provider policy, short-lived selection integrity, authorization, confirmation, idempotency, dependency cleanup, compensation, audit, and reconciliation                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| CNAM                           | Outbound display name and inbound lookup                                                | Installed schema/runtime and Monster workflow audited. The live account has no provider override, inherits `knm_cnam_notifier`, and contains no numbers. Selectability remains visible per number, but writes are gated because the notifier publishes asynchronously without carrier completion status and Crossbar may require a quote/charge-accepting retry. Enable only with typed recursive `PATCH`, provider acknowledgement, billing confirmation, authorization, immutable audit, duplicate-safe recovery, and authoritative reconciliation                                                                                                                                                                                                                                                                                                                                                                                                 |
| E911                           | Emergency service address, caller name, notifications, provisioning status, and removal | Installed schema, Dash/Telnyx/Vitelity runtime, emergency-routing dependency, and Monster workflow audited. The live deployment inherits uncredentialed `knm_dash_e911`, leaves `ensure_valid_emergency_cid` disabled, and has no numbers. Writes remain gated pending a configured provider, verified TLS/bounded timeouts, account-locked emergency-CID dependency checks, server-owned geocode choices, address/privacy and notification policy, billing confirmation, exact-number removal confirmation, immutable audit, provider-specific compensation, and authoritative reconciliation                                                                                                                                                                                                                                                                                                                                                       |
| Fax message operations         | Send, Forward, Resubmit, message deletion, and document deletion                        | Installed schema/runtime and Monster history workflows audited. Send can fetch operator-directed HTTP GET/POST content without a proven SSRF boundary or accept broad upload types, then returns 202 before background conversion/storage completes. Forward/Resubmit create random new jobs and are not retry-safe; message and attachment deletion are separate permanent commands. Live Fax Box/inbox/outbox collections are empty and available, but outgoing jobs return HTTP 503. All five operations are explicit public policy gates pending content/malware controls, hardened egress, authorization, destination confirmation, retention/legal hold, billing, rate limits, idempotency, immutable audit, and reconciliation                                                                                                                                                                                                                |
| Connectivity/trunks            | Resources, gateways, limits, routing and failover                                       | Installed schemas combine SIP credentials, servers/routes, arbitrary SIP headers and invite parameters, codecs/media, classifiers, flags, emergency routing, weighting, purchased limits/credits, restrictions, DIDs, and SIP/E.164 failover. Monster's Trunks workflow directly updates potentially billable limits, while its carrier callflow action accepts a raw account ID. GridPBX exposes only a validated carrier-endpoint boolean and keeps Resource/Gateway/Trunk documents, carrier actions, and all mutations unavailable pending a dedicated administrator threat model, secret vaulting, public account/resource identities, final-destination authorization, emergency/failover policy, quotes/confirmation, immutable audit, rollback/reconciliation, and representative live tests |
| Account administration         | Create/update accounts, descendants, limits, service plans                              | Installed Account, Limits, and Services schemas plus Kazoo runtime and Monster workflows audited. Existing typed account settings/status updates and confirmed onboarding of an existing descendant remain the only writable slices. Account creation, move/delete, reseller promotion/demotion, limits, service-plan assignment/removal/overrides, manual quantities, Kazoo billing-side synchronization/reconciliation commands, top-up, and quote acceptance remain unavailable pending operation-specific reseller/platform authority, public-reference contracts, billing/charge confirmation, idempotency, compensation/recovery, immutable audit, and disposable live verification. Monster's account wizard is explicitly non-atomic: after Account creation it independently creates users, a no-match Callflow, app restrictions, limits, plans, and credit, and can leave a partially configured account when a later step fails |
| White-labeling                 | Brand, domain, logo, colors, email identity                                             | Installed Whitelabel and SSO-provider schemas, Crossbar runtime, and Monster's domain-consumption workflow audited. A deliberately separate GridPBX-local organization-logo slice is delivered: settings-authorized administrators upload PNG/JPEG/WebP files up to 2 MB and 2048×2048 pixels, Laravel decode/re-encodes them to a bounded PNG, stores them privately, serves them through authenticated account scope, audits replace/remove, and exposes only the organization public UUID plus availability/timestamp metadata. The selected organization logo is used in the sidebar with the GridPBX mark as fallback. This performs no Switch write and does not claim Kazoo Whitelabel parity. Switch company/domain metadata, public logo/icon/welcome attachments, DNS generation/testing, navigation and Porting links, display-only trunk prices, SSO providers, document deletion, tenant colors, and all Switch tenant-brand mutations remain unavailable pending their existing authority, ownership, egress, IAM, content, rollout, and reconciliation gates. Switch has no tenant color or outbound email-sender identity field, so those remain GridPBX deployment/notification concerns rather than invented Whitelabel fields |
| Provisioning templates         | Global/local templates, model capabilities, zero-touch provisioning                     | Installed Crossbar runtime and the current GridPBX catalog/Device/LineKey boundary audited. Kazoo stores global templates in the shared Provisioner database and local templates in the Account database, while the large arbitrary JSON template is a separate attachment saved through a non-atomic second operation. Global reads are broadly authenticated but mutations require system-administrator authority; local mutations follow account-tree authorization. The referenced `provisioner_templates` validation schema is absent from this installed checkout and generated documentation publishes no field contract, so GridPBX will not invent a generic template form. Existing bounded catalog discovery, model-capability validation, public-UUID line-key resolution, redacted Device projection, and explicitly disabled manufacturer enrollment remain the only delivered foundation. Template CRUD, images, inheritance/precedence, firmware, dependency-safe deletion, and zero-touch enrollment remain unavailable pending a typed public-UUID model, dedicated roles, credential isolation, hardened fixed-destination egress and secret-safe logging, verified/re-encoded assets, versioned atomic staging, affected-Device preview, canary rollout/rollback, and authoritative reconciliation |
| Notifications                  | Template and destination management                                                     | Installed Notification schema, Crossbar inheritance/mutation runtime, Teletype rendering and SMTP delivery/logging, failed-notification persistence, and Monster SDK surface audited. GridPBX has no Notification Template entity, route, projection, or form; existing Fax, Voicemail, and Device notification controls remain resource-specific settings. System templates, per-account/ancestor overrides, HTML/plain attachments, dynamic `original`/`admins` recipients, specified addresses, previews, customer-update sending, SMTP logs, and bulk reset/force operations remain unavailable. Enable only after dedicated platform/reseller/account roles, public identities, verified sender domains, destination policy, a strict macro allowlist, safe structured or sanitized HTML authoring, inheritance/effective-source preview, multi-step versioning and rollback, non-delivering render preview plus explicitly confirmed test delivery, sensitive-log retention/redaction, encrypted retry payloads, idempotency/deduplication, hardened attachment egress, mandatory secure SMTP transport, immutable audit, and authoritative delivery reconciliation are approved |
| Security controls              | Access lists, IP authentication, MFA, token restrictions                                | Installed Access List, ACL, IP-authentication, auth-module, MFA/Duo, token-restriction, login-attempt, and password-recovery schemas/runtime plus Monster's opt-in Access List and legacy Duo workflows audited. These are separate trust boundaries: Frontier SIP ACLs can lock out or bypass endpoint authentication; source-IP auth can mint a user token without credentials; MFA provider documents contain secrets; token restrictions authorize API paths; and recovery can become an authentication bypass. GridPBX currently uses its own Laravel/Sanctum session and a private server-to-server Switch token provider; it has no public Security Controls domain. All Switch security mutations remain unavailable pending an approved proxy/client-IP contract, strict CIDR and safe user-agent validation, uniqueness and expiry of IP grants, public Account/User/Device UUID mapping, dedicated least-privilege and step-up roles, secret vaulting/rotation, modern provider integration, enrollment and break-glass recovery, deny-by-default token policy with simulation, actor-bound session inventory and revocation, enumeration-resistant single-use recovery, minimized/redacted attempt logs, lockout preview/canary/rollback, immutable audit, and independent authoritative reconciliation. Raw IDs, API keys, provider secrets, auth tokens, reset IDs, full headers, and private ACL documents must never reach projections or the browser |

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

The first provider boundary is delivered without enabling financial mutations:
an administrator-only capability response, a production-refusing Authorize.Net
sandbox merchant diagnostic, and provider-neutral attempt/event/profile tables
with public UUIDs, HMAC idempotency data, encrypted provider references, and
append-only safe events. Live read-only sandbox authentication and public-client-
key matching are verified. Default-off, independently gated sandbox charge,
void, refund, and profile paths now enforce hosted tokenization, explicit
confirmation, bounded amounts, rate limits, public source-attempt UUIDs, local
source-balance reservation, and private provider references. All payment and
operation flags remain false in the live environment; one isolated browser run
confirmed no provider script and zero payment mutations. One separately
authorized `$1.00` hosted-tokenized sandbox charge has also completed
successfully. Tenant-safe attempt history and typed-confirmation controls for
void, bounded partial refund, and customer-profile creation are implemented and
provider-mocked, but those operations have not run against the live sandbox.
A default-off signed-webhook intake and queued reconciliation boundary is also
implemented: exact-body HMAC-SHA512 verification, payload-size limits,
notification deduplication, encrypted provider references, no raw payload
persistence, authoritative `getTransactionDetails` confirmation, safe state
transitions, and bounded retries. Account-scoped delivery health, sanitized
recovery guidance, and an administrator-only, rate-limited retry control are
also delivered. Recovery is limited to failed sandbox reconciliation, refuses
unavailable provider verification, and stops at ten processing attempts.
Each manual recovery request creates an immutable safe audit event without
persisting the raw requester address. Account administrators can also expand a
recent payment attempt into a tenant-scoped immutable event timeline containing
only public UUIDs, normalized safe categories, fixed summaries, and timestamps;
stored JSON context and provider/security metadata are not exposed.
Saved customer-payment profiles now have a bounded, deterministically ordered,
administrator-only account inventory that persists across UI reloads. It exposes
only public UUIDs, normalized status, masked account metadata, provider name,
and timestamps; encrypted provider identifiers, keyed hashes, source/internal
keys, and gateway data remain private. This read path neither contacts the
provider nor changes any payment mutation flag.
Billing-document readiness is also explicit: Switch invoice counts and billing
transactions are not treated as invoice documents, and local payment attempts
are not represented as provider-issued receipts. The read-only Services view
marks both authoritative sources unconfigured and exposes only bounded,
tenant-scoped successful charge/refund confirmations with public IDs and safe
financial metadata. Actual invoices or receipts remain blocked until the client
selects and validates their authoritative source and document contract.
Separate invoice and receipt gateway contracts are now registered with
fail-closed unavailable adapters. Unconfigured or unsupported provider settings
return safe source status and never attempt document access. Legacy custom
invoice tables are reference evidence only and are not copied into the new
system without confirmation that they remain the client's accounting authority.
An optional legacy-MySQL invoice-summary adapter is implemented behind explicit
enabled, authority-confirmed, and read-only-credentials-confirmed gates. It uses
server-only account mapping, bounded tenant queries, decimal-safe totals, and
keyed public UUIDs. Currency and binary invoice downloads remain unavailable,
and legacy receipts remain disabled until their status/document semantics are
verified.
The optional adapter is additionally protected by a runtime read-only safety
diagnostic and the operator command `billing:legacy-invoices:verify`. The check
does not connect while any confirmation gate or connection setting is missing.
Once fully configured, it permits only `SELECT`, `SHOW VIEW`, and `USAGE`
database grants and validates the minimal legacy invoice schema through
read-only metadata operations. Failures expose fixed status categories and
guidance only; connection details, SQL errors, schema names, values, and legacy
identifiers remain private. Invoice reads stay unavailable unless every check
passes.
An account-scoped `/billing` workspace now consumes that same read model and
permission boundary. It separates invoice-source status, invoice summaries,
payment confirmations, reconciliation health, and Switch transaction activity
from service quantity management, with safe read-only record slide-overs. It
adds no production charge, plan, invoice, receipt, or reseller mutation and
does not expose the operator-only database diagnostic. The existing separately
gated Authorize.Net sandbox verification component is now hosted here instead
of Services; none of its mutation flags or safety policies changed. Services
retains only a compact billing-impact/status summary and a link to this
workspace.

The 2026-08-31 high-level billing/reseller reconciliation passed 22 focused
Laravel tests / 143 assertions, three Vue files / 10 tests, and two isolated
non-mutating headless Playwright workflows covering the account-scoped billing
workspace and reseller boundary without financial, hierarchy, or role controls.

The application shell now groups Cloud Phone System navigation into
People & Endpoints, Numbers & Routing, Call Applications, and Activity. Only
one group is expanded at a time, the active route reopens its owning group,
collapsed mode retains labelled icon controls, and mobile route selection
closes the overlay. Business and Workspace remain direct-link sections so
billing, services, account, reseller, system-status, and settings destinations
stay immediately available. The main-header account control now follows the
useful part of Monster's Account Jump workflow: a `Browsing` summary, focused
search field, safe account context, keyboard navigation, disabled-account
handling, and responsive access. Monster first filters loaded rows and can then
call Kazoo `search/multi` by name, realm, and raw account ID before
masquerading. GridPBX deliberately does neither remote search nor
masquerading: it filters only the authenticated user's already-loaded projected
accounts by name, realm, or organization and persists only the selected public
Account UUID. Two focused Vue files / five tests, Vue and isolated-E2E
typechecks, focused lint, and two isolated desktop/mobile headless Playwright
checks cover search, autofocus, selection, disabled state, responsive access,
and identifier redaction. The Settings destination is no longer a
placeholder: it presents application identity and account-scoped
role/capability context, reuses the public-UUID account switcher and shared
theme controls, persists the compact-sidebar preference in the current browser,
and links to the owning Account, System Status, and authorized Reseller
workspaces. Its only identity mutation is a self-service display-name update
through an authenticated, validated, six-per-minute, account-neutral audited
Laravel endpoint. The endpoint returns the User public UUID, accepts no email or
internal-key mutation, and never writes to Switch. Login-email changes,
verification, password changes, MFA, and session controls remain gated pending
dedicated security contracts. Eight focused Laravel tests / 41 assertions, five
Vue files / 16 tests, Vue and isolated-E2E typechecks, focused lint, and one
isolated desktop/mobile headless Playwright scenario verify the boundary. The
browser scenario intercepts and asserts the exact `{ name }` payload so it does
not alter the live administrator.

Organization branding is now a separate GridPBX-local Settings capability.
The organization row stores only a generated private logo path and update
timestamp; account resources reduce that to the organization public UUID,
`logo_available`, and `logo_updated_at`. The upload endpoint is scoped by a
public Account UUID, uses existing account-settings authorization, rejects SVG
and other non-raster formats, and decode/re-encodes bounded PNG/JPEG/WebP input
as PNG before private storage. Replacement/removal are audited and clean up the
previous asset. Authenticated reads return `image/png` with private/no-store
and no-sniff headers; neither storage paths, numeric keys, nor raw Switch IDs
reach the UI. The selected organization's logo is shown in desktop/mobile
sidebar branding with the GridPBX mark as fallback. Focused verification passed
four Laravel tests / 56 assertions, four Vue files / 15 tests, Vue and isolated
E2E typechecks, and one intercepted isolated headless Playwright lifecycle. The
browser test submits real multipart file data but intercepts upload/read/delete,
so it changes no live organization or Switch document.
Webhook enrollment/live delivery and every production provider request remain
disabled and must not be marked complete.

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
