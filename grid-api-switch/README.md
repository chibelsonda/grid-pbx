# GridPBX Switch client

Framework-independent PHP client used by `grid-api` to communicate with the
Switch Crossbar API.

The package owns:

- API-key authentication and token refresh behavior
- authenticated JSON transport
- normalized upstream exceptions
- cursor-safe account resource pagination
- list-to-detail hydration for complete entity payloads
- immutable typed snapshots that also preserve unknown Switch fields

It must not store tokens in files or expose Switch credentials to the browser.

## Design boundary

The package deliberately separates three responsibilities:

- `SwitchClient` handles transport and authentication.
- `Resources\AccountResourceClient` enumerates collections and fetches each
  detail document.
- `Dto\*Snapshot` classes expose stable typed fields while retaining the full
  upstream `data` object through `toArray()`.

Snapshots are read models, not write payloads. Create and update operations
will use dedicated command DTOs so private or read-only Switch fields are not
sent back accidentally. Persistence and credential redaction belong to the
Laravel application boundary; this package has no MySQL dependency.

The initial snapshot set covers users, devices, voicemail boxes, and
callflows. Additional entity snapshots will be added with their feature slice.
