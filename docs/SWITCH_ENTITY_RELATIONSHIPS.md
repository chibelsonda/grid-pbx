# Switch entity relationships and lifecycle workflows

## 1. Purpose

Switch exposes separate account resources. GridPBX composes those resources
into user-facing workflows, but it must not pretend that an extension, IVR, or
queue is one atomic Switch object.

This document defines:

- which Switch resources own data and which only reference other resources;
- which related resources GridPBX may create as one guided workflow;
- safe creation, update, deletion, and compensation order;
- how MySQL projections represent the relationships; and
- where a workflow must stop for user confirmation instead of cascading.

The relationship fields were checked against the local Crossbar schemas in
`src/kazoo/applications/crossbar/priv/couchdb/schemas` and compared with the
legacy GridPBX coordinators. The local source is a reference only; GridPBX does
not modify or package the Switch or Monster UI repositories.

## 2. Core rules

1. Switch remains authoritative for PBX configuration. MySQL contains safe,
   rebuildable projections and GridPBX workflow state.
2. A MySQL transaction cannot make several remote Switch requests atomic.
   Multi-resource mutations use an orchestrated saga: validate, create in
   dependency order, compensate in reverse order, record the outcome, and
   reconcile.
3. The UI sends only GridPBX public UUIDs. Laravel resolves those UUIDs to
   account-scoped Switch resource IDs immediately before a remote request.
4. References never imply ownership. Deleting a callflow must not delete the
   user, menu, media, or voicemail box it references.
5. A dependent created exclusively by a managed workflow may be compensated
   or deleted with that workflow. A pre-existing or shared resource is never
   cascade-deleted.
6. Every response `data` object is saved in the entity's redacted
   `switch_json` projection. Secrets, credentials, tokens, PINs, and binary
   content are excluded.
7. Unknown callflow modules and branches are preserved losslessly. GridPBX
   edits only fields covered by a validated, version-compatible command.

## 3. Relationship map

| Resource | References | Relationship meaning | Lifecycle rule |
| --- | --- | --- | --- |
| Account | parent/reseller account, media and service settings | Tenant and authorization boundary | Root boundary; deleting an account is a separate destructive workflow |
| User | directories mapped to callflows, media/MOH, feature callflows | Person/extension identity and endpoint policy | Aggregate root for the guided extension workflow |
| Device | `owner_id` -> user; provisioning/media references | A device may be assigned to one owner while remaining an independent endpoint | Optional extension dependent; unassign rather than delete unless created exclusively by the workflow |
| Voicemail box | `owner_id` -> user; `media.unavailable` -> media | Mailbox belongs to a user; greeting is a referenced media object | Optional managed dependent; never delete a non-empty mailbox automatically |
| Callflow | node module data IDs -> user, device, voicemail, menu, group, media, callflow, conference, queue, temporal rule/set, blacklist, and others | Routing graph that references targets | Managed extension callflow is a workflow dependent; ordinary callflows are independent roots |
| Phone number | represented as a callflow entry number; inventory reports `used_by` | Number assignment is ownership by a callflow entry point | Assign/unassign by updating the callflow; purchasing/releasing is separate |
| Media | referenced by play nodes, menus, voicemail, groups, conferences, and MOH settings | Shared prompt, greeting, or hold media | Independent unless uploaded exclusively as a managed greeting; dependency-check before delete |
| Directory | contains users; users map directory ID to a destination callflow ID | Searchable membership plus routing destination | Create directory first, then update user membership mappings; compensate membership updates on failure |
| Group | endpoint map references users/devices; optional MOH media | Reusable membership definition | Independent root; ring-group callflows reference it or embed endpoints depending on module |
| Menu | prompt media and key destinations in callflow children | IVR configuration consumed by a menu callflow node | Independent root; prompt/destinations are references |
| Conference | optional `owner_id`, media, numbers, and conference callflow node | Conference room configuration and routing entry | Independent root; number entry callflow is a managed dependent when created by the conference wizard |
| Fax box | owner user and inbound number/callflow relationships | Fax destination and document store | Conditional workflow; retain documents and block unsafe cascade delete |
| Temporal rule | schedule only | Reusable schedule condition | Independent root; block delete while referenced |
| Temporal rule set | list of temporal-rule IDs | Ordered schedule composition | Create rules before set; delete set before workflow-owned rules |
| Queue | user agents/members and queue callflow modules | ACD configuration plus operational membership/state | Capability-gated aggregate; configuration and live agent state are separate |
| Blacklist | numbers; blacklist callflow node | Reusable rejection policy | Independent root; block delete while referenced |
| Recording/CDR | user/device/call IDs and media metadata | Operational records produced by calls | Read/retention workflow, never created as a side effect of entity CRUD |

CDR foundation note: CDRs are append/upsert operational projections, not CRUD
aggregate members. A bounded on-demand import links a normalized `owner_id` to
the internal extension foreign key when possible, while the API returns only
the extension public UUID. `switch_json` contains an allowlisted copy of each
normalized list `data` item; full CDR documents, recording locations, costs,
rates, authorization IDs, SIP headers, DTMF, and SDP are not persisted. Missing
records in one bounded window are never treated as deletions. Retention,
archival, partitioning, scheduled import, and recording authorization remain
separate client-approved operational policies.

Media foundation note: `switch_media` is the canonical account media metadata
projection. The Switch remains authoritative for the binary, which is uploaded
and streamed through the typed boundary rather than copied into MySQL. An
account's default music-on-hold setting is stored as an internal foreign key to
that projection while the UI receives only public UUIDs. Deletion rechecks
known account MOH, voicemail greeting, and callflow play-node references. Menu,
group, conference, and queue dependency checks must be added when those
projections are delivered; an unknown future dependency must never justify a
forced cascade delete.

Directory/group foundation note: `switch_directories` and `switch_groups`
retain each complete redacted Switch `data` object in `switch_json`, while
`switch_directory_members` and `switch_group_members` normalize the
relationships needed by the Laravel API and Vue UI. Directory membership is
coordinated through each user's directory-to-callflow mapping with compensating
patches on partial failure. Group endpoint maps support users, devices, nested
groups, ordered weights, and optional projected music-on-hold media. Guided
callflows may target a directory or reusable group using public UUIDs; inline
`ring_group` endpoint timing and strategy editing remains an advanced-editor
slice.

## 4. Guided extension aggregate

“Create extension” is the clearest multi-resource workflow. The UI presents one
right-side panel, while Laravel deliberately performs separate steps.

### Default creation order

1. Validate authorization, account ownership, unique extension/mailbox, and
   all requested options without mutating Switch.
2. Create the Switch user. Project it immediately so its public UUID is stable.
3. If selected, create a voicemail box with `owner_id` set to the new Switch
   user ID and the mailbox defaulted to the extension number.
4. If selected, create a device with `owner_id` set to the new Switch user ID.
   Generate credentials only at this boundary and never persist the password.
5. Create the managed extension callflow. Its root `user` node references the
   new user; its wildcard child references the new voicemail box when enabled.
   The extension number is its initial entry number.
6. Commit projections, relationship metadata, and one aggregate audit outcome.
7. Queue a targeted reconciliation so the projection is repaired if Switch
   applied defaults or asynchronous changes.

The callflow is created last because it makes the extension dialable. A caller
must not reach a half-provisioned user while dependent resources are missing.

### Compensation order

If a later step fails, GridPBX attempts reverse-order cleanup only for resources
created by this operation:

1. delete the managed callflow;
2. delete the new device;
3. delete the empty new voicemail box;
4. delete the new user; and
5. remove or tombstone their projections.

If compensation fails, the request returns a stable workflow failure code and
records the surviving Switch IDs in an internal repair record/audit event. It
must not claim that the operation was rolled back. Reconciliation then marks
the aggregate `repair_required` until an operator retries cleanup or adopts
the surviving resource.

Current implementation note: create records each successfully created resource
by its internal upstream identifier, but the recovery API returns only public
operation data and safe step names. A complete compensation is recorded as
`rolled_back`. If cleanup itself fails, the manager-only recovery panel retries
only those failed reverse-order cleanup steps and marks the operation recovered
after all leaked resources are removed. Credentials and raw payload snapshots
are never written to lifecycle context.

### Update behavior

- Profile/caller-ID changes update the user only.
- Changing the extension number updates the user, managed callflow entry
  number, managed mailbox number/name, and generated device labels where the
  operator opted into managed naming.
- Enabling voicemail creates the mailbox and attaches a voicemail fallback to
  the managed callflow.
- Disabling voicemail first detaches the callflow reference. It deletes the
  mailbox only when it is workflow-owned, empty, and explicitly confirmed.
- Assigning a pre-existing device only changes its `owner_id`; it does not make
  that device owned by the extension workflow.
- GridPBX never rewrites a callflow no longer marked as managed. It reports the
  divergence and asks the operator to edit routing explicitly.

Each update step is persisted. A failure before any upstream change is marked
rolled back; a partial upstream update enters the recovery queue. Its bounded
recovery action runs extension reconciliation from Switch before marking the
operation recovered, so MySQL never becomes the source used to overwrite an
unknown upstream state.

### Delete behavior

Before deleting an extension, Laravel builds and returns a dependency preview:

- managed callflows and their phone-number entry points;
- owned and merely assigned devices;
- voicemail boxes and message counts;
- directory/group/queue membership; and
- other callflows that reference the user or its dependents.

Deletion is blocked by external callflow references, assigned phone numbers,
non-empty voicemail, operational queue membership, or unresolved references.
The operator may choose safe detach operations. Confirmed deletion proceeds in
reverse dependency order and never deletes shared resources.

Current implementation note: the preview covers projected devices, voicemail
boxes and message counts, callflows, and phone-number assignments. Membership
checks for directories, groups, and queues are added with those projections.
The right-side panel requires the exact extension number before execution.
Laravel re-runs the preview and deletes managed callflows, devices, voicemail
boxes, and finally the user. `extension_lifecycle_operations` stores a public
operation UUID, completed steps, the failed step, and sanitized recovery
context. Retrying a partial deletion resumes that operation and skips completed
steps. Local projections are soft-deleted only after all upstream deletions
finish; until then the extension is marked `error` for reconciliation. A row
lock prevents concurrent deletion sagas, while an operation left without
progress for 15 minutes can be resumed using its recorded steps.

The same right-side recovery queue covers all three extension lifecycle types:
failed create cleanup, partial-update reconciliation, and partial-delete resume.
It never returns lifecycle context or Switch resource identifiers to Vue.

## 5. Other aggregate workflows

| Guided workflow | Creation order | Compensation/deletion boundary |
| --- | --- | --- |
| IVR | media (optional) -> menu -> callflow -> phone-number assignment | Detach number, delete managed callflow/menu, delete prompt only if workflow-owned and unreferenced |
| Business hours | temporal rules -> temporal rule set -> routing callflow | Detach route, delete set, then only workflow-owned unreferenced rules |
| Ring group | group/membership -> callflow -> number assignment | Membership resources remain unless workflow-owned and unreferenced |
| Conference | conference -> callflow -> number assignment | Preserve recordings and shared media; detach number first |
| Queue | queue -> agent membership -> callflow -> number assignment | Capability-gated; remove live memberships/state before configuration |
| Voicemail greeting | media upload -> mailbox media reference | Detach mailbox first; delete only managed, unreferenced media |
| Directory | directory -> user directory/callflow mappings | Roll back only mappings written by the operation; never delete users/callflows |

## 6. Projection model

Relationships are projected with both forms where useful:

- internal named foreign keys (`switch_extension_id`,
  `assigned_callflow_id`) for efficient joins;
- upstream IDs (`owner_switch_resource_id`) for deterministic rebuilds; and
- public UUIDs (`id`) in API resources and UI payloads.

Many-to-many relationships use explicit projection tables, for example
`switch_group_members`, `switch_directory_members`, and
`switch_queue_agents`. Those tables store internal named foreign keys and
relationship attributes such as endpoint type, priority, delay, timeout,
role, or state. They do not expose internal keys to the UI.

MySQL foreign-key cascades apply only to disposable projection rows. They do
not authorize or trigger deletion in Switch.

Queue foundation note: `switch_queues` owns durable normalized configuration
and the complete redacted queue `data` object in `switch_json`.
`switch_queue_agents` maps a queue to the existing projected extension/user;
it stores the upstream user reference only for deterministic synchronization.
There is deliberately no duplicate agent identity table. Live login, logout,
pause, resume, and wrap-up state is fetched or commanded through ACDc and is
not treated as durable MySQL truth. Queue creation updates configuration first
and roster second with cleanup on failure; updates restore both prior settings
and roster on failure; deletion clears roster before deleting configuration.

Menu foundation note: `switch_menus` stores normalized digit collection,
recording, hunt, retry, and prompt settings while retaining the complete
redacted Menu `data` object in `switch_json`. Prompt references resolve to
public media resources when projected and preserve their upstream references
for reconciliation. Menu deletion is blocked while a projected callflow uses
the Menu; guided call routing resolves the UI UUID to the upstream Menu ID and
writes a `menu` callflow node without exposing the MySQL `menu_id`. Recording
PINs are write-only: MySQL stores only `record_pin_configured`, API responses
never return the PIN, and the retained `switch_json` value is redacted.

## 7. Implementation sequence

1. Done: typed user and managed extension-callflow commands in
   `grid-api-switch`.
2. Done: Laravel create/update provisioning, policies, projection handling,
   compensation/repair-required reporting, audit logs, and tests.
3. Done: Vue right-side create/edit panels and end-to-end coverage.
4. Done: deletion dependency preview, exact-number confirmation, reverse-order
   deletion saga, persisted resume progress, audit outcomes, right-side UI, and
   partial-failure recovery tests.
5. Done: persisted create/update lifecycle progress and manager-only recovery
   queue for cleanup, reconciliation, and deletion resume.
6. Reuse the orchestration pattern for IVR/menu, temporal routing, groups, and
   the capability-gated queue workflow.
