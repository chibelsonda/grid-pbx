# GridPBX Application Implementation Plan

Status: Active
Last updated: 2026-08-29

Implemented checkpoint:

- Laravel and Vue domain-oriented application structures
- Sanctum first-party SPA login and protected routing
- Organization-scoped Switch account selection
- MySQL projections for extensions, devices, voicemail, phone numbers, and call routing
- Queued, idempotent synchronization with per-resource run/checkpoint status
- Full Switch list/detail hydration with safe, redacted `switch_json` snapshots
- Public UUID API contracts with entity-named internal primary keys
- ArchitectUI-inspired Tailwind application shell, directories, and right-side
  CRUD/detail panels
- Safe callflow trees with public-UUID target resolution and a guided
  Switch-first root-destination editor that preserves unknown branches and
  same-module settings while locking unresolved or unsupported roots
- Optional wildcard fallback editing with public-UUID target resolution;
  nested, unsupported, and unresolved fallback subtrees remain read-only and
  are preserved losslessly
- Menu/IVR key routing for digits `0–9`, `*`, and `timeout`, using public UUID
  targets and explicit per-key writes; legacy `#`, unknown keys, and unsafe
  nested branches are preserved read-only
- Zod-validated Callflow fields with Headless UI selectors, inline API errors,
  and shared invalid-control styling
- Conflict-safe phone-number entry-point assignment within the routing editor
- Guided Switch-first callflow creation and dependency-aware deletion
- Shared Axios response-envelope unwrapping for clean domain API clients

## 1. Objective

Build a fresh GridPBX management application that replaces Monster UI with a
simpler, task-oriented interface while using the configured Switch Crossbar API
as the PBX source of truth.

The planned Switch capabilities, priorities, resource mappings, and delivery
checklist are maintained in
[SWITCH_FEATURE_ROADMAP.md](SWITCH_FEATURE_ROADMAP.md).
Its Switch coverage register is the authoritative completeness checklist for
the requested resources and package boundaries; inclusion in that register
does not bypass capability, security, retention, or acceptance requirements.
The public field-by-field contract, intentional exclusions, and implementation
order are maintained in
[SWITCH_SCHEMA_PARITY.md](SWITCH_SCHEMA_PARITY.md).

The application will preserve the legacy three-project boundary:

- `grid-api`: Laravel application API and application-owned data.
- `grid-api-switch`: framework-independent Switch Crossbar client package.
- `grid-ui`: Vue 3 and TypeScript single-page application.

The legacy projects under `/home/chicote/App/gridpbx-old` are reference
material only. New code must not depend on or modify those projects.

## 2. Product principles

1. Present user tasks instead of raw Switch resources wherever possible.
2. Keep all Switch credentials and tokens on the server.
3. Treat Switch as the source of truth for PBX configuration.
4. Use MySQL as the source of truth for GridPBX application data and as a
   synchronized, searchable read model of selected Switch resources.
5. Deliver vertical slices that are usable and testable end to end.
6. Use the separately maintained Monster UI environment only as a workflow
   reference until replacement functionality has been verified.
7. Make every account-scoped action explicitly authorized and auditable.

## 3. System architecture

```text
Browser
  |
  | Sanctum session cookie + CSRF
  v
grid-ui (Vue 3)
  |
  | /api/v1
  v
grid-api (Laravel)
  |-- MySQL: application data + normalized Switch read projections
  |-- Redis: sessions, cache, queues, Switch token cache
  |-- Workers: imports, incremental synchronization, reconciliation
  |
  v
grid-api-switch (Composer package)
  |
  | Switch API key authentication and X-Auth-Token
  v
External Switch Crossbar API configured by `SWITCH_BASE_URL`
  |
  `-- Events/webhooks where available + scheduled reconciliation
```

Switch and Monster UI are intentionally not part of this repository. A separate
local reference environment may remain available during development. The new
Vue development server uses port `5173`, and the Laravel API uses port `8081`.

## 4. Repository layout

```text
GridPBX/
|-- compose.yaml
|-- grid-api/
|-- grid-api-switch/
|-- grid-ui/
|-- config/
|-- docker/
|-- docs/
`-- scripts/
```

`grid-api-switch` is a library consumed by Laravel; it is not a separate HTTP
service. Its public API must use PSR-4 classes, typed DTOs, contracts, and
exceptions. Legacy global functions and file-based token storage are not
allowed.

## 5. Technology baseline

### API

- Laravel 13 and PHP 8.3+
- Laravel Sanctum using cookie-based SPA authentication
- MySQL 8.4 for application-owned data
- Redis for sessions, cache, queues, and distributed locks
- Laravel HTTP client for outbound Switch requests
- Pest or PHPUnit for unit and feature tests
- Laravel Pint and PHPStan for code quality
- OpenAPI 3.1 contract for `/api/v1`

### UI

- Vue 3 with Composition API and `<script setup>`
- TypeScript in strict mode
- Vite
- Vue Router and Pinia
- Tailwind CSS 4 through the official Vite plugin
- `@headlessui/vue` for every supported interactive primitive: dialogs and
  slide-overs, listboxes, menus, switches, tabs, and disclosures
- Shared Tailwind-styled adapters for a consistent ArchitectUI-inspired visual
  language; native semantic controls remain only where Headless UI has no Vue
  primitive, such as multi-select checkboxes and ordinary text/file inputs
- Vitest for component and composable tests
- Playwright for critical browser flows

## 6. Domain-driven architecture

Both applications use the same business vocabulary and bounded contexts, but
DDD is applied according to each application's responsibility. The goal is
clear domain ownership and dependency direction, not empty layers or a class
for every operation.

Initial bounded contexts:

- Identity and access
- Organizations and Switch accounts
- People and extensions
- Devices
- Phone numbers
- Call routing
- Voicemail and media
- Call history
- Switch synchronization and projections
- Auditing and administration

### API structure

Laravel uses a simple, domain-first DDD structure. Each bounded context owns
its complete Laravel feature slice directly instead of reproducing deep
`Application`, `Infrastructure`, and `Presentation` directory trees:

```text
app/Domains/{Context}/
|-- Controllers/     # Thin HTTP entry points
|-- Models/          # Context-owned Eloquent projections and relationships
|-- Requests/        # Endpoint authorization and input validation
|-- Resources/       # Stable API response mapping
|-- Services/        # Read and write use cases for the context
|-- Contracts/       # Ports for external dependencies, when required
|-- Gateways/        # Switch or other adapter implementations, when required
|-- Jobs/            # Context-owned asynchronous work, when required
`-- Enums/           # Context vocabulary and state values, when required
```

Only folders used by a context are created. A typical context contains
`Controllers`, `Models`, `Requests`, `Resources`, and `Services`, while Switch
synchronization also needs jobs, contracts, and gateways. Services own both
reads and writes; separate Action and Query layers are intentionally not used.
This keeps each domain complete and easy to navigate without adding empty
architectural layers.

Dependency rules:

- Controllers validate through context-owned requests, call one query or
  action, and return context-owned resources; controllers contain no business
  rules.
- Models express MySQL projection state and relationships, but raw Switch
  response shapes do not leak into controllers or the UI.
- Services coordinate behavior through contracts when external access is
  required. Gateways implement those contracts.
- Framework-independent value objects or policies can be added when actual
  domain complexity warrants them; an extra layer is not required by default.
- Cross-context work occurs through explicit application contracts or events,
  not by reaching into another context's internal models.
- `grid-api-switch` is the anti-corruption layer between GridPBX terminology
  and Switch Crossbar resources.

Every new API feature follows this domain-first convention. Shared framework
bootstrap remains under Laravel's normal `app`, `config`, and `routes`
locations; business-specific controllers, models, requests, resources, and
use cases remain in their owning domain.

### UI structure

Vue uses a domain-oriented frontend structure rather than reproducing backend
DDD classes in the browser:

```text
src/
|-- app/                    # Bootstrap, router, providers, and app shell
|-- domains/{context}/
|   |-- api/                # Laravel endpoint client and DTO mapping
|   |-- model/              # Types, validation, stores, and domain state
|   |-- composables/        # Domain use cases for the UI
|   |-- components/         # Context-owned presentation components
|   |-- pages/              # Route-level screens
|   `-- tests/
`-- shared/                 # Domain-neutral UI, utilities, and infrastructure
```

UI dependency rules:

- Pages and components do not call Axios directly; they use context APIs or
  composables.
- Laravel response DTOs are mapped at the API boundary instead of leaking
  transport shapes throughout components.
- Pinia stores are context-owned and hold shared client state, not arbitrary
  component state.
- A domain may expose a small public entry point; other domains must not import
  its internal files.
- `shared` remains business-neutral. PBX-specific behavior belongs to its
  owning domain even when more than one screen uses it.
- Shared terminology, validation rules, and user-visible workflows should
  match the corresponding API bounded context.

## 7. Switch data projection and synchronization

The client requires selected Switch data to be available in MySQL for fast
access, searching, dashboards, relationships, and reporting. This is a
reasonable architecture when the MySQL records are treated as projections,
not as an independent copy that can diverge from Switch.

### Data ownership

| Data category | Authoritative system | Examples |
| --- | --- | --- |
| PBX configuration | Switch | Extensions, devices, numbers, voicemail, callflows |
| GridPBX application data | MySQL | Users, roles, organizations, account mappings, preferences |
| Search/reporting projections | MySQL, derived from Switch | Extension directory, device summary, number assignments |
| Temporary operational state | Redis | Sessions, locks, queues, token cache |

MySQL projection rows must never be edited as a shortcut around Switch. PBX
mutations go through Laravel to Switch first. After Switch accepts a mutation,
Laravel updates or invalidates the affected projection and schedules a
reconciliation job. There is no distributed transaction between Switch and
MySQL, so recovery must be designed around idempotency and reconciliation.

### Read and write paths

Normal reads use the projection:

```text
Vue -> Laravel authorization -> MySQL projection -> API response
```

An explicitly requested refresh may synchronize the affected resource from
Switch before returning it. Screens must expose the last synchronization time
when freshness affects the user's decision.

PBX writes use Switch first:

```text
Vue
  -> Laravel validation and authorization
  -> Switch mutation
  -> projection upsert/invalidation
  -> audit record and reconciliation job
```

If Switch succeeds but the projection update fails, the API records the failure
and the reconciliation worker repairs MySQL. Retrying a synchronization job
must produce the same result and must not duplicate records.

### Projection design

Each owning bounded context defines its own normalized projection tables. A
typical projected record includes:

- An internal, entity-named primary key such as `extension_id` or `device_id`
- A separate unique UUID column named `id` for API routes and UI contracts
- Organization and Switch account mapping identifiers
- Switch resource identifier with a composite unique constraint per account
- Normalized fields required for filtering, sorting, joins, and display
- Source revision/version and source update time where Switch provides them
- `last_synced_at`, `sync_status`, and optional safe error metadata
- Soft deletion or a tombstone when a resource disappears from Switch
- Projection schema version
- Redacted `switch_json` sourced from the entity detail response's `data`
  property, including unmapped non-secret fields

Raw Switch documents should not be copied indiscriminately. API keys, Switch
tokens, SIP passwords, authentication hashes, and other credentials must not
be stored in projection payloads. High-volume records such as call-detail
records require explicit retention, indexing, pagination, and archival rules
before being projected at scale.

### Switch JSON snapshot contract

For each supported entity, synchronization first enumerates the account-level
collection and then fetches the entity detail endpoint. The complete detail
response `data` object becomes `switch_json` after a centralized,
recursive sensitive-field redaction pass. Redacted keys remain present with a
`[REDACTED]` marker so schema coverage is observable without retaining the
credential value.

The snapshot and the normalized projection serve different purposes:

- Normalized columns own application filtering, sorting, joins, and display.
- `switch_json` preserves non-secret fields that are not normalized yet and
  allows future projection changes to be rebuilt from the latest snapshot.
- Public API resources do not return `switch_json`; a future diagnostic
  endpoint would require explicit administrator authorization and auditing.
- PBX write requests use dedicated validated command payloads. A stored source
  snapshot is never sent wholesale back to Switch because it can include
  read-only, private, or unsupported fields.

Display/edit-only nested properties are exposed as typed application virtual
fields derived from `switch_json`; they do not require matching physical MySQL
columns. MySQL virtual generated columns are added only for scalar JSON paths
with a demonstrated database filtering, sorting, uniqueness, or indexing use
case. They are not created for every JSON key or for arbitrary nested maps and
arrays.

The initial typed snapshots cover users, devices, voicemail boxes, voicemail
message metadata, callflows, media, and phone numbers. Remaining Switch
entities will adopt the same list/detail, typed field, raw-data preservation,
and redaction contract as their vertical slices are implemented. Voicemail
audio is intentionally not a projection: Laravel authorizes each request and
streams it from Switch without persisting the binary in MySQL.

### Synchronization strategy

1. An initial account import populates projections in bounded batches.
2. Switch events or webhooks update individual resources where the deployment
   supports them.
3. Incremental polling covers resources without dependable change events.
4. Scheduled full reconciliation detects missed events, updates changed rows,
   and marks deleted resources.
5. Per-account checkpoints allow failed imports to resume safely.
6. Distributed locks prevent overlapping account/resource synchronization.
7. Each projection reports `healthy`, `syncing`, `stale`, or `error`, plus its
   last successful synchronization time.

The `SwitchSynchronization` bounded context coordinates jobs, checkpoints, and
health reporting. Resource interpretation and projection schemas remain owned
by their domains, such as Extensions, Devices, or Phone Numbers. The
`grid-api-switch` package remains the transport anti-corruption layer and does
not contain MySQL persistence logic.

## 8. UI direction

The visual direction is inspired by the ArchitectUI Vue Pro demo, but the
implementation must be original and built with Tailwind CSS. Do not copy the
commercial template's source code or proprietary assets.

### Shell

- Fixed 60px top header with a subtle multi-layer shadow.
- Fixed 280px desktop sidebar that can collapse to an 80px icon rail.
- White navigation surfaces over a light gray application background.
- Mobile sidebar becomes an overlay drawer.
- Content uses compact page headings, breadcrumbs, actions, and responsive
  card grids.

### Visual tokens

The starting palette is based on the recognizable visual language of the
reference demo:

| Purpose | Value |
| --- | --- |
| Primary | `#3f6ad8` |
| Info | `#16aaff` |
| Success | `#3ac47d` |
| Warning | `#f7b924` |
| Danger | `#d92550` |
| Dark text | `#343a40` |
| Muted text | `#6c757d` |
| Canvas | `#f1f4f6` |
| Border | `#e9ecef` |
| Surface | `#ffffff` |

Typography should be compact and highly readable. Cards use modest radii,
thin borders or low-opacity shadows, clear status accents, and restrained
motion. Dense tables are allowed on desktop but must transform into usable
mobile layouts.

### Navigation model

- Dashboard
- People & Extensions
- Devices
- Phone Numbers
- Call Routing
- Voicemail & Media
- Call History
- Settings
- Administration

### UX rules

- A PBX concept may be technically composed of several Switch resources, but
  the UI should expose a single guided workflow where that matches the task.
- Every mutation displays pending, success, and failure states.
- Destructive actions require explicit confirmation and identify the target.
- Empty states explain what the resource does and provide the next action.
- Forms keep advanced Switch fields collapsed unless the task needs them.
- Create and edit forms open in a reusable responsive panel that slides from
  the right. List and detail context remains visible behind the panel; Escape,
  the close button, and the backdrop close it safely when no mutation is in
  progress.
- Keyboard focus, color contrast, labels, and error summaries are required.
- Every mutation form uses a domain-owned Zod schema for immediate client-side
  feedback. Zod issues are normalized to the same dotted field-error shape as
  Laravel; Laravel request validation remains the authoritative trust boundary.
- Interactive behavior is centralized in shared Headless UI adapters rather
  than reimplemented inside a domain. Domain screens compose those adapters
  and keep their domain state, validation, and API orchestration local.

## 9. Authentication and authorization

1. The Vue SPA authenticates to Laravel using Sanctum session cookies and CSRF
   protection. Application tokens are not persisted in local storage.
2. Laravel authenticates to Switch using a server-side API key.
3. Switch tokens are cached with an expiry shorter than the server expiry and
   refreshed under a distributed lock.
4. A local user belongs to an organization and receives roles and permissions.
5. An organization is mapped to one or more Switch accounts.
6. Laravel policies verify both the permission and account mapping before any
   Switch call.
7. Mutations create audit records containing actor, account, resource, action,
   request correlation ID, outcome, and safe change metadata.

Initial roles:

- Platform administrator
- Reseller administrator
- Account administrator
- Account operator
- Read-only user

## 10. API conventions

- Base path: `/api/v1`
- JSON request and response bodies except file transfers
- Resource-oriented routes with account scope in the URL
- Consistent success, validation, error, and pagination envelopes
- API resources, routes, and UI state use only the public UUID column named
  `id`; internal primary and foreign keys are never serialized
- MySQL primary keys are named for their entity (`user_id`, `device_id`,
  `voicemail_box_id`, and so on). `switch_accounts` uses `account_id` because
  `switch_account_id` already stores the upstream Switch account identifier
- Internal ULID/bigint key types remain implementation details; public UUIDs
  are immutable, unique, and generated by Laravel when a record is created
- Switch identifiers remain opaque strings
- Correlation ID returned on every response
- Validation errors use stable field keys
- Upstream Switch errors are translated to stable application error codes
- Secrets and PBX credentials are redacted from logs and normal responses

Initial endpoints:

```text
GET    /api/v1/health
GET    /api/v1/session
POST   /login
POST   /logout
GET    /api/v1/accounts
GET    /api/v1/accounts/{account}/dashboard
GET    /api/v1/accounts/{account}/extensions
POST   /api/v1/accounts/{account}/extensions
GET    /api/v1/accounts/{account}/extension-recovery
POST   /api/v1/accounts/{account}/extension-recovery/{operation}
GET    /api/v1/accounts/{account}/devices
GET    /api/v1/accounts/{account}/phone-numbers
GET    /api/v1/accounts/{account}/voicemail-boxes
POST   /api/v1/accounts/{account}/voicemail-boxes
GET    /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}
PUT    /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}
DELETE /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}
GET    /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/messages
PATCH  /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/messages
PATCH  /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/messages/{message}
GET    /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/messages/{message}/audio
POST   /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting
GET    /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting/audio
DELETE /api/v1/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting
GET    /api/v1/accounts/{account}/callflows
GET    /api/v1/accounts/{account}/callflows/editor
POST   /api/v1/accounts/{account}/callflows
GET    /api/v1/accounts/{account}/callflows/{callflow}
GET    /api/v1/accounts/{account}/callflows/{callflow}/editor
PUT    /api/v1/accounts/{account}/callflows/{callflow}
DELETE /api/v1/accounts/{account}/callflows/{callflow}
GET    /api/v1/accounts/{account}/call-detail-records
GET    /api/v1/accounts/{account}/call-detail-records/{callDetailRecord}
POST   /api/v1/accounts/{account}/sync/call-detail-records
GET    /api/v1/accounts/{account}/sync/call-detail-records/{run}
```

## 11. Delivery phases

### Phase 0: Foundation

- Create this implementation plan and architecture decisions.
- Scaffold all three projects.
- Add MySQL, Redis, Laravel, and Vue services to Compose.
- Establish the API modules and UI domain boundaries before feature growth.
- Add health checks and developer commands.
- Establish formatting, static analysis, unit tests, and CI-ready scripts.

Acceptance criteria:

- The separately running Switch/Monster reference environment is not modified.
- New API and UI build and start through Docker Compose.
- Default test and static-check commands pass.

### Phase 1: Secure vertical slice

- Move the initial Laravel authentication/account code and Vue shell into the
  agreed domain module boundaries before adding PBX feature screens.
- Implement local users, organizations, roles, permissions, and account maps.
- Implement Sanctum SPA login/logout/session endpoints.
- Implement Switch API-key authentication and token caching.
- Implement synchronization jobs, checkpoints, projection health, and an
  initial full import for account and extension data.
- Add account list and Switch health boundary.
- Build login, application shell, account selector, dashboard, and a read-only
  extensions page.

Acceptance criteria:

- A seeded administrator can sign in without a token in browser storage.
- The user can only select mapped Switch accounts.
- The extensions page reads its MySQL projection through Laravel and reports
  its synchronization status and last successful refresh.
- Re-running the account/extension import is idempotent and repairs changed or
  deleted Switch resources.

### Phase 2: Core PBX management

- Extension workflow combining user, device, voicemail, and basic callflow.
- Manage the User hotdesk profile inside the Extension aggregate with a
  schema-aligned dial-pad ID, enabled/PIN/multi-device-login controls, and a
  write-only PIN that is redacted from `switch_json` and API responses.
- Manage optional Switch User portal credentials in the Extension aggregate.
  Require and confirm the write-only password only when creating or changing a
  username, omit it on unchanged edits, support `require_password_update`, and
  require explicit confirmation before removing login hashes.
- Managed extension update plus a dependency preview before any destructive
  operation. Confirmed deletion is an audited reverse-order saga with persisted
  step progress, exact-number confirmation, and safe retry after interruption.
- Persist safe lifecycle progress for create, update, and delete. Expose a
  manager-only right-side recovery queue that retries failed create cleanup,
  reconciles partial updates from Switch, and resumes partial deletions after
  exact-number confirmation. Keep upstream IDs and credentials internal.
- Implement multi-resource workflows using the aggregate, dependency,
  compensation, deletion, and projection rules in
  [SWITCH_ENTITY_RELATIONSHIPS.md](SWITCH_ENTITY_RELATIONSHIPS.md).
- Device CRUD and SIP credential handling.
- Voicemail CRUD.
- Phone number inventory and assignment.
- Basic call routing and callflow visualization.
- Call-detail record listing, detail, synchronization, and filtering. The
  foundation imports a configurable bounded window on demand, projects only
  approved normalized fields, and leaves production scheduling and retention
  deletion disabled until client policy is approved.
- Account projection workspace: authenticated organization scoping, public
  identity/status detail, tenant-safe resource counts, redacted full
  `switch_json`, and administrator-only typed refresh/update for the audited
  identity and calling-default subset. External/emergency caller IDs resolve
  from public Phone Number UUIDs with E911 enforcement, while enable/disable is
  a separate exact-name-confirmed audited command. Higher-risk operations stay
  gated.
- Add projections and incremental synchronization for each delivered resource
  domain.

### Phase 3: Supporting PBX modules

- Media and music on hold foundation: metadata projection, upload and protected
  range streaming, rename, audio replacement, account MOH assignment, and
  dependency-aware deletion. Binary content remains in Switch storage.
- Directory and group foundation: typed CRUD, queued projection rebuilds,
  normalized membership relationships, complete redacted `switch_json`, safe
  public references, dependency-aware deletion, and guided callflow targets.
  Inline `ring_group` timing/strategy canvas editing remains part of advanced
  visual callflow work.
- Queue and agent foundation: ACDc-aware typed queue CRUD, normalized roster
  projection, redacted `switch_json`, queued synchronization, compensating
  roster updates, live agent status commands, right-side panels, and guided
  `acdc_member` callflow targets. Agent identity reuses projected users rather
  than creating a duplicate durable identity; live status remains operational
  Switch state. Statistics remain a later capability-gated slice.
- Menu/IVR foundation: typed Menu CRUD, normalized prompt and behavior
  projection with full redacted `switch_json`, media relationship resolution,
  dependency-safe deletion, queued synchronization, guided call-routing
  integration, and Vue management through a right-side panel. Wildcard,
  digit, Star, and timeout Callflow branch editing is delivered with legacy
  `#` preservation; deeper recursive branch editing remains part of the visual
  callflow slice.
- Temporal routing foundation: typed Temporal Rule and Rule Set CRUD,
  normalized recurrence and ordered membership projections with redacted
  `switch_json`, Gregorian-date conversion, dependency-safe deletion, queued
  synchronization, guided `temporal_route` Rule Set destinations, and Vue
  Rules/Rule Sets management through right-side panels, timezone-aware
  effective-status evaluation, and audited force-active, force-inactive, and
  resume-schedule commands. Rule Set commands fan out to every resolved member
  rule under an account lock and compensate completed writes if a later member
  fails.
- Blacklist foundation: typed CRUD and account-assignment boundary, normalized
  blacklist and E.164 number-entry projections with complete redacted
  `switch_json`, queued reconciliation of configuration plus active state,
  compensating activation writes, active-delete protection, and Vue management
  through a right-side panel. Switch enforces these lists before callflow
  execution, so no synthetic blacklist callflow node is created.
- Recording foundation: bounded typed inventory, normalized metadata-only
  projection with complete redacted `switch_json`, extension/CDR relationship
  resolution, queued reconciliation, audited protected playback/download with
  byte ranges, and a Vue inventory plus right-side detail panel. Binary audio
  remains in Switch or its storage provider; deletion remains policy-gated.
- Callflow editor layout: the drag-and-drop node graph and action palette live
  on the main Callflow page. A right-side panel may edit the selected node's
  typed properties, but it must not contain or constrain the graph canvas.
- Conference foundation: typed CRUD, normalized general/member/moderator
  access-number rows, owner relationship, full redacted `switch_json`,
  write-only PIN replacement/removal, queued synchronization, last-observed
  runtime status, dependency-safe deletion, guided callflow destinations, and
  a Vue right-side CRUD panel. Live participant commands and dial/lock/play
  controls remain a later operational enhancement and are not persisted.
- Fax foundation: typed fax-box CRUD, normalized fax-box and bounded
  inbox/outbox message projections, owner/fax-box relationship resolution,
  complete redacted `switch_json`, queued reconciliation, dependency-safe
  fax-box deletion, guided callflow destinations, audited range-aware document
  streaming, and Vue inventory/detail/CRUD right-side panels. Document bytes
  remain in Switch or its storage provider. Outbound sending, forwarding,
  resubmission, and message deletion remain gated on retention, notification,
  and abuse-control policies.
- Services foundation: typed read-only summary and limits clients, normalized
  account summary, assigned-plan, quantity, and limit projections, complete
  redacted `switch_json` for source objects, administrator-only authorization,
  queued synchronization, and a Vue inventory plus right-side detail panel.
  Billing identifiers, payment tokens, and bookkeeper configuration are
  redacted. Plan assignment, limit changes, top-ups, manual quantities,
  invoices, and charge acceptance remain outside this foundation.
- LineKey/provisioning-preview foundation: entity-organized typed DTOs and a
  device PATCH client, normalized `switch_line_keys` owned by projected
  devices, endpoint brand/family/model metadata, credential-free preview API,
  audit logging, and a Vue inventory plus right-side editor. Preview is safe by
  default; upstream apply requires both a recognized device identity and the
  explicit `SWITCH_LINE_KEY_MUTATIONS_ENABLED=true` capability flag. Generated
  vendor templates, SIP credentials, and provisioning infrastructure are never
  returned to the UI.
- Advanced visual callflow editing: a Tailwind node canvas with connectors,
  categorized module palette, recursive linear and keyed branches,
  module-specific right-side forms, public-reference resolution, schema-aware
  validation, and lossless read-only preservation for unsupported nodes. The
  selectable recursive read-only canvas, safe public branch-label contract,
  selected-node inspector, and searchable 73-module schema reference palette
  are delivered; arbitrary node mutation and module-specific forms remain
  next.
- SMS/MMS with carrier, consent, retention, and abuse-control gates.
- Number purchasing, porting, releasing, CNAM, and E911 workflows after
  carrier and compliance approval.

### Phase 4: Business modules

- Reseller and client hierarchy.
- Branding and user preferences.
- Billing, payment methods, and invoices.
- Zero-touch phone provisioning.
- Trunks, carriers, resources, and connectivity administration.
- Webhooks and advanced account/security administration.

These modules require separate threat models and acceptance criteria before
implementation, especially payment handling.

### Phase 5: Migration and hardening

- Compare workflows against Monster UI and the legacy GridPBX application.
- Add rate limiting, idempotency, retries, circuit breaking, and observability.
- Validate projection rebuild, missed-event recovery, checkpoint restoration,
  retention, and disaster-recovery procedures.
- Complete browser, accessibility, security, backup, and recovery testing.
- Document production deployment and migration.
- Retire Monster UI only after agreed parity checks pass.

## 12. Test strategy

- `grid-api-switch`: isolated unit tests with fake HTTP responses plus opt-in
  integration tests against the local Switch API.
- `grid-api`: policy, validation, API contract, database, and service tests.
- Synchronization: mapping accuracy, idempotent replays, checkpoint recovery,
  stale-data reporting, tombstones, and full projection rebuilds.
- `grid-ui`: component and composable unit tests.
- End to end: login, account selection, extension creation, device assignment,
  number routing, and logout.
- Every bug fix adds a regression test at the lowest effective layer.

Tests that mutate Switch must use a dedicated test account and identify created
resources so cleanup is deterministic.

## 13. Definition of done

A feature is complete when:

1. Its acceptance behavior is documented.
2. Authorization and account isolation are enforced server-side.
3. Loading, empty, success, validation, and upstream-error states exist.
4. Automated tests cover the primary behavior and important failure modes.
5. Logs and responses do not expose credentials or tokens.
6. API documentation and user-facing labels are current.
7. The feature works responsively and passes keyboard navigation checks.
8. Projected Switch data has a tested import, reconciliation, deletion, and
   freshness path with no credential fields persisted.

## 14. Deferred decisions

- Final production hosting platform and domain layout.
- Whether external/mobile API consumers need personal access tokens.
- Payment gateway and compliance scope.
- Production telephony topology and FreeSWITCH integration.
- Data migration from the legacy application database.

Each deferred decision should receive an architecture decision record before
implementation changes the system boundary.
