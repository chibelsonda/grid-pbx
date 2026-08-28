# GridPBX local development environment

This repository contains the new GridPBX management application:

- `grid-api`: Laravel API with Sanctum authentication and MySQL persistence
- `grid-api-switch`: framework-independent PHP client boundary for Switch
- `grid-ui`: Vue 3, TypeScript, Pinia, Vue Router, and Tailwind CSS application
- Domain-driven modules in the API and domain-oriented feature modules in the UI
- MySQL 8.4 and Redis 7 for the new application

Switch and Monster UI source code are intentionally excluded from this
repository. GridPBX integrates with an independently running Switch Crossbar API
through `grid-api-switch`; it does not embed or publish the legacy platform.

The implementation roadmap and architecture decisions live in
[docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md). The PBX capability
catalog and delivery priorities live in
[docs/SWITCH_FEATURE_ROADMAP.md](docs/SWITCH_FEATURE_ROADMAP.md).

All published ports bind to `127.0.0.1`.

## Start the new API and UI

```bash
cp .env.example .env
docker compose up -d mysql redis grid-api grid-worker grid-ui
bash scripts/status.sh
```

The copy step is needed only once. The checked-in development defaults work
without editing `.env`; change them before using this anywhere beyond a local
machine. When `GRID_API_APP_KEY` is empty, the API container generates an
ephemeral development key at startup; set a persistent key before sharing an
environment or relying on durable sessions.

| New service | Local address |
| --- | --- |
| Vue UI | http://localhost:5173 |
| Laravel API | http://localhost:8081 |
| API health | http://localhost:8081/api/v1/health |
| MySQL | `127.0.0.1:3309` (`gridpbx` database) |

The initial local Laravel account is:

- Email: `admin@gridpbx.local`
- Password: `admin-change-me`

The current vertical slice includes secure UI login, tenant-scoped account
selection, a searchable MySQL extension projection, manual Switch sync, sync
status, and a dedicated Redis queue worker.

## Start or resume

The normal command resumes the complete GridPBX application:

```bash
docker compose up -d
bash scripts/status.sh
```

## Connect an external Switch API

Set these values in the root `.env` file:

```dotenv
SWITCH_BASE_URL=http://switch:8000/v2
SWITCH_API_KEY=
SWITCH_ACCOUNT_ID=
```

The local reference container is attached to the same Docker network and uses
the hostname `switch`. For another independently managed environment, replace
the URL with a Crossbar `/v2` endpoint reachable from the API container. Switch
credentials remain server-side and must never be added to the repository.

Queue and agent management additionally requires the external Switch deployment
to run ACDc and expose its Crossbar bindings. On the Switch node, enable ACDc in
the deployment's persistent application list and register the HTTP modules once:

```bash
sup kapps_controller start_app acdc
sup crossbar_maintenance start_module cb_queues
sup crossbar_maintenance start_module cb_agents
```

`start_module` stores the Crossbar autoload setting. The external deployment
must also keep `acdc` in its startup application configuration so the runtime
service returns after a restart. GridPBX intentionally does not own or package
that Switch configuration.

Recording inventory and protected audio access require the existing Crossbar
recordings module to be autoloaded on the external Switch node:

```bash
sup crossbar_maintenance start_module cb_recordings
```

GridPBX stores recording metadata only; audio remains in Switch or its selected
storage provider.

Conference management requires the Crossbar conferences module:

```bash
sup crossbar_maintenance start_module cb_conferences
```

GridPBX stores conference configuration and redacted response data in MySQL.
PINs remain write-only, and live participants are not persisted as durable
database rows.

Fax management requires the Crossbar fax-box and fax-message modules:

```bash
sup crossbar_maintenance start_module cb_faxboxes
sup crossbar_maintenance start_module cb_faxes
```

The external Switch deployment must also provide its fax application, document
storage, and notification services. GridPBX stores fax-box configuration and a
bounded, redacted metadata projection in MySQL; fax documents remain in Switch
or its storage provider and are streamed only after an authorized request.
Outbound sending, forwarding, resubmission, and deletion are intentionally not
enabled until retention, notification, and abuse-control policies are agreed.

The read-only Services overview requires the account services and v2 limits
Crossbar modules:

```bash
sup crossbar_maintenance start_module cb_services
sup crossbar_maintenance start_module cb_limits_v2
```

GridPBX projects assigned-plan metadata, flattened account/cascade/manual
quantities, current limits, account standing, billing-cycle metadata, and
aggregate billing impact. Payment tokens, billing identifiers, and bookkeeper
configuration are redacted. Plan assignment, limit changes, manual quantities,
top-ups, and charge acceptance are not exposed by GridPBX.

## Stop and inspect

```bash
docker compose down
docker compose ps
docker compose logs -f grid-api grid-worker grid-ui mysql
```

`docker compose down` preserves MySQL and Redis volumes. Running
`docker compose down -v` deletes local application data and should only be used
for an intentional clean reset.

## Scope and caveats

This is a management API/UI development environment, not a production PBX. It
does not include Switch, a SIP edge, FreeSWITCH media servers, or RTP services;
calls will not terminate or originate through this repository alone.
