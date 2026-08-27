# GridPBX Application Implementation Plan

Status: Active
Last updated: 2026-08-27

## 1. Objective

Build a fresh GridPBX management application that replaces Monster UI with a
simpler, task-oriented interface while using the local Kazoo Crossbar API as
the PBX source of truth.

The application will preserve the legacy three-project boundary:

- `grid-api`: Laravel application API and application-owned data.
- `grid-api-switch`: framework-independent Kazoo Crossbar client package.
- `grid-ui`: Vue 3 and TypeScript single-page application.

The legacy projects under `/home/chicote/App/gridpbx-old` are reference
material only. New code must not depend on or modify those projects.

## 2. Product principles

1. Present user tasks instead of raw Kazoo resources wherever possible.
2. Keep all Kazoo credentials and tokens on the server.
3. Treat Kazoo as the source of truth for PBX configuration.
4. Store only application concerns in MySQL: identities, authorization,
   Kazoo account mappings, preferences, and audit records.
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
  |-- MySQL: users, organizations, roles, mappings, audit log
  |-- Redis: sessions, cache, queues, Kazoo token cache
  |
  v
grid-api-switch (Composer package)
  |
  | Kazoo API key authentication and X-Auth-Token
  v
External Kazoo Crossbar API configured by `KAZOO_BASE_URL`
```

Kazoo and Monster UI are intentionally not part of this repository. A separate
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
- Laravel HTTP client for outbound Kazoo requests
- Pest or PHPUnit for unit and feature tests
- Laravel Pint and PHPStan for code quality
- OpenAPI 3.1 contract for `/api/v1`

### UI

- Vue 3 with Composition API and `<script setup>`
- TypeScript in strict mode
- Vite
- Vue Router and Pinia
- Tailwind CSS 4 through the official Vite plugin
- Headless, accessible primitives where a custom component needs behavior
- Vitest for component and composable tests
- Playwright for critical browser flows

## 6. Domain-driven architecture

Both applications use the same business vocabulary and bounded contexts, but
DDD is applied according to each application's responsibility. The goal is
clear domain ownership and dependency direction, not empty layers or a class
for every operation.

Initial bounded contexts:

- Identity and access
- Organizations and Kazoo accounts
- People and extensions
- Devices
- Phone numbers
- Call routing
- Voicemail and media
- Call history
- Auditing and administration

### API structure

Laravel uses a modular DDD structure. Each bounded context may contain:

```text
app/Domains/{Context}/
|-- Domain/          # Entities, value objects, policies, domain services/events
|-- Application/     # Use cases, commands, queries, DTOs, and ports
|-- Infrastructure/  # Eloquent persistence and external service adapters
`-- Presentation/    # HTTP controllers, requests, resources, and routes
```

Dependency rules:

- Domain code does not depend on Laravel, Eloquent, HTTP, or Kazoo payloads.
- Application use cases coordinate domain behavior through contracts.
- Infrastructure implements those contracts and owns persistence or external
  transport details.
- Presentation validates and translates HTTP input, calls one application use
  case, and formats the response; controllers contain no business rules.
- Cross-context work occurs through explicit application contracts or events,
  not by reaching into another context's internal models.
- `grid-api-switch` is the anti-corruption layer between GridPBX terminology
  and Kazoo Crossbar resources.

Small features may start with fewer files and gain layers only when behavior
requires them. The dependency rules still apply.

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

## 7. UI direction

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

- A PBX concept may be technically composed of several Kazoo resources, but
  the UI should expose a single guided workflow where that matches the task.
- Every mutation displays pending, success, and failure states.
- Destructive actions require explicit confirmation and identify the target.
- Empty states explain what the resource does and provide the next action.
- Forms keep advanced Kazoo fields collapsed unless the task needs them.
- Keyboard focus, color contrast, labels, and error summaries are required.

## 8. Authentication and authorization

1. The Vue SPA authenticates to Laravel using Sanctum session cookies and CSRF
   protection. Application tokens are not persisted in local storage.
2. Laravel authenticates to Kazoo using a server-side API key.
3. Kazoo tokens are cached with an expiry shorter than the server expiry and
   refreshed under a distributed lock.
4. A local user belongs to an organization and receives roles and permissions.
5. An organization is mapped to one or more Kazoo accounts.
6. Laravel policies verify both the permission and account mapping before any
   Kazoo call.
7. Mutations create audit records containing actor, account, resource, action,
   request correlation ID, outcome, and safe change metadata.

Initial roles:

- Platform administrator
- Reseller administrator
- Account administrator
- Account operator
- Read-only user

## 9. API conventions

- Base path: `/api/v1`
- JSON request and response bodies except file transfers
- Resource-oriented routes with account scope in the URL
- Consistent success, validation, error, and pagination envelopes
- UUID/ULID identifiers for application-owned records
- Kazoo identifiers remain opaque strings
- Correlation ID returned on every response
- Validation errors use stable field keys
- Upstream Kazoo errors are translated to stable application error codes
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
GET    /api/v1/accounts/{account}/devices
GET    /api/v1/accounts/{account}/phone-numbers
GET    /api/v1/accounts/{account}/voicemails
GET    /api/v1/accounts/{account}/callflows
GET    /api/v1/accounts/{account}/call-records
```

## 10. Delivery phases

### Phase 0: Foundation

- Create this implementation plan and architecture decisions.
- Scaffold all three projects.
- Add MySQL, Redis, Laravel, and Vue services to Compose.
- Establish the API modules and UI domain boundaries before feature growth.
- Add health checks and developer commands.
- Establish formatting, static analysis, unit tests, and CI-ready scripts.

Acceptance criteria:

- The separately running Kazoo/Monster reference environment is not modified.
- New API and UI build and start through Docker Compose.
- Default test and static-check commands pass.

### Phase 1: Secure vertical slice

- Move the initial Laravel authentication/account code and Vue shell into the
  agreed domain module boundaries before adding PBX feature screens.
- Implement local users, organizations, roles, permissions, and account maps.
- Implement Sanctum SPA login/logout/session endpoints.
- Implement Kazoo API-key authentication and token caching.
- Add account list and Kazoo health boundary.
- Build login, application shell, account selector, dashboard, and a read-only
  extensions page.

Acceptance criteria:

- A seeded administrator can sign in without a token in browser storage.
- The user can only select mapped Kazoo accounts.
- The extensions page reads live data through Laravel, never directly from
  Kazoo.

### Phase 2: Core PBX management

- Extension workflow combining user, device, voicemail, and basic callflow.
- Device CRUD and SIP credential handling.
- Voicemail CRUD.
- Phone number inventory and assignment.
- Basic call routing and callflow visualization.
- Call-detail record listing and filtering.

### Phase 3: Supporting PBX modules

- Media and music on hold.
- Directories and groups.
- Conferences and fax boxes.
- Time-of-day rules, blacklists, queues, and recordings.

### Phase 4: Business modules

- Reseller and client hierarchy.
- Branding and user preferences.
- Billing, payment methods, and invoices.
- Zero-touch phone provisioning.

These modules require separate threat models and acceptance criteria before
implementation, especially payment handling.

### Phase 5: Migration and hardening

- Compare workflows against Monster UI and the legacy GridPBX application.
- Add rate limiting, idempotency, retries, circuit breaking, and observability.
- Complete browser, accessibility, security, backup, and recovery testing.
- Document production deployment and migration.
- Retire Monster UI only after agreed parity checks pass.

## 11. Test strategy

- `grid-api-switch`: isolated unit tests with fake HTTP responses plus opt-in
  integration tests against the local Kazoo API.
- `grid-api`: policy, validation, API contract, database, and service tests.
- `grid-ui`: component and composable unit tests.
- End to end: login, account selection, extension creation, device assignment,
  number routing, and logout.
- Every bug fix adds a regression test at the lowest effective layer.

Tests that mutate Kazoo must use a dedicated test account and identify created
resources so cleanup is deterministic.

## 12. Definition of done

A feature is complete when:

1. Its acceptance behavior is documented.
2. Authorization and account isolation are enforced server-side.
3. Loading, empty, success, validation, and upstream-error states exist.
4. Automated tests cover the primary behavior and important failure modes.
5. Logs and responses do not expose credentials or tokens.
6. API documentation and user-facing labels are current.
7. The feature works responsively and passes keyboard navigation checks.

## 13. Deferred decisions

- Final production hosting platform and domain layout.
- Whether external/mobile API consumers need personal access tokens.
- Payment gateway and compliance scope.
- Production telephony topology and FreeSWITCH integration.
- Data migration from the legacy application database.

Each deferred decision should receive an architecture decision record before
implementation changes the system boundary.
