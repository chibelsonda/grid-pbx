# GridPBX Switch Client Architecture

`grid-api-switch` is the framework-free anti-corruption layer between GridPBX and the external
Switch and provisioner APIs. It does not own HTTP controllers, persistence models, database
queries, or Laravel application services.

## Package boundaries

- `SwitchClient` and `SwitchConfig` are the stable package entry points.
- `Shared/Authentication` owns token acquisition and invalidation.
- `Shared/Http` and `Shared/Exceptions` contain cross-context transport concerns.
- `Shared/Dto` contains response structures genuinely shared by multiple contexts.
- `Domains/<Entity>/Dto` contains typed request and response data for one Switch entity.
- `Domains/<Entity>/*ResourceClient.php` performs that entity's Switch HTTP operations.
- `Domains/Provisioning` owns the separate provisioner transport and catalog DTOs.

DTOs may validate and serialize Switch payloads, but business workflows and MySQL projection stay
in `grid-api`. New Switch entities must be added as a new domain module instead of extending the
legacy global `Dto` or `Resources` directories.

## Dependency direction

Domain modules may depend on the root `SwitchClient` and shared transport types. Shared code must
not depend on a domain module. The package must not depend on Laravel or a database abstraction.

Tests mirror the source contexts under `tests/Domains`; cross-cutting client and architecture tests
live under `tests/Shared`.
