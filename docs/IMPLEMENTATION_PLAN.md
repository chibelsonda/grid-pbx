# GridPBX Application Implementation Plan

Status: Active
Last updated: 2026-08-31

Implemented checkpoint:

- Laravel and Vue domain-oriented application structures
- Sanctum first-party SPA login and protected routing
- Organization-scoped Switch account selection
- MySQL projections for extensions, devices, voicemail, phone numbers, and call routing
- Queued, idempotent synchronization with per-resource run/checkpoint status
- Full Switch list/detail hydration with safe, redacted `switch_json` snapshots
- Public UUID API contracts with entity-named `BIGINT UNSIGNED AUTO_INCREMENT`
  internal primary keys and numeric foreign-key relationships
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
- Direct Temporal Rule callflow routing with ordered public Rule UUIDs and one
  typed match destination per rule; raw Switch branch identifiers remain
  server-side, removed rules are explicitly cleared, and unsafe nested branches
  remain locked and lossless
- Zod-validated Callflow fields with Headless UI selectors, inline API errors,
  and shared invalid-control styling
- Accessible shared `FormInput`, `FormTextarea`, `SearchInput`,
  `FormFileInput`, and `FormCheckbox` controls with consistent labels,
  descriptions, native attribute forwarding, model modifiers,
  `aria-describedby`, and field-local invalid styling. Entity mutation forms,
  list searches, advanced history filters, guided metaflow editors, uploads,
  confirmation dialogs, and selection groups use these purpose-specific
  adapters; raw native inputs remain encapsulated inside the shared controls
- Wide Callflow workspace with small responsive side gutters for the safe
  recursive route map. A Kazoo-aligned document entry card displays the primary
  number/pattern above the actual `flow` root; a compact draggable action
  palette can return to its right-side dock, and centralized semantic icons are
  shared across the diagram, palette, and node forms. The selected-node detail
  remains an information modal.
  Accessible subtree moves support empty public branches plus guarded
  insert-before and disjoint-subtree swap operations; typed mutation forms
  remain in right-side panels
- Palette-driven add forms for guided reference actions and selected-node
  target editing. These reuse the account-scoped destination catalog, Zod and
  API validation, public UUIDs, and lossless server-side node-data preservation
- Schema-backed side-panel forms for Sleep, Text to Speech, Collect/Send/Flush
  DTMF, Dead Air, Language, Record Call, Record Caller, Missed Call Alert, Set
  Caller ID, Prepend Caller ID, Set Alert Info, and regex-mode Check Caller ID.
  Only bounded public
  properties are accepted; alert extension UUIDs are translated server-side,
  CR/LF is rejected in Alert-Info, recording storage values remain
  server-owned, and unknown node data plus complete children are preserved
  losslessly
- Check Caller ID uses safe-regex validation, stable `match` and `nomatch`
  paths, virtual identity fields, and server-only translation of a public
  Extension UUID into Kazoo's nested caller identity payload. Absolute
  caller-number keys remain preserved and non-editable; Privacy and Caller-ID
  List Match remain capability-gated until their route-capture and
  projected-list dependencies are available
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

The application preserves a three-project boundary for clear ownership:

- `grid-api`: Laravel application API and application-owned data.
- `grid-api-switch`: framework-independent Switch Crossbar client package.
- `grid-ui`: Vue 3 and TypeScript single-page application.

The legacy projects under `/home/chicote/App/gridpbx-old` are migration evidence
only. New code must not depend on or modify those projects, and new behavior is
not derived from them when the current Switch API schema or Kazoo workflow
provides authoritative evidence.

### Implementation reference order

Every entity and workflow uses this evidence order:

1. The installed Switch/Kazoo JSON schemas define accepted payload fields,
   types, validation constraints, defaults, limits, and compatibility
   boundaries.
2. The installed Switch/Kazoo API and runtime implementation define actual
   request and response behavior, side effects, operational commands, branch
   semantics, and resource relationships.
3. Current Kazoo/Monster workflows inform Basic and Advanced field grouping,
   conditional visibility, relationship prompts, defaults, operator
   terminology, and expected interaction behavior.
4. Disposable live create, edit, clear, reopen, and synchronization checks
   against the connected Switch confirm the implemented contract.
5. The old GridPBX projects are consulted only for confirmed client-specific
   requirements or migration mappings absent from the preceding sources.

Features are not implemented from screenshots alone. Screenshots are useful
interaction evidence, but they do not establish payload types, API ownership,
runtime side effects, or version compatibility.

### Feature implementation authority

Before implementing an entity, relationship, callflow action, or operational
command, its audit must establish:

- The owning Switch resource, schema, endpoint, and runtime module.
- The installed schema version and its valid fields, types, defaults, limits,
  nested structures, and clear or unset behavior.
- The raw Switch request and response shape, including the response `data`
  projected into `switch_json`.
- The small subset of values that require indexed MySQL columns for searching,
  sorting, joining, uniqueness, reporting, or reconciliation. Other values
  remain virtual accessors over `switch_json` instead of becoming one database
  column per JSON key.
- Related resources that must be created, updated, removed, synchronized, or
  compensated as part of the operator workflow.
- Whether each operation is ordinary CRUD, a synchronization operation, or a
  separate operational command.
- Basic and Advanced UI placement, entity-type conditions, account
  capabilities, and Switch-version compatibility rules.
- The bounded public API fields, public UUID references, server-side mapping to
  raw Switch identifiers, and fields that must remain secret or internal.
- Unknown or unsupported Switch data that must be preserved losslessly during
  edits.
- Focused create, edit, clear, reopen, relationship, projection, and cleanup
  verification appropriate to the feature.

When the evidence sources disagree:

- The installed schema and observed runtime behavior take precedence for
  payload correctness and operational semantics.
- The current Kazoo/Monster workflow takes precedence for understanding the
  established operator workflow, including Basic and Advanced controls.
- GridPBX may simplify and modernize presentation, but it must not silently
  remove supported behavior or submit fields unsupported by the connected
  Switch.
- Deployment-specific differences use explicit capability-aware or
  version-aware controls.
- Every intentional behavioral or field-level difference is recorded in the
  schema parity audit with its reason.

A feature is complete only when all applicable layers are implemented:

1. `grid-api-switch` has typed DTOs, validated mapping, error handling, and
   preservation of unknown Switch properties.
2. `grid-api` has account-scoped authorization, requests, services, public-UUID
   translation, stable resources, relationship orchestration, and MySQL
   projection behavior.
3. `grid-ui` has typed API integration, reusable form controls and composables,
   Zod validation, field-local error behavior, and capability-aware Basic and
   Advanced controls.
4. Related-resource creation and failure compensation are handled where the
   workflow spans multiple Switch resources.
5. Focused automated tests pass for the layers changed.
6. Disposable live Switch create, edit, clear, reopen, synchronization, and
   cleanup verification passes when the required environment or provider is
   available.
7. The implementation plan, schema parity audit, form audit, and feature
   roadmap accurately distinguish implemented, live-verified,
   capability-gated, planned, and externally blocked behavior.

If live Switch access or an external provider contract is unavailable, the
feature is recorded as implemented but not live-verified, or externally
blocked. It must not be described as fully complete.

Reusable domain form components are the UI source of truth. A Device created
from People & Extensions uses the same Device Basic/Advanced editor, Zod schema,
capability matrix, and payload mapper as the standalone Device slide-over. The
relationship workflow presents that editor as a subview of the existing
slide-over, preserving the aggregate draft and avoiding stacked dialogs or
competing focus traps. It must not fork or duplicate Device fields.

## 2. Product principles

1. Present user tasks instead of raw Switch resources wherever possible.
2. Keep all Switch credentials and tokens on the server.
3. Treat Switch as the source of truth for PBX configuration.
4. Use MySQL as the source of truth for GridPBX application data and as a
   synchronized, searchable read model of selected Switch resources.
5. Deliver vertical slices that are usable and testable end to end.
6. Use current Kazoo/Monster workflows as interaction evidence while keeping
   the connected API schema and observed Switch behavior authoritative.
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

- An internal `BIGINT UNSIGNED AUTO_INCREMENT`, entity-named primary key such
  as `extension_id` or `device_id`
- A separate immutable UUID column named `id`, stored by MySQL as `CHAR(36)`
  with a unique index, for API routes and UI contracts
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

All future free-entry public telephone-number controls use a shared,
libphonenumber-grade validator in Vue plus independent Laravel validation and
E.164 normalization. The validator must distinguish public numbers from PBX
extensions; selecting an already projected number continues to use its public
UUID rather than trusting a number supplied by the browser.

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
- `ApiResponse` owns data, error, metadata, and no-content responses so domain
  controllers pass values directly and cannot accidentally emit `data.data`
- API resources, routes, and UI state use only the public UUID column named
  `id`; internal primary and foreign keys are never serialized
- MySQL primary keys are named for their entity (`user_id`, `device_id`,
  `voicemail_box_id`, and so on). `switch_accounts` uses `account_id` because
  `switch_account_id` already stores the upstream Switch account identifier
- Internal primary and foreign keys use `BIGINT UNSIGNED`; public UUIDs are
  immutable, unique, stored as `CHAR(36)`, and generated by Laravel when a
  record is created. MySQL 8.4 has UUID functions but no native UUID data type;
  `BINARY(16)` is intentionally avoided here because these UUIDs are public
  lookup values rather than relationship keys
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
PATCH  /api/v1/accounts/{account}/callflows/{callflow}/tree
PATCH  /api/v1/accounts/{account}/callflows/{callflow}/tree/order
POST   /api/v1/accounts/{account}/callflows/{callflow}/tree/nodes
PATCH  /api/v1/accounts/{account}/callflows/{callflow}/tree/nodes
POST   /api/v1/accounts/{account}/callflows/{callflow}/tree/inline-nodes
PATCH  /api/v1/accounts/{account}/callflows/{callflow}/tree/inline-nodes
DELETE /api/v1/accounts/{account}/callflows/{callflow}
POST   /api/v1/accounts/{account}/sync/caller-id-lists
GET    /api/v1/accounts/{account}/sync/caller-id-lists/{run}
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
- Reuse the owning domain forms inside the Extension drawer rather than
  duplicating relationship fields or stacking dialogs. Extension create now
  embeds the complete Device and Voicemail editors as drawer subviews;
  Extension edit uses the same complete Voicemail subview and mutation contract.
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
  a separate exact-name-confirmed audited command. Dynamic restrictions,
  recording defaults, dial-plan rules, and request formatters use guided
  virtual fields with server-owned nested metadata preserved. Account preflow
  resolves a projected Callflow public UUID, and bounded metaflow activation
  defaults and supported recursive action trees use the shared Device/Account
  guided editor. Resource references are public UUIDs, while unsupported or
  unresolved roots remain locked and are preserved losslessly. Higher-risk
  operations stay gated.
- Add projections and incremental synchronization for each delivered resource
  domain.

### Phase 3: Supporting PBX modules

- Media and music on hold foundation: metadata projection, upload and protected
  range streaming, rename, audio replacement, account MOH assignment, and
  dependency-aware deletion. Binary content remains in Switch storage.
- Directory and group foundation: typed CRUD, queued projection rebuilds,
  normalized membership relationships, complete redacted `switch_json`, safe
  public references, dependency-aware deletion, and guided callflow targets.
  A Device-only inline `ring_group` timing/strategy foundation is delivered
  with bounded public UUID endpoints, computed attempt duration,
  weighted-random routing, and the two schema-backed bridge flags. User/group
  expansion is capability-gated because the installed runtime dynamically
  recurses through mutable membership without a resolved-device cap or safe
  cycle boundary. Ringback/ringtone media behavior remains part of advanced
  visual callflow work.
- Queue and agent foundation: ACDc-aware typed queue CRUD, normalized roster
  projection, redacted `switch_json`, queued synchronization, compensating
  roster updates, live agent status commands, right-side panels, and guided
  `acdc_member` callflow targets. Agent identity reuses projected users rather
  than creating a duplicate durable identity; live status remains operational
  Switch state. Read-only account probes expose Queue configuration, live Agent
  controls, and statistics as separate cached capabilities; failures close the
  affected UI boundary without exposing probe payloads or Switch identifiers.
  Statistics remain a later capability-gated slice.
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
  fails. Callflow routing supports both Rule Sets and ordered direct Rules with
  public-UUID match destinations and explicit per-rule branch updates.
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
- Callflow editor layout: the selectable node graph now uses the full available
  content width on the main Callflow page inside small responsive side gutters,
  without the normal narrow centered-page maximum. A compact categorized action
  palette starts in a sticky Kazoo-style right rail, can be dragged within the
  viewport, and has an explicit Dock control. Safe selected-node information
  and reorder controls open in an accessible modal.
  Existing guided subtrees can be moved by pointer drag-and-drop or an
  equivalent keyboard workflow into empty `_`, Menu, and Temporal Rule Set
  branches. Public paths are revalidated by Laravel and the Switch adapter;
  preserved branches, unsupported actions, and cycles are rejected without
  rewriting the document. Occupied positions support guarded insert-before
  and disjoint-subtree swap operations that preserve the complete raw subtree.
  Right-side panels may edit selected-node typed properties but must not
  contain or constrain the graph.
  Guided resource actions can now be added from the palette into an empty
  schema-valid branch and retargeted from the selected-node modal. The
  shared side panel supports User/Extension, Device, Voicemail, Callflow,
  Media, Directory, Group, Queue Member, Menu, Conference, Fax Box, and
  Temporal Rule Set references without exposing Switch identifiers.
  Sleep, Text to Speech, Collect/Send/Flush DTMF, Dead Air, Language, Record
  Call, Record Caller, Missed Call Alert, Set Caller ID, Prepend Caller ID, and
  Set Alert Info and regex-mode Check Caller ID use a separate schema-driven
  panel with current Switch bounds. Check Caller ID maps optional identity
  overrides through public Extension UUIDs and exposes a safe public
  `match`/`nomatch` branch contract; absolute caller-number branches stay
  preserved. The visual route
  begins with document entry data (`numbers[]`/`patterns[]`) and only then the
  real action tree; this display-only entry card is never written into `flow`.
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
- Services and billing-observability foundation: typed read-only summary,
  limits, ledger-summary, ledger-total, and transaction clients; normalized
  account summary, assigned-plan, quantity, limit, ledger-source, and recent
  transaction projections; complete redacted `switch_json` for source
  objects; administrator-only authorization; queued synchronization; and a
  Vue inventory plus right-side detail panel. The detail contract also exposes
  an account-scoped read-only reconciliation report: projection health,
  version-specific endpoint availability, stored-versus-active ledger and
  transaction row counts, billing-owner mapping, and the ten latest public
  service-sync runs. Failures are mapped to safe categories and recovery
  guidance; exception classes, raw backend messages, credentials, and private
  Switch identifiers never reach the UI. Endpoint availability is stored
  explicitly so older Switch deployments do not display missing data as zero.
  Immutable projected transaction history is retained when an endpoint is
  unavailable. Billing identifiers, payment tokens, provider metadata, and
  bookkeeper configuration are redacted, and upstream transaction identifiers
  are never exposed to the UI. Plan assignment, limit changes, top-ups,
  credit/debit, sale/refund, invoices, payment methods, and charge acceptance
  remain outside this read-only foundation.
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
  selectable recursive canvas, safe public branch-label contract,
  selected-node modal, compact searchable 73-module schema reference palette,
  guided reference-node add/edit forms, empty-branch moves, insert-before, and
  disjoint-subtree swaps and the first bounded non-reference module forms are
  delivered. Remaining module-specific forms and dynamic branch contracts
  remain next.
- SMS/MMS with carrier, consent, retention, and abuse-control gates.
- Number purchasing, porting, releasing, CNAM, and E911 workflows after
  carrier and compliance approval. Read-only System Status probes now report
  only Port Request collection availability through an exact non-number filter
  and the validated shape availability of the account-scoped carrier-info
  endpoint. Number search, provider inventory, quotes, charges, purchase,
  reservation, release, request details/documents, state transitions, carrier
  automation, and number completion remain capability-gated.

### Phase 4: Business modules

- Reseller and client hierarchy.
- Branding and user preferences.
- Billing, payment methods, and invoices.
- Zero-touch phone provisioning.
- Trunks, carriers, resources, and connectivity administration.
- Webhooks and advanced account/security administration.

These modules require separate threat models and acceptance criteria before
implementation, especially payment handling.

The first billing slice is deliberately observability-only. Switch remains
authoritative for calculated service quantities, ledger totals, ledger-source
usage, and its transaction records. GridPBX projects those values into MySQL
for authorized search and support workflows; it does not recalculate them or
act as a second accounting ledger. A future payment-provider integration must
use hosted fields or provider tokenization so PAN/CVV never reaches Laravel,
must use server-side idempotency and webhook reconciliation, and must begin
with provider sandbox credentials stored only in local environment or secret
management. Authorize.Net charge, tokenize, refund, credit/debit, and payment
method mutations remain disabled until that separate design is approved.

The payment-provider foundation is separate from the Switch billing projection.
It provides an administrator-only capability contract and a server-side,
read-only Authorize.Net sandbox diagnostic using merchant details. The
diagnostic refuses production endpoints and returns only safe booleans and
status categories; API login IDs, transaction keys, signature keys, public
client-key values, merchant details, and raw provider errors are never returned.
The configured sandbox credentials have been verified as reachable and
authenticated, including a boolean public-client-key match, without creating a
transaction.

Sandbox operation paths now have testable, default-off groundwork: named BIGINT
internal keys, public UUIDs, account-scoped HMAC idempotency keys, request
fingerprints, encrypted provider references, append-only safe event rows,
administrator authorization, explicit typed confirmation, per-account/user/IP
rate limiting, and independent charge, void, refund, and profile flags. Hosted
Authorize.Net tokenization is the only card-entry design; a charge also fails
closed unless its public tokenization key is configured. Raw PAN/CVV, opaque
tokens, raw gateway requests/responses, and webhook payloads are not persisted.

This groundwork is not an enabled payment feature. `PAYMENTS_ENABLED`, the
global mutation flag, and every operation flag default to `false`; production
endpoints are rejected, indeterminate attempts reserve their source operation
until reconciliation, and the live mutation Playwright case requires a separate
explicit opt-in. The default-off browser acceptance sends no payment mutation
and loads no provider script. One separately authorized `$1.00` hosted-tokenized
sandbox charge has completed successfully and remains as the only stored payment
attempt; its provider reference is encrypted and absent from public responses.
Tenant-scoped attempt history and independently gated, typed-confirmation UI
controls are implemented for void, bounded partial refund, and customer-profile
creation. Those three operations remain provider-mocked only and must not be
enabled or exercised live until separately authorized. The dedicated threat
model, reconciliation/operations workflow, signed webhook design, and disposable
sandbox cleanup procedure are still required before broader acceptance.

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
