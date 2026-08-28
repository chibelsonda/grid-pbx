# Switch Feature Implementation Roadmap

Status: Active delivery roadmap
Last updated: 2026-08-28

## 1. Purpose

This document defines the Switch-backed capabilities planned for GridPBX. It is
the product feature catalog that complements the architecture and delivery
rules in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

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

No Switch-backed product feature is currently marked complete pending target
Switch credentials and client acceptance. The People and Extensions list/search
workflow is at Foundation status: its MySQL projection, queued synchronization,
freshness state, account authorization, API, and Vue screen are implemented.

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
    `source_payload` after sensitive-field redaction; normalized columns remain
    the query and relationship contract.

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

## 6. P2 operational features

| Domain | User-facing capabilities | Switch boundary | Projection notes | Status |
| --- | --- | --- | --- | --- |
| Advanced callflows | Visual tree editor, validation, version-safe updates, dependency view | Callflows and referenced resources | Searchable summary plus safe source snapshot | Planned |
| IVR menus | CRUD, prompts, retries, timeout, key destinations | Menus, media, callflows | Menu options and relationships | Planned |
| Time-of-day | Rules, holidays, rule sets, enable/disable/reset | Temporal rules and rule sets | Effective schedule summary | Planned |
| Media and music on hold | Upload, stream, rename, delete, assignment | Media and account settings | Metadata only; binary streamed/stored externally | Planned |
| Directories | CRUD and user membership | Directories and users | Directory membership projection | Planned |
| Groups and ring groups | CRUD, membership, endpoints, ring strategy | Groups, users, devices, callflows | Group/member relationships | Planned |
| Conferences | CRUD, numbers, pins, members, basic controls | Conferences and callflows | Conference configuration; no PIN exposure | Planned |
| Fax boxes | CRUD, assignment, inbound/outbound message metadata | Fax boxes, faxes, users | Metadata; documents streamed on demand | Conditional |
| Blacklists | CRUD, number entries, callflow use | Blacklists and callflows | Entries and dependency summary | Planned |
| Feature codes | View and manage supported star-code callflows for DND, hotdesk, voicemail, and related actions | Callflows | Code, action, enabled state, and dependency summary | Planned |
| Account voice settings | Caller ID, timezone, language, music on hold, and supported account defaults | Accounts, media, configs | Safe effective-setting summary | Planned |
| Call history | Search, direction/date/duration filters, interaction detail | CDRs | Retained/indexed CDR projection | Planned |
| Recordings | Search, metadata, authorized playback/download | Recordings and storage | Metadata only by default | Planned |
| Active channels | Current calls and account activity | Channels | Short-lived cache, not durable projection | Conditional |

### CDR and recording constraints

Before enabling production CDR projection, define:

- Retention and archival periods
- Expected account volume and import window
- Required indexes and partitioning strategy
- Personally identifiable information controls
- Recording authorization and download audit rules
- Whether recordings stay in Switch storage or use an external object store

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

These capabilities require client prioritization and deployment discovery.

| Domain | Candidate capabilities | Status/constraint |
| --- | --- | --- |
| Queues and agents | Queue CRUD, membership, agent state, call statistics | Conditional; requires ACD/queue applications |
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
- Fax, SMS/MMS, queues, and number-porting requirements
- Carrier and emergency-services responsibilities
- Whether billing/CRM remains in GridPBX or integrates with another platform
- Required migration/parity threshold before Monster UI is retired

These decisions should update this roadmap before the affected feature enters
implementation.
