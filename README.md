# GridPBX local development environment

This repository contains the new GridPBX management application:

- `grid-api`: Laravel API with Sanctum authentication and MySQL persistence
- `grid-api-switch`: framework-independent PHP client boundary for Kazoo
- `grid-ui`: Vue 3, TypeScript, Pinia, Vue Router, and Tailwind CSS application
- Domain-driven modules in the API and domain-oriented feature modules in the UI
- MySQL 8.4 and Redis 7 for the new application

Kazoo and Monster UI source code are intentionally excluded from this
repository. GridPBX integrates with an independently running Kazoo Crossbar API
through `grid-api-switch`; it does not embed or publish the legacy platform.

The implementation roadmap and architecture decisions live in
[docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md). The PBX capability
catalog and delivery priorities live in
[docs/KAZOO_FEATURE_ROADMAP.md](docs/KAZOO_FEATURE_ROADMAP.md).

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
selection, a searchable MySQL extension projection, manual Kazoo sync, sync
status, and a dedicated Redis queue worker.

## Start or resume

The normal command resumes the complete GridPBX application:

```bash
docker compose up -d
bash scripts/status.sh
```

## Connect an external Kazoo API

Set these values in the root `.env` file:

```dotenv
KAZOO_BASE_URL=http://kazoo:8000/v2
KAZOO_API_KEY=
KAZOO_ACCOUNT_ID=
```

The local reference container is attached to the same Docker network and uses
the hostname `kazoo`. For another independently managed environment, replace
the URL with a Crossbar `/v2` endpoint reachable from the API container. Kazoo
credentials remain server-side and must never be added to the repository.

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
does not include Kazoo, a SIP edge, FreeSWITCH media servers, or RTP services;
calls will not terminate or originate through this repository alone.
