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
- validated callflow trees with derived module, node-count, and depth summaries
- bounded callflow root-destination and phone-entry updates that preserve all
  child branches and non-phone entry points
- bounded guided callflow creation and deletion commands
- bounded managed-extension callflow updates that preserve unrelated numbers,
  root settings, and independent branches
- bounded user create/update/delete commands for aggregate workflows
- streamed binary responses for protected media delivery
- bounded single and bulk voicemail message lifecycle commands
- bounded, cursor-safe CDR summary pagination for approved Unix time windows

It must not store tokens in files or expose Switch credentials to the browser.

## Design boundary

The package deliberately separates three responsibilities:

- `SwitchClient` handles transport and authentication.
- Entity resource clients enumerate collections and fetch detail documents
  where appropriate, including the keyed phone-number collection shape. CDRs
  intentionally use Crossbar's normalized list rows instead of hydrating full
  documents that may contain SIP headers, DTMF, SDP, costs, or recording URLs.
- `Dto\*Snapshot` classes expose stable typed fields while retaining the full
  upstream `data` object through `toArray()`.

Snapshots are read models, not write payloads. Create and update operations
use dedicated command DTOs so private or read-only Switch fields are not sent
back accidentally. The bounded callflow command starts from a fresh detail,
removes private document fields, replaces only the root module data, and keeps
unknown child branches intact. When explicitly supplied, it replaces only
known phone-number entry points while preserving extension numbers and
patterns. Persistence and credential redaction belong to the Laravel
application boundary; this package has no MySQL dependency.

The initial snapshot set covers users, devices, voicemail boxes, voicemail
message metadata, callflows, media, phone numbers, and call detail records.
Additional entity snapshots will be added with their feature slice.
