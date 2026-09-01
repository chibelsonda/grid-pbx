# Switch Schema Parity

## 1. Purpose

GridPBX must cover the public account-scoped Switch/Kazoo contract for every
supported entity. A form is not considered complete merely because basic CRUD
works. Each public field must be classified, implemented where appropriate,
and tested through the Vue UI, Laravel API, and `grid-api-switch` boundary.

This inventory is based on the local upstream snapshots:

- Kazoo `6af38c7`, especially the Crossbar JSON schemas under
  `applications/crossbar/priv/couchdb/schemas`
- Monster UI `171a051`, used only as a workflow and conditional-visibility
  reference

The Kazoo schema and Crossbar endpoint behavior are authoritative. Monster UI
does not define the data contract.

## 2. Parity rules

Every public field receives one of these treatments:

| Treatment | Meaning |
| --- | --- |
| Editable | GridPBX presents and safely writes the field |
| Read-only | GridPBX presents the value but does not mutate it |
| Conditional | Editable only when the account and Switch deployment advertise the required capability |
| Write-only | Accepted for mutation but never returned or persisted in readable form |
| Managed | Derived or changed only through a dedicated operational workflow |
| Hidden | Private, unsafe, obsolete, or infrastructure-only; the exclusion must be documented |

Parity applies to public fields, nested objects, accepted variants, defaults,
and clear-value semantics. It does not mean exposing CouchDB metadata, `pvt_*`
properties, authentication tokens, PINs, SIP passwords, carrier credentials,
provisioning secrets, generated configuration documents, or infrastructure
addresses.

For updates, GridPBX must distinguish these states:

- omitted: preserve the current Switch value;
- supplied: validate and replace the value;
- explicitly cleared: remove the value when the endpoint permits it.

An update must never rebuild a Switch document from only the visible form and
silently delete fields that GridPBX does not own.

## 3. Persistence rules

- MySQL normalizes identifiers, relationships, searchable fields, operational
  summaries, and reporting fields.
- The complete redacted response `data` object is retained in `switch_json`.
- Nested schema parity does not automatically require one MySQL column per
  field.
- Secrets are removed before `switch_json` persistence and API serialization.
- Public UUID `id` values remain the only database identifiers exposed to the
  UI. Named internal keys such as `device_id` remain server-side.
- Binary media, voicemail audio, fax documents, recordings, and generated
  provisioning files are streamed through authorized endpoints rather than
  copied into JSON columns.

### 3.1 Virtual-field policy

GridPBX does not create a physical MySQL column for every public Switch JSON
path. Each field receives one persistence treatment:

1. Normalize identifiers, relationships, fields used by account-scoped joins,
   and fields that are routinely filtered, sorted, aggregated, or reported.
2. Expose display/edit-only JSON paths as typed Laravel resource values or
   model accessors derived from the redacted `switch_json` snapshot. These are
   application virtual fields and are not database columns.
3. Add a MySQL `VIRTUAL` generated column for a scalar JSON path only after an
   actual SQL filter, sort, uniqueness rule, or index requirement is proven.
   Generated columns are selective query optimizations, not the default field
   mapping strategy.
4. Keep nested maps and ordered arrays such as call restrictions, caller ID,
   SIP/media configuration, codecs, recording rules, formatters, metaflows, and
   provisioning key maps in `switch_json` unless a relationship or query use
   case requires a separate projection.

The UI never receives raw `switch_json`. API resources expose an allowlisted,
typed virtual view, and mutation endpoints accept dedicated validated command
payloads. Sensitive values remain redacted and cannot be surfaced through a
virtual attribute or generated column.

### 3.2 Form evidence requirement

Field parity is verified using the workflow in
[`SWITCH_FORM_AUDIT.md`](SWITCH_FORM_AUDIT.md). A field cannot be marked
runtime-verified from template inspection or a mocked test alone.

The 2026-08-31 cross-entity presentation pass also enforces one shared,
Device-style horizontal tab implementation for writable multi-section forms.
Conference, Menu, and Voicemail retain Monster's nested section hierarchy;
Queue uses installed-schema/runtime semantic grouping because this Monster
checkout has no Queue editor. Basic-only resources and read-only projections
remain tabless. This presentation rule does not expand any public payload or
change existing public-UUID/private-resource mapping.

## 4. All-entity registry

`Detailed matrix` identifies the field-level audit status, not implementation
completion.

| Entity | Canonical schema or endpoint source | Related objects and additional boundaries | Current implementation | Detailed matrix | Delivery order |
| --- | --- | --- | --- | --- | --- |
| Account | `accounts.json` | hierarchy, limits, service plans, capabilities | Foundation | Safe projection matrix complete below; typed settings mutations pending | 6 |
| Blacklist | `blacklists.json` | account activation and number entries | Foundation | Complete below | 4 |
| CallDetailRecord | `cdrs.json` plus MODB CDR endpoints | interactions, recordings, retention | Foundation | Safe read/filter/relationship matrix complete below; retention remains policy-gated | 5 |
| Callflow | `callflows.json` and `callflows.*.json` module schemas | users, devices, groups, queues, menus, temporal routes, numbers | Foundation | Full-width main-page safe recursive workspace, node inspector, 73-module schema reference palette, root, entry-point, wildcard fallback, Menu keys, Rule Set routing, ordered direct Temporal Rule match routes, and dedicated read-only Feature Codes inventory complete; safe Feature Code lifecycle mutations and future dynamic branch contracts remain gated | 3 |
| Conference | `conferences.json` and conference action endpoints | users, role numbers, callflows, live participants | Foundation | Form matrix, room lock/unlock, safe single-participant controls, confirmed native bulk mute/hearing controls, and bounded media playback complete below; bulk kick and dial-out remain gated | 3 |
| Device | `devices.json` and referenced endpoint schemas | users, registrations, line keys, provisioner, numbers | Foundation | Complete below | 1 |
| Directory | `directories.json` | users and destination callflows | Foundation | Complete below | 2 |
| Fax | `faxbox.json`, `faxes.json`, and document endpoints | users, numbers, callflows, media | Foundation | Fax Box form matrix complete below; message mutations remain gated | 4 |
| Group | `groups.json` | users, devices, ring groups, callflows | Foundation | Complete below | 3 |
| LineKey | `devices.combo_key.json` embedded in `devices.provision` | device, provisioner brand/family/model | Foundation | Complete below | 2 |
| Media | `media.json` plus upload/content endpoints | menus, music on hold, prompts | Foundation | Upload/audio/MOH matrix complete below; generated sources gated | 5 |
| Menu | `menus.json` | media prompts and callflow DTMF branches | Foundation | CRUD form audited; root and nested DTMF/timeout/continuation routing delivered for guided branches, with unknown shapes preserved read-only | 3 |
| PhoneNumber | `phone_numbers.json` plus number-manager feature/action endpoints | callflows, CNAM, E911, porting, carriers, SMS/MMS | Foundation | Safe read/detail matrix complete below; mutations policy-gated | 4 |
| Queue | `queues.json`, agent endpoints, and ACDc runtime | users, devices, callflows, agent state/statistics | Foundation | Detailed matrix complete below; live controls and statistics remain capability-gated | 3 |
| Recording | MODB recording documents and content endpoints; no single Crossbar CRUD schema | CDRs, storage policy, retention | Foundation | Safe metadata/playback matrix complete below; deletion and retention remain policy-gated | 5 |
| Services | services, limits, service-plan, ledger, and quote endpoints | accounts, reseller hierarchy, billing provider | Foundation/read-only | Detailed read/presentation matrix complete below; mutations policy-gated | 6 |
| SystemStatus | Crossbar/system health and capability endpoints; no durable entity schema | applications, nodes, registrations, provider health | Foundation/read-only | Complete safe presentation/capability matrix below; mutations remain separately gated | 6 |
| TemporalRule | `temporal_rules.json` plus enable/disable/reset actions | callflows, rule sets, account timezone | Foundation | Complete below | 3 |
| TemporalRuleSet | `temporal_rules_sets.json` plus member rule actions | temporal rules and callflows | Foundation | Complete below | 3 |
| User | `users.json` and user action endpoints | devices, voicemail, directories, groups, queues, callflows | Foundation | Complete below | 2 |
| Voicemail | `vmboxes.json`, voicemail key schemas, message/greeting endpoints | users, media, callflows, notifications | Foundation | Complete below | 2 |

The advanced client-confirmed areas are part of the associated entity matrices:

- number purchasing, porting, release, CNAM, and E911;
- advanced visual callflow editing;
- queues and agents;
- SMS/MMS;
- recordings;
- provisioning;
- billing and reseller management;
- trunks, carriers, resources, and connectivity;
- webhooks and advanced administration.

If an advanced area does not map cleanly to one entity schema, it receives a
capability/action matrix alongside the owning domain rather than being silently
excluded.

## 5. Device field-level matrix

### 5.1 Device types and form composition

GridPBX will support the same addable device-type set used by the upstream
workflow:

| Device type | Basic controls | Advanced controls |
| --- | --- | --- |
| `sip_device` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Audio, Video, Options, Restrictions; recording and notifications are grouped under Options |
| `cellphone` | name, owner, enabled, forwarding number | legacy forwarding behavior and contact-list visibility; current-schema extensions are grouped under Advanced forwarding |
| `smartphone` | name, owner, enabled, forwarding number | Basic, Caller ID, Wi-Fi calling, Audio, Video, Options, Restrictions |
| `softphone` | name, owner, enabled | Basic, Caller ID, SIP, Audio, Video, Options, Restrictions; recording and notifications are grouped under Options |
| `landline` | name, owner, enabled, forwarding number | legacy forwarding behavior and contact-list visibility; current-schema extensions are grouped under Advanced forwarding |
| `fax` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Audio, Options, Restrictions; T.38 and notifications are grouped under Options |
| `ata` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Audio, Options, Restrictions; optional T.38 and notifications are grouped under Options |
| `sip_uri` | name, owner, enabled, SIP URI/route | Basic plus Options containing only contact-list visibility |

Device-type selection controls visibility and defaults; it does not define a
different database table.

The 2026-08-31 presentation verification corrected three stale capability
entries. Monster exposes Caller ID, Audio, and Video for Smartphone, and Audio
for Fax and ATA. The installed Device schema treats `device_type` as an
arbitrary UI/billing label while its generic Caller ID and endpoint-media
schemas validate these values for the same Device document, and Crossbar has
no per-type rejection for them. GridPBX now exposes those fields in the
matching Advanced sub-tabs and includes them in the typed payload. Raw resource
IDs and SIP secrets remain outside the public form contract.

The form uses a device capability matrix rather than rendering every property
accepted by the generic Device schema for every type. `contact_list.exclude` is
available to all eight types. Registered endpoints (`sip_device`, `smartphone`,
`softphone`, `fax`, and `ata`) additionally receive endpoint-behavior and
advanced-routing controls. `cellphone` and `landline` receive their forwarding
workflow. `sip_uri` deliberately sends only `sip.invite_format`, `sip.route`, and
its contact-list option; SIP credentials, media, Caller ID, provisioning,
restrictions, and endpoint routing controls do not belong to that workflow.

### 5.1.1 Version-aware compatibility matrix

GridPBX reads `GET /v2/schemas/devices` from the connected Switch and returns a
safe capability summary from the Device options endpoint. Vue, Zod, Laravel
validation, and the Switch DTO boundary all use that same summary. If schema
discovery is unavailable, GridPBX uses the conservative legacy column below so
it does not submit fields an older deployment may reject.

| Area | Connected local schema (`6af38c7`, 2021-04-13) | Current upstream schema | GridPBX behavior |
| --- | --- | --- | --- |
| `call_forward.number` | maximum 15 characters | maximum 35 characters | Input and Laravel limit follow the connected schema |
| `sip.invite_format` | no `strip_plus` | includes `strip_plus` | Options and validation are populated from the advertised enum |
| `sip.custom_sip_interface` | absent | string | Control and payload key appear only when advertised |
| `sip.forward` | absent | string | Control and payload key appear only when advertised |
| `sip.proxy` | absent | string | Control and payload key appear only when advertised |
| `sip.static_invite` | absent | string | Control and payload key appear only when advertised |
| `sip.transport` | absent | string | Control and payload key appear only when advertised |
| `provision.id` | absent | string | Template ID appears only when advertised |
| `provision.endpoint_model` | string or integer | string or array | Form representation and server validation follow the advertised types |
| `provision.check_sync_event` | present | absent | Retained only for compatible deployments |
| `provision.check_sync_reload` | present | absent | Retained only for compatible deployments; operational reload remains separate |
| `provision.check_sync_reboot` | present | absent | Retained only for compatible deployments; operational reboot remains separate |

The official schema is the field contract; the legacy Kazoo/Monster form is
used to preserve workflow semantics and discover conditional behavior. This is
why controls can intentionally differ between Switch versions while the form
layout remains familiar.

### 5.2 Core, relationship, and operational fields

| Schema path | Type/default | GridPBX treatment | UI location | MySQL and security treatment | Current status |
| --- | --- | --- | --- | --- | --- |
| `name` | string, required | Editable | Basic | normalized `name`; retained in `switch_json` | Implemented |
| `device_type` | string | Editable from supported type set | Device type selector | normalized; retained | Implemented; live create/edit/clear matrix verified for all eight types |
| `enabled` | boolean, default `true` | Editable | Basic | normalized `is_enabled`; retained | Implemented |
| `owner_id` | Switch object ID | Editable through public extension/user UUID | Basic / Assignment | normalized relationship; upstream ID never exposed; unassignment removes the key from a preserved full-document update | Implemented and runtime-verified |
| `mac_address` | string | Conditional for provisionable hardware | Basic / Provisioning | canonical uppercase colon notation; retained | Implemented; one active canonical MAC per account is enforced by validation and MySQL, and line-key apply requires brand, model, and MAC |
| `language` | string | Editable with account-supported options | Advanced / Locale | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `timezone` | string | Editable with account default/inheritance | Advanced / Locale | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `presence_id` | string | Editable | Advanced / Presence | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `do_not_disturb.enabled` | boolean | Editable | Advanced / Options | `switch_json` | Implemented |
| `call_waiting.enabled` | boolean | Editable | Advanced / Options | `switch_json` | Implemented |
| `exclude_from_queues` | boolean, default `false` | Editable when queues are available | Advanced / Options | `switch_json`; optional normalized agent projection | Implemented |
| `contact_list.exclude` | boolean, default `false` | Editable | Advanced / Options | `switch_json` | Implemented |
| `music_on_hold.media_id` | Switch media ID | Editable using public media UUID | Advanced / Routing and endpoint behavior | relationship resolved server-side; upstream ID hidden | Implemented and live create/edit/clear verified for registered endpoint types |
| `mwi_unsolicited_updates` | boolean, default `true` | Editable | Advanced / Options / Notifications | `switch_json` | Implemented for applicable types |
| `register_overwrite_notify` | boolean, default `false` | Editable | Advanced / Options / Notifications | `switch_json` | Implemented for applicable types |
| `suppress_unregister_notifications` | boolean, default `false` | Editable using the positive Kazoo wording and inverse value mapping | Basic / Device identity | `switch_json` | Implemented for applicable types |
| `hotdesk.users` | map keyed by Switch user ID | Managed through dedicated sign-in/sign-out operations | Device detail / Active hotdesk users | public extension UUIDs are resolved server-side; unprojected active users are counted but their upstream IDs remain hidden | Implemented with audited live-document patching and focused API/SDK coverage |
| `flags[]` | string array | Conditional/admin | Advanced / General flags and formatters | application virtual field from `switch_json` | Implemented and live create/edit/clear verified for registered endpoint types |
| `outbound_flags[]` | string array, or `static[]`/`dynamic[]` | Conditional/admin | Advanced / Routing flags | legacy and live flat arrays hydrate as `static`; typed UI grouping is flattened at the Switch boundary | Implemented and live create/edit/clear verified for registered endpoint types |

Fax writes always retain the `fax` outbound flag used by the Kazoo workflow;
other user-configured static flags remain ordered after it.

### 5.3 Call forwarding

| Schema path | Type/default | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `call_forward.enabled` | boolean, default `false` | Editable | Basic for external-number types; Advanced / Options | Implemented |
| `call_forward.number` | string, connected-schema max (15 legacy; 35 current) | Editable | Basic for cellphone/landline/smartphone | Implemented with version-aware UI and Laravel validation |
| `call_forward.direct_calls_only` | boolean, default `false` | Editable | Advanced / Options | Implemented |
| `call_forward.failover` | boolean, default `false` | Editable | Advanced / Options | Implemented |
| `call_forward.ignore_early_media` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.keep_caller_id` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.require_keypress` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.substitute` | boolean, default `true` | Editable | Advanced / Options | Implemented |

All forwarding fields remain in redacted `switch_json`; the number may be
normalized later only if account-wide routing search requires it.

For `cellphone` and `landline`, the Basic Enabled control writes both
`enabled` and `call_forward.enabled`, matching the Kazoo workflow and preventing
divergent operational state. Options keeps Require keypress, Keep original
caller ID, and contact-list visibility immediately visible. The four additional
current-schema fields—direct calls only, failover, ignore early media, and
substitute—remain available in a collapsed Headless UI disclosure. SIP, media,
Caller ID, provisioning, restrictions, and registered-endpoint routing fields
are rejected for these forwarding-only types.

### 5.4 SIP

| Schema path | Type/default | Treatment | UI location | MySQL and security treatment | Current status |
| --- | --- | --- | --- | --- | --- |
| `sip.method` | `password` or `ip`; default `password` | Editable | Advanced / SIP | `switch_json` | Implemented |
| `sip.username` | string | Write-only on mutation; masked configured state on read | Basic or Advanced / SIP | never persisted or returned as raw credential unless policy later explicitly permits username display | Implemented with configured-state projection |
| `sip.password` | string | Write-only | Basic or Advanced / SIP | redact before logs, MySQL, exceptions, and responses | Implemented write-only |
| `sip.realm` | string | Read-only by default; conditional override | Advanced / SIP | redacted safe value in `switch_json` | Implemented |
| `sip.expire_seconds` | integer, default `300` | Editable | Advanced / SIP | `switch_json` | Implemented |
| `sip.invite_format` | enum, default `contact` | Editable | Advanced / SIP | `switch_json` | Implemented |
| `sip.ip` | string | Conditional when method is `ip` | Advanced / SIP | safe projection only for authorized admins; otherwise masked | Implemented conditionally |
| `sip.number` | string | Conditional on invite format | Advanced / SIP | `switch_json` | Implemented conditionally |
| `sip.route` | string | Conditional on invite format `route`/SIP URI | Basic for `sip_uri`; Advanced otherwise | validate as SIP URI; redact embedded credentials | Implemented conditionally |
| `sip.static_route` | string | Conditional/admin | Advanced / SIP | validate and redact embedded credentials | Implemented |
| `sip.custom_sip_interface` | string | Connected-schema conditional | Advanced / SIP | `switch_json`; omitted when unsupported | Implemented conditionally |
| `sip.forward` | string | Connected-schema conditional | Advanced / SIP | `switch_json`; omitted when unsupported | Implemented conditionally |
| `sip.proxy` | string | Connected-schema conditional | Advanced / SIP | `switch_json`; omitted when unsupported | Implemented conditionally |
| `sip.static_invite` | string | Connected-schema conditional | Advanced / SIP | `switch_json`; omitted when unsupported | Implemented conditionally |
| `sip.transport` | string | Connected-schema conditional | Advanced / SIP | `switch_json`; omitted when unsupported | Implemented conditionally |
| `sip.ignore_completed_elsewhere` | boolean | Editable for SIP Device and Softphone | Advanced / SIP | `switch_json`; omitted for Smartphone, Fax, and ATA | Implemented and live create/edit/clear verified for applicable types |
| `sip.custom_sip_headers.<name>` | string map | Conditional/admin | Advanced / SIP headers | legacy undirected maps hydrate as outbound; authentication headers are denied | Implemented compatibility read |
| `sip.custom_sip_headers.in.<name>` | string map | Conditional/admin | Advanced / SIP headers | bounded name/value rows mapped to a Switch object; authentication headers denied | Implemented and live create/edit/clear verified |
| `sip.custom_sip_headers.out.<name>` | string map | Conditional/admin | Advanced / SIP headers | same as above | Implemented and live create/edit/clear verified |

### 5.5 Caller ID and privacy

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `caller_id.internal.name` | string | Editable | Advanced / Caller ID | Implemented |
| `caller_id.internal.number` | string | Editable | Advanced / Caller ID | Implemented |
| `caller_id.external.name` | string | Editable subject to carrier/account rules | Advanced / Caller ID | Implemented |
| `caller_id.external.number` | string | Select from account-owned projected numbers | Advanced / Caller ID | Implemented with UI options and server-side ownership enforcement |
| `caller_id.emergency.name` | string | Conditional on E911 capability | Advanced / Emergency caller ID | Implemented |
| `caller_id.emergency.number` | string | Select only E911-enabled account numbers | Advanced / Emergency caller ID | Implemented with UI filtering and server-side E911 enforcement |
| `caller_id.asserted.name` | string | Conditional/admin | Advanced / Asserted identity | Implemented |
| `caller_id.asserted.number` | string | Conditional/admin | Advanced / Asserted identity | Implemented |
| `caller_id.asserted.realm` | string | Conditional/admin | Advanced / Asserted identity | Implemented |
| `caller_id_options.outbound_privacy` | `full`, `name`, `number`, or `none` | Editable | Advanced / Caller ID | Implemented |

Caller ID values are retained in `switch_json`. Number selection is resolved
through account-scoped phone-number projections; no phone-number primary key or
Switch resource ID is exposed. Existing unprojected values remain visible on an
edit form so unrelated changes do not silently erase legacy configuration.

### 5.6 Media

| Schema path | Type/default | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `media.audio.codecs[]` | unique ordered codec enum | Editable | Advanced / Audio | Priority editor implemented; create/edit/clear order verified live |
| `media.video.codecs[]` | unique ordered codec enum | Editable | Advanced / Video | Priority editor implemented; create/edit/clear order verified live |
| `media.bypass_media` | boolean or legacy `auto` | Editable with compatibility handling | Advanced / Media | Implemented |
| `media.encryption.enforce_security` | boolean, default `false` | Editable | Advanced / Encryption | Implemented |
| `media.encryption.methods[]` | `zrtp`, `srtp` | Editable/capability-gated | Advanced / Encryption | Implemented |
| `media.fax_option` | boolean | Conditional for SIP Device, Softphone, Fax, and ATA, matching the Kazoo workflows | Advanced / Options | Implemented and live create/edit/clear verified for applicable types |
| `media.ignore_early_media` | boolean | Editable | Advanced / Media | Implemented |
| `media.progress_timeout` | integer seconds | Editable | Advanced / Media | Implemented |

Codec order is significant and is preserved by DTOs, the API, and explicit UI
move controls.

### 5.7 Restrictions, dial plans, and formatters

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `call_restriction.<classification>.action` | `inherit` or `deny` | Editable from live account number classifications; unknown stored keys remain editable | Advanced / Restrictions | Implemented; create/edit/reset-to-inherit verified live for all applicable types |
| `dial_plan.system[]` | string array | Conditional/admin | Advanced / Dial plan | Implemented and live create/edit/clear verified for registered endpoint types |
| `dial_plan.<regex>.description` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field and live verified |
| `dial_plan.<regex>.prefix` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field and live verified |
| `dial_plan.<regex>.suffix` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field and live verified |
| `formatters.<field>[]` | ordered formatter rules | Conditional/admin | Advanced / General flags and formatters | Implemented as typed rule rows and live create/edit/clear verified for registered endpoint types |
| formatter `direction` | `inbound`, `outbound`, or `both` | Conditional/admin | formatter editor | Implemented and live verified |
| formatter `match_invite_format` | boolean | Conditional/admin | formatter editor | Implemented |
| formatter `prefix` / `suffix` / `value` | string | Conditional/admin | formatter editor | Implemented and live verified |
| formatter `regex` | regex string | Conditional/admin | formatter editor | Implemented with bounded validation and live verified |
| formatter `strip` | boolean | Conditional/admin | formatter editor | Implemented |

Dynamic classification and formatter keys require allowlists, regex safety,
field-count limits, and payload-size limits in Laravel validation.

### 5.8 Recording

`call_recording` supports the direction scopes `any`, `inbound`, and
`outbound`; each direction supports the network scopes `any`, `onnet`, and
`offnet`. Each leaf accepts:

| Leaf field | Type | Treatment | Security treatment | Current status |
| --- | --- | --- | --- | --- |
| `enabled` | boolean | Editable | audited | Implemented |
| `format` | `mp3` or `wav` | Editable | safe | Implemented |
| `record_min_sec` | integer | Editable | bounded validation | Implemented |
| `record_on_answer` | boolean | Editable | safe | Implemented |
| `record_on_bridge` | boolean | Editable | safe | Implemented |
| `record_sample_rate` | integer | Conditional | capability allowlist | Implemented with fixed allowlist |
| `time_limit` | integer, 5–10800 | Editable | bounded validation | Implemented |
| `url` | URI | Conditional/admin | SSRF validation; redact embedded credentials | Deliberately excluded pending policy |

The UI presents this as an Advanced recording matrix rather than exposing raw
nested JSON. Vue validates the matrix with Zod before submission, Laravel
revalidates the security boundary, and the typed Switch DTO preserves the
direction/network hierarchy.

### 5.9 Provisioning and line keys

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `provision.endpoint_brand` | string | Conditional | Basic / Hardware | Implemented for provisionable types as an application virtual field |
| `provision.endpoint_family` | string | Conditional | Basic / Hardware | Implemented for provisionable types as an application virtual field |
| `provision.id` | string | Connected-schema conditional template ID | Basic / Hardware | Implemented as an application virtual field; hidden on this legacy deployment |
| `provision.endpoint_model` | connected-schema string, integer, or array | Conditional | Basic / Hardware | Implemented as a version-aware application virtual field |
| `provision.check_sync_event` | string, legacy schema only | Managed/admin | Advanced / Provisioning events | Conditionally retained; live create/edit/clear verified for SIP Device, Fax, and ATA on the local legacy deployment |
| `provision.check_sync_reload` | string, legacy schema only | Managed/admin | Advanced / Provisioning events; explicit reload action on detail | Conditionally retained; live configuration lifecycle and reload command verified |
| `provision.check_sync_reboot` | string, legacy schema only | Managed/admin | Advanced / Provisioning events; confirmed reboot action on detail | Conditionally retained; live configuration lifecycle and reboot command verified |
| `provision.combo_keys.<position>` | combo-key object or null | Conditional | Advanced / Line keys | Grouped main/expansion presentation plus model capacity and supported-type validation implemented |
| `provision.feature_keys.<position>` | combo-key object or null | Conditional | Advanced / Feature keys | Account-scoped presence/personal-parking suggestions resolve only through synchronized Extension/User projections; devices are not offered as BLF targets |
| combo-key `type` | `line`, `presence`, `personal_parking`, `speed_dial`, or `parking` | Conditional | key editor | Implemented with model capability checks and line restricted to combo keys |
| combo-key `value` | string, parking position 1–10, or `{label,value}` | Conditional | key editor | Implemented: presence/personal parking map public user UUIDs at the API boundary, speed dial stores a literal dialable value, and line carries no custom value |

Provisioning mutations remain capability-gated. GridPBX never exposes vendor
credentials, provisioning URLs containing secrets, templates containing
secrets, or generated endpoint documents.

### 5.10 Ringtones and metaflows

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `ringtones.internal` | string | Editable | Advanced / Options / Ringtone headers | Dedicated control implemented and runtime-verified for create/edit/clear |
| `ringtones.external` | string | Editable | Advanced / Options / Ringtone headers | Dedicated control implemented and runtime-verified for create/edit/clear |
| `metaflows.binding_digit` | DTMF enum, default `*` | Conditional/admin | Advanced / In-call features | Implemented and live create/edit/clear verified; clear restores `*` |
| `metaflows.digit_timeout` | non-negative integer | Conditional/admin | Advanced / In-call features | Implemented with a 60000 ms UI/API safety cap and live verified |
| `metaflows.listen_on` | `both`, `self`, or `peer` | Conditional/admin | Advanced / In-call features | Implemented and live create/edit/clear verified |
| `metaflows.numbers.<digits>` | recursive metaflow | Conditional/admin | dedicated guided editor | Supported action trees are editable; root replacement/clear live verified |
| `metaflows.patterns.<pattern>` | recursive metaflow | Conditional/admin | dedicated guided editor | Supported action trees are editable; root replacement/clear live verified |
| metaflow `module` | string, required | Conditional/admin | guided node editor | Allowlisted scalar modules plus `play`, `callflow`, and `move` implemented |
| metaflow `data` | module-specific object | Conditional/admin | module-specific controls | Guided scalar controls and public resource selectors implemented; arbitrary JSON excluded |
| metaflow `children` | recursive object | Conditional/admin | nested guided node editor | Supported trees editable to 8 levels/100 nodes; unsupported subtrees lock and preserve their entire root |

GridPBX does not provide an unrestricted JSON editor to ordinary account users.
Projected media, callflow, device, and extension references are translated to
public UUIDs for `play`, `callflow`, and `move`. Unsupported modules,
unprojected references, Pivot, embedded flows, and recording-upload actions are
preserved as locked Switch data so public-ID translation and SSRF policy cannot
be bypassed.

## 6. Device implementation acceptance criteria

Current checkpoint: the first Device parity slice implements all eight device
types, Basic/Advanced Headless UI structure, conditional forwarding and SIP
forms, audio/video codec selection, core media encryption/T.38 controls,
caller ID, common calling options, notifications, locale fields, nested
Zod and Laravel validation, a complete recording direction/network matrix,
typed nested Switch DTOs, and a credential-safe detail resource. These
controls are implemented end to end but Device remains
`Foundation` until the remaining items below are delivered.

The 2026-08-31 Advanced-tab drift re-audit restored the shared recording and
routing editors plus the full schema-backed payload groups after a presentation
refactor had disconnected them. The correction keeps forwarding-only and SIP
URI payloads minimal, retains connected-schema gates, uses account-scoped
public UUIDs for resource selectors, and continues to preserve unknown Switch
properties at the typed read-merge-write boundary. Focused SDK, Laravel, Vue,
typecheck, and isolated headless create/edit/clear checks passed.

Still outstanding for Device schema completion:

- production verification against a real provisioner after local live
  `/api/phones` discovery was verified with the contract-compatible Compose catalog.

The disposable authenticated Switch lifecycle now passes for Device hotdesk
sign-in/sign-out and recursive resource-linked metaflow create/edit/clear. It
uses temporary User, Media, Callflow, and Device resources and removes them in
reverse dependency order. The same audit now verifies User login creation,
unchanged-password omission, `require_password_update`, and credential removal.

Device parity is complete only when:

1. all eight supported device types have correct conditional Basic and
   Advanced controls;
2. every field above has its declared UI/API/security treatment;
3. nested request validation and clear/omit semantics are covered;
4. the Switch DTO performs a safe patch without dropping unknown fields;
5. `switch_json` contains the complete redacted response `data` object;
6. normalized device and relationship fields reconcile after mutations;
7. capability-gated sections cannot be enabled by crafting a direct request;
8. SIP passwords and other secrets are absent from responses, logs, audit
   context, exceptions, queues, and MySQL;
9. targeted SDK, Laravel, Vue, and end-to-end tests pass.

## 7. User field-level matrix

GridPBX manages a Switch User through the People & Extensions aggregate because
the normal creation workflow also provisions the selected Device, Voicemail
Box, and managed Callflow. This does not merge their schemas: User-specific
fields remain typed and validated at the User boundary.

| Schema path or group | Treatment | Current status |
| --- | --- | --- |
| `first_name`, `last_name` | Editable and required | Implemented |
| `username`, `email`, `enabled`, `timezone` | Editable | Implemented |
| `language`, `presence_id` | Editable with account fallback | Implemented |
| `call_waiting.enabled`, `do_not_disturb.enabled` | Editable | Implemented |
| `contact_list.exclude` | Editable | Implemented |
| `caller_id.internal` | Editable as bounded text, initially derived from the managed extension identity | Implemented for managed edits |
| `caller_id.external`, `caller_id.emergency` | Public account-number selections; emergency selection requires projected E911 capability | Implemented for managed edits with unresolved-current-value preservation |
| `caller_id.asserted` | Switch-managed and never exposed as editable identity; unknown metadata is preserved server-side | Implemented boundary |
| `caller_id_options.outbound_privacy` | Editable | Implemented |
| `directories.<directory_id>` | Managed through public Directory and Callflow relationships | Implemented through the complete Directory matrix and live relationship lifecycle below |
| `call_forward` | Editable through the current eight-field bounded leaf contract; destination accepts internal extensions and dialable public numbers | Implemented for managed edits |
| `call_recording` | Editable with the current User direction/network matrix; Account/Endpoint branches are Account-schema-only and storage URLs stay server-owned | Implemented for managed edits |
| `call_restriction` | Editable from live Switch number classifications, including preserved projected legacy keys | Implemented for managed edits |
| `dial_plan`, `formatters` | Guided bounded editors with safe-regex checks; no unrestricted JSON | Implemented for managed create/edit with retained-rule unknown metadata preservation |
| `flags[]` | Values owned by external applications | Read-only count exposed; values preserved server-side and never accepted from Vue |
| `hotdesk.enabled`, `hotdesk.id`, `hotdesk.keep_logged_in_elsewhere` | Editable in the Extension slide-over through a typed user hotdesk profile | Implemented and live create/edit/clear verified |
| `hotdesk.pin`, `hotdesk.require_pin` | PIN is write-only and redacted; an unchanged configured PIN is preserved through a private read-before-write | Implemented and live preserve/clear verified |
| `media` | Ordered current-schema audio/video codecs plus bounded bypass, encryption, T.38, early-media, and progress-timeout controls | Implemented for managed create/edit; unknown nested properties are preserved server-side |
| `music_on_hold.media_id` | Account-scoped public Media UUID with explicit unresolved-current-value preservation | Implemented for managed create/edit |
| `ringtones.internal`, `ringtones.external` | Bounded Alert-Info header values | Implemented for managed create/edit |
| `metaflows` | Shared guided recursive editor with bounded activation controls and public resource references | Implemented for managed User create/edit; unsupported/unresolved roots lock and preserve |
| `password` | Write-only; required on login creation or normalized username change, omitted when unchanged, and never returned or persisted readably | Implemented and live create/unchanged/clear verified |
| `require_password_update` | Editable only while a login username exists | Implemented and live set/clear verified |
| `priv_level` | Administrator-only role mapping; never accepted from ordinary account forms | Read-only status implemented; policy mutation intentionally excluded |
| `feature_level` | Capability/service-plan controlled | Read-only status implemented; mutation intentionally excluded |
| `profile` | Bounded addresses, assistant, birthday, nicknames, note, role, sort string, and title | Implemented for managed create/edit |
| `pronounced_name.media_id` | Account-scoped public Media UUID with unresolved-current-value preservation | Implemented for managed create/edit |
| `verified` | Read-only operational status | Implemented |
| `vm_to_email_enabled`, `voicemail` | Managed through the Voicemail domain | Foundation; detailed Voicemail matrix complete below |

Current checkpoint: create and edit forms use one domain-owned Zod contract,
Laravel revalidates the boundary, and the implemented configuration is written
through `UserAdvancedData`, entity-organized caller-ID, forwarding, recording,
restriction, hotdesk, metaflow, and credential DTOs. Crossbar hashes and deletes the submitted plaintext
password; GridPBX therefore never attempts to read or preserve it. An unchanged
username is updated without resending the password, while username removal
requires explicit UI/API confirmation. The complete redacted response remains
in `switch_json`; only the safe subset and configured-state metadata are returned
as `configuration`. Hotdesk IDs remain
account-scoped Switch values, while primary keys and upstream resource IDs are
not exposed.

The complete Media, ringtone, routing/profile, and Metaflow create/edit/clear
lifecycle is also verified through the actual authenticated Vue drawer. API
calls are used only for synchronization/readback and guarded cleanup. Empty
optional numeric form controls are normalized centrally to `null`; the
Metaflow clear request therefore removes the local override even though the
subsequent Switch read reports its effective default binding digit `*`.

Managed edits resolve external and emergency caller-ID selections from public
MySQL UUIDs to account-scoped Switch numbers. Laravel independently enforces
E911 capability, while unresolved projected numbers can only be retained by an
explicit preserve choice. Nested future schema keys and asserted identity are
merged from the existing redacted projection; redaction markers are recursively
removed before the upstream request. Recording storage URLs are likewise
preserved only on the server and are never returned as form fields.

Advanced User media settings use the current referenced `endpoint.media`
schema rather than the smaller legacy form alone. The UI sends ordered codec
lists and bounded scalar controls; music-on-hold accepts only a projected
account Media UUID, which Laravel resolves to the Switch identifier. An
unresolved projected music reference is represented only as a preserve state,
so its upstream ID never reaches Vue. Ringtone and media clears replace the
bounded nested object while safe unknown keys are retained from the server-side
snapshot.

The advanced routing/profile section now uses the current `dialplans`,
`formatters`, and `profile` references. Regexes pass the shared bounded safety
policy in both Zod and Laravel, retained rules keep unknown server-owned
options, and profile arrays have explicit size/type limits. Pronounced-name
Media follows the same public-UUID and unresolved-preservation boundary as
music on hold. `verified`, `priv_level`, `feature_level`, and external flags
are displayed only as safe status metadata. Their values are preserved during
ordinary edits but cannot be submitted by the browser.

The 2026-08-31 Advanced-tab drift re-audit reconfirmed every managed-edit
section against the installed User schema and referenced schemas. Unlike the
Device presentation regression, all User controls and payload groups remained
connected. Focused SDK, API, Zod/metaflow, E2E TypeScript, and one isolated
headless User-calling walkthrough passed. The audit made no User code change
and retains the existing live-mutation evidence limits documented above.

The 2026-09-01 create-parity pass connected those same Media,
Routing/Profile, and Metaflow sections to the create aggregate. Shared Zod and
Laravel rules validate both operations, public references resolve only at the
server boundary, and the typed Switch write uses JSON objects for empty
object-shaped schema fields. An isolated disposable Switch lifecycle verified
create, synchronization/readback, and cleanup for the complete tabbed User
contract.

The Wave 2 form audit found presentation and contract work that is not visible
in the field table alone. Shared invalid styling and inline-only field error
placement are implemented for User/Extension. Timezone, language, and presence
now use account-backed choices that preserve existing projected values. The
aggregate reuses the Device domain's complete Basic/Advanced editor in a wide
subview of the existing relationship drawer. All eight endpoint types, the provisioner catalog,
conditional capabilities, Zod validation, payload mapping, Laravel validation,
and Switch mutation translation remain owned by Devices rather than being
duplicated in Extensions. The remaining work and intentional aggregate
boundaries are recorded in
[`SWITCH_FORM_AUDIT.md`](SWITCH_FORM_AUDIT.md#userextension-and-voicemail-audit-checkpoint-2026-08-29).

## 8. Voicemail field-level matrix

GridPBX treats the mailbox document, unavailable greeting media, and mailbox
messages as separate workflows. The mailbox mutation only writes the bounded
mailbox configuration below; it does not overwrite message or media state.

| Schema path or workflow | Treatment | Current status |
| --- | --- | --- |
| `name`, `mailbox` | Editable and required | Implemented |
| `owner_id` | Editable through a public Extension UUID; Switch IDs remain server-side | Implemented |
| `timezone` | Editable; IANA timezone, 5–32 characters | Implemented |
| `notify_email_addresses[]` | Editable; distinct validated addresses, maximum 10 | Implemented |
| `transcribe` | Schema-backed boolean with default `false`. GridPBX reads the installed authentication capability backed by `kazoo_asr:available()` and `kvm_util:transcribe_default()`, exposes only nullable availability/default booleans, and prevents newly enabling transcription when availability is explicitly false. Existing enabled values remain preservable and can be turned off | Implemented and live capability-verified |
| `require_pin` | Editable | Implemented |
| `pin` | Write-only, 4–6 digits; an omitted configured PIN is recovered privately from Switch for the replacement write and never returned to the browser or stored unredacted | Implemented, redacted, and live preserve-verified |
| `is_setup` | Read-only mailbox setup status | Implemented |
| `check_if_owner` | Editable, default `true` | Implemented |
| `delete_after_notify` | Editable, default `false`; mutually exclusive with `save_after_notify` in GridPBX | Implemented |
| `include_message_on_notify` | Editable, default `true` | Implemented |
| `include_transcription_on_notify` | Editable, default `true` | Implemented |
| `media_extension` | Editable enum: `mp3`, `mp4`, or `wav` | Implemented |
| `not_configurable` | Editable, default `false` | Implemented |
| `oldest_message_first` | Editable, default `false` | Implemented |
| `save_after_notify` | Editable; enabling it clears Delete and the API rejects contradictory input | Implemented |
| `skip_envelope` | Editable beta Switch field | Implemented |
| `skip_greeting`, `skip_instructions` | Editable playback controls | Implemented |
| `is_voicemail_ff_rw_enabled` | Editable playback control | Implemented |
| `seek_duration_ms` | Editable non-negative integer; GridPBX safety cap is 300000 ms | Implemented |
| `media.unavailable` | Managed through the authenticated greeting upload/remove workflow | Implemented |
| messages, folders, raw audio, and transcription | Managed through mailbox message workflows; audio is streamed | Implemented |
| `announcement_only` | Hidden because the upstream schema marks it unsupported | Deliberately excluded |
| `flags[]` | External-application metadata preserved from `switch_json` during mailbox updates; not operator-editable | Implemented preservation boundary |
| `notify.callback` | Dedicated bounded workflow for disabled state, number, attempts, interval, timeout, and schedule | Implemented |
| unknown safe public fields | Preserved server-side from redacted `switch_json`, including unedited greeting/setup state and unknown `notify`/callback fields; redaction markers and modeled fields are never copied as preservation input | Implemented preservation boundary |
| voicemail key maps and account playback keys | Operational/account configuration, not mailbox CRUD | Pending administration workflow |

The Vue create/edit form uses a domain-owned Zod schema and right-hand
slideover. Laravel repeats all trust-boundary validation. A typed
`VoicemailBoxAdvancedData` DTO owns the Switch field mapping, while MySQL keeps
the searchable mailbox fields normalized and stores the complete redacted
response `data` object in `switch_json`. The API returns only the safe
configuration subset and never exposes the PIN. `notify.callback` is returned
as typed safe configuration; the form never edits raw JSON.

Extension create and edit reuse the complete Voicemail field component inside
the same right-hand aggregate drawer. The embedded flow derives the mailbox
name, number, and owner relationship but delegates field validation and Switch
mutation mapping to the Voicemail domain. Standalone and aggregate operations
therefore cannot drift into separate Kazoo payload contracts. Isolated live
verification covers aggregate create, full edit hydration, advanced
callback/audio clearing, persistence reload, and cleanup.

The 2026-08-31 Advanced-form drift re-audit found no missing supported form
controls, but runtime inspection found that voicemail `POST` uses
`crossbar_doc:load_merge/3`, which retains CouchDB private fields only and
replaces the submitted public document. The mutation path now preserves safe
unmodeled public fields entirely server-side, preserves unknown `notify`
siblings and callback options, restores empty `media` as a JSON object, and
privately re-reads an unchanged configured PIN before the replacement write.
An isolated disposable lifecycle passed protected-mailbox create, two blank-PIN
edits, callback edit/clear, reopen, and deletion. MySQL reported zero matching
active projections afterward, and an independent CouchDB query reported zero
matching active voicemail documents.

The Wave 2 form audit also identified incomplete UI acceptance behavior and
conditional contracts. Shared invalid styling, inline-only field errors,
account-backed timezone/assignment choices, and create-versus-edit PIN behavior
are now implemented for Voicemail. The Switch schema accepts ASR fields, and
the GridPBX session layer retains only the authentication response's typed
availability/default booleans. The options endpoint reports those values or an
explicit unknown state, and the UI warns without discarding an existing enabled
value.
External flags are preserved rather than exposed, and the callback object is
typed and bounded end-to-end. Runtime create/edit/clear acceptance remains
documented in [`SWITCH_FORM_AUDIT.md`](SWITCH_FORM_AUDIT.md#voicemail-findings):
the paused callback lifecycle and disposable cleanup passed against the
connected Switch. Unassigned mailbox writes omit `owner_id` because the
connected schema rejects an explicit `null`.

The reusable Voicemail fields now use the Device-style outer Basic/Advanced
selector in both standalone and embedded Extension forms. Opening Advanced
exposes Monster's inner Basic/Options tabs. Basic contains identity,
account-scoped assignment, and write-only PIN. Options contains timezone,
schema-backed audio format, notifications, typed callback delivery,
transcription/owner options, and playback behavior. Greeting media remains a
dedicated authenticated operation. Error routing selects the exact outer and
inner tab while preserving one form model and validation contract. The latest
focused rerun passed three component tests, Vue and isolated E2E TypeScript
checks, and two non-mutating isolated headless Playwright checks covering the
standalone and embedded surfaces.

## 9. Directory field-level matrix

Switch Directory documents own dial-by-name behavior. Membership is not
written as an ordinary Directory document replacement: Crossbar derives the
resolved `users` response from mappings on each User document. GridPBX
therefore coordinates membership through typed User mapping patches and then
re-reads the Directory, with compensation if a multi-user update fails.

| Schema path | Treatment | Current status |
| --- | --- | --- |
| `name` | Editable and required; GridPBX/MySQL safety limit is 128 characters | Implemented |
| `confirm_match` | Editable, default `true` | Implemented |
| `min_dtmf` | Editable integer, minimum 1; UI safety cap 20 | Implemented |
| `max_dtmf` | Editable integer, minimum 0; `0` means unlimited and UI safety cap is 20 | Implemented |
| `sort_by` | Editable enum: `first_name` or `last_name` | Implemented |
| `flags[]` | External-application metadata; initialized empty and preserved from `switch_json` on edit, never accepted from the operator form | Implemented preservation boundary |
| `users[]` | Managed through public Extension UUIDs and resolved User/Callflow mappings | Implemented |
| unknown future public properties | Derived only from redacted server-side `switch_json`; modeled fields, `id`, derived `users`, private keys, and redacted values are excluded before the typed full update | Implemented preservation boundary |

The API never accepts Switch User or Callflow identifiers from the UI. It
resolves each public Extension UUID inside the selected account, requires a
projected destination Callflow, and patches only the User `directories`
mapping. The complete redacted Directory response remains in `switch_json`.
Flags remain visible in the safe read projection for diagnostics, but Laravel
prohibits operator flag input and the mutation service preserves existing
values. The Vue slideover uses a domain composable, Zod, a non-clipping
Headless UI sort listbox, shared invalid borders, and inline-only field errors
before Laravel repeats validation at the trust boundary.

Its shared Basic/Advanced tabs mirror Monster: name and public Extension
members are Basic; sorting, DTMF limits, and match confirmation are Advanced.
Focused component/type checks and an isolated non-mutating browser check pass.

The 2026-08-31 form-drift re-audit confirmed that the visible Basic/Advanced
fields match the complete installed schema and Monster workflow. It also
confirmed that Directory `POST` validation finishes through
`crossbar_doc:load_merge/4`, which replaces the existing public document.
Directory updates now pass a private server-derived preservation bag into the
typed SDK DTO, so a safe unknown future field survives without becoming form
input or public response data. Focused SDK and Laravel preservation/rejection
tests passed. At that stage, no new live Directory mutation had been performed.

The 2026-08-31 disposable live follow-through then covered create, edit,
authoritative reopen, removal of the final public Extension member,
`max_dtmf = 0`, and delete. It found that an empty PHP mapping had been encoded
as JSON `[]`; the typed User mapping DTO now emits the schema-required empty
object `{}`. The Directory slide-over also now ignores its own close event
while the nested delete confirmation is open, matching the existing Menu
pattern. The isolated headless lifecycle passed, its public responses contained
only the account-scoped Extension UUID, and independent cleanup checks found
zero active matching MySQL projections and zero active Switch Directories. All
six disposable MySQL projections from the focused attempts are soft-deleted.

A focused 2026-09-01 presentation pass added accessible inventory structure,
keyboard record controls, loading/error announcements, a labelled member
group, mobile-safe actions, and table-local overflow. The shared controlled tab
bar now derives its visual selection from the same model used by form content,
so validation routing cannot show Basic fields with Advanced still styled as
selected. One isolated non-mutating 390-pixel run and five focused component
tests verified the behavior with zero Directory writes and no browser or server
errors.

The Callflow create and tree-mutation recheck on 2026-08-31 confirmed that a
new route now begins on the full main-page visual workspace with the document
entry card and installed action palette, not in a create slide-over. The entry
metadata and root-action modals accept only account-scoped public UUIDs; raw
Switch resource identifiers remain server-side. Guided child-subtree deletion
accepts only a non-empty public branch path plus explicit confirmation, refetches
the latest raw Callflow, removes exactly that subtree, and preserves unrelated
unknown document/node/sibling fields. Root and preserved paths are rejected.
Focused SDK, Laravel, Vue, E2E TypeScript, and two isolated mocked browser
checks passed. A disposable live User-rooted route with a Voicemail child then
proved browser deletion, authoritative reopen, public/raw separation, and raw
root preservation. Independent cleanup found zero active MySQL or Switch
matches and one soft-deleted projection.

## 10. LineKey field-level matrix

Line keys are positions inside a Device's `provision.combo_keys` and
`provision.feature_keys` maps; they are not independent Switch documents.
GridPBX uses a standalone MySQL projection and public UUIDs for UI workflows,
but applies changes as one bounded replacement to the owning Device provisioning
subtree.

| Schema path or variant | Treatment | Current status |
| --- | --- | --- |
| combo/feature map position | Editable non-negative numeric position; GridPBX safety cap 999 | Implemented |
| null map entry | Managed by removing the projected assignment from the full replacement | Implemented |
| `type: line` | Editable with optional string or labeled-string value | Implemented |
| `type: presence` | Editable with optional string or labeled-string value | Implemented |
| `type: personal_parking` | Editable with optional string or labeled-string value | Implemented |
| `type: speed_dial` | Editable with optional string or labeled-string value | Implemented |
| `type: parking` | Editable position 1–10 as integer, numeric string, or labeled integer | Implemented |
| labeled value object | Editable only when a value is present; label/value limited to 255 characters by GridPBX storage | Implemented |
| account resource value | Suggested Extension/User and Device values cross the public boundary as account-scoped UUIDs; Laravel resolves the raw provisioner value only for the Switch write and maps known raw values back before every response | Implemented |
| unknown retained-key fields | Safe unknown top-level key fields and unknown members of a retained labeled-value object are merged from the live Device read; modeled `type`, `label`, and `value` always win | Implemented preservation boundary |
| `provision.endpoint_brand`, `endpoint_family`, `endpoint_model` | Read-only capability identity in this workflow; edited in Device | Implemented |
| `check_sync_event`, `check_sync_reload`, `check_sync_reboot` | Managed in the Device provisioning panel; reload and reboot are explicit audited Device actions | Implemented and live verified for provisionable Device types |

The right-side editor uses a domain composable and Zod, including duplicate
category/position detection and type-dependent value validation. Laravel and
the typed Switch DTO repeat the same boundary rules. Labeled parking values
are normalized to the integer shape required by `devices.combo_key.json`.
Preview and mutation responses exclude SIP credentials and provisioning
infrastructure, and the complete redacted Device response remains in
`switch_json`.

The 2026-08-31 drift re-audit reconfirmed all five key types and their
conditional value shapes directly from the installed
`devices.combo_key.json`. The current Monster checkout has no separate Line
Key editor, so its Device workflow does not override that schema; the legacy
Grid workflow remains evidence only for model-sized main/expansion sections.
Monster exposes Combo Keys and Feature Keys as conditional sections of the
Device editor. Because GridPBX's standalone panel is already the dedicated
advanced provisioning subworkflow and Line Keys are not independent Switch
documents, it intentionally stays single-view instead of adding empty outer
Basic/Advanced tabs.
Installed Device updates use `POST` and `crossbar_doc:load_merge`, while PATCH
recursively merges old key maps, so GridPBX intentionally performs a live
read-modify-POST full map replacement. The SDK now merges safe unknown fields
for retained positions into that replacement. Public suggestions, projected
values, payload previews, and mutation responses use only account-scoped
UUIDs for known Extension/User and Device references. Laravel privately maps
those UUIDs to the raw Switch value and rejects foreign UUIDs; arbitrary
non-UUID dial strings remain schema-valid.

Focused verification passed with five SDK tests / 13 assertions, five Laravel
tests / 55 assertions, the isolated E2E TypeScript typecheck, and one isolated
headless provisioning walkthrough. The browser check created and removed a
disposable Device, confirmed every suggested reference value equaled its
public UUID, found no `switch_resource_id` field, and rechecked the grouped
main/expansion controls without clipping.

## 11. Group and Menu field-level matrices

### Group

| Schema path | Treatment | Current status |
| --- | --- | --- |
| `name` | Required bounded editable name | Implemented |
| `endpoints.<id>.type/weight` | Public User, Device, or Group UUIDs are resolved server-side; ordered weights are bounded 1–100 | Implemented |
| `music_on_hold.media_id` | API-backed projected Media UUID; `null` inherits the account default | Implemented |
| `flags[]` | External-application metadata initialized empty and preserved from `switch_json`; prohibited from operator input | Implemented preservation boundary |
| unknown public fields | Safe unknown top-level fields, retained-endpoint metadata, and non-modeled `music_on_hold` members are preserved privately; modeled fields always win and removed endpoints remain removed | Implemented preservation boundary |

The 2026-08-31 Group drift re-audit confirmed that these are all fields in the
installed `groups.json`. Monster's Group workflow writes the name and User
membership to the Group document, while its extensions, numbers, ring timing,
and feature panels mutate related Callflows rather than additional Group
fields. GridPBX's account-scoped Device and nested-Group membership choices are
a typed normalized superset of that workflow; the API resolves only active
public UUIDs to raw endpoint identifiers and never returns the raw map keys.

Installed Group updates use `POST` and finish through
`crossbar_doc:load_merge`, so an update can replace omitted public document
fields. Laravel now derives a private preservation bag from redacted
`switch_json`, removing resource/private/redacted and modeled values before the
typed SDK merges safe unknown top-level, retained-endpoint, and nested
music-on-hold metadata. The bag and raw endpoint IDs are prohibited operator
inputs and never enter the public response. Clearing music removes the modeled
`media_id` but preserves safe sibling metadata; a completely empty value is
encoded as the schema-required JSON object `{}`. The 128-character name bound,
100-member product cap, UUID ownership checks, and 1–100 weight limits match
across SDK/Laravel/Zod where applicable.

Focused verification covers the SDK, Laravel relationship/projection services,
Zod/composable form boundary, component behavior, and E2E typecheck. An
isolated authenticated browser lifecycle also created a disposable Group with
synchronized User, Device, and nested Group members plus optional Media,
edited it, explicitly cleared every member and the modeled music-on-hold
reference, reopened the authoritative result, and deleted it. The clear
operation verified that empty `endpoints` and `music_on_hold` values are encoded
as schema-required JSON objects rather than arrays. Final MySQL inspection
found zero active `E2E Group %` projections.

Group intentionally has no Basic/Advanced selector. Monster's generic buttons
are hidden by its own runtime because the Group editor declares only one Basic
tab, and the installed schema has no separate advanced settings. GridPBX keeps
the complete modeled Group contract in that single workflow.

A focused 2026-09-01 presentation pass added accessible inventory structure,
keyboard record controls, loading/error announcements, a labelled ordered
member group, mobile-safe actions, and table-local overflow. One isolated
non-mutating 390-pixel run verified the listbox, inline validation, zero Group
or sync writes, and no browser or server errors. It did not originate a media
leg and therefore does not change the pending audible Ring Group evidence.

### Menu

| Schema path | Treatment | Current status |
| --- | --- | --- |
| `name`, `timeout`, `interdigit_timeout`, `max_extension_length`, `retries` | Required schema-bounded controls with matching Zod and Laravel validation | Implemented |
| `hunt`, `hunt_allow`, `hunt_deny` | Direct-extension dialing and bounded optional patterns | Implemented |
| `allow_record_from_offnet` | Explicit boolean recording-origin control | Implemented |
| `suppress_media` | Public compatibility control mapped to `media.invalid_media`, `transfer_media`, and `exit_media = false`, which are the values consumed by installed `cf_menu` | Implemented runtime mapping |
| `record_pin` | Write-only 3–6 digit value; blank edit preserves, a replacement overwrites, and an explicit removal omits it from the full public-document update | Implemented |
| `media.greeting` | Account-scoped projected Media UUID; unresolved current raw references remain private and are preserve-or-explicit-clear | Implemented |
| `media.invalid_media`, `transfer_media`, `exit_media` | Schema union represented as enabled/system-prompt boolean or account-scoped Media UUID; disabling ignores stale IDs and unresolved current values are preserve-or-explicit-clear | Implemented |
| `flags[]` | External-application metadata initialized empty and retained; prohibited from operator input | Implemented preservation boundary |
| unknown public fields | A fresh pre-update Switch read preserves safe unknown top-level and nested `media` fields; modeled values win and private/redacted/resource metadata is discarded | Implemented preservation boundary |

The 2026-08-31 Menu drift re-audit confirmed every installed `menus.json`
field and inspected `cb_menus`, `crossbar_doc:load_merge`, `cf_menu`, and
Monster's Menu workflow. Installed `cf_menu` does not read the documented
top-level `suppress_media` value; Monster implements that switch by writing all
three result-prompt media members as booleans. GridPBX now does the same while
retaining the schema field, disables the dependent prompt controls, and never
lets a disabled prompt's stale Media UUID override the boolean.

Menu `POST` replaces omitted public fields after retaining only private CouchDB
metadata. The SDK therefore performs one authoritative pre-update read and
merges safe unknown top-level and nested `media` values into the typed write.
The write-only record PIN is recovered only for a preserve operation and never
enters MySQL or the public API; explicit removal leaves it out. Unresolved raw
Media references are exposed only as booleans, remain preserved by default,
and require an explicit clear or account-scoped public Media replacement.
Empty media serializes as the schema-required object `{}`.

The Device-style outer Basic/Advanced presentation maps Monster's Basic view
to name, write-only recording PIN, direct-extension enablement, and greeting.
Opening Advanced exposes Monster's inner Basic, Extension Dialing, and Options
sections as separate horizontal tabs. Allowed and denied patterns belong only
to Extension Dialing; timeouts, retries, recording behavior, and the installed-
schema invalid/transfer/exit prompt superset belong to Options. Client and
server errors select the exact outer and inner tab without duplicating
validation rules.

Focused verification passed with four SDK tests / 19 assertions, four Laravel
tests / 37 assertions, eight Vue tests across three files, Vue and E2E
TypeScript checks, one isolated non-mutating form check, and one disposable
live lifecycle. The final lifecycle used `E2E Menu 45693910`, created and
replaced a write-only PIN, enabled runtime prompt suppression, reopened the
authoritative values, removed the PIN, reopened again, deleted the Menu, and
ran an independent Menu synchronization. MySQL remained soft-deleted after
that sync, proving no matching active Switch Menu remained. Public responses
contained only the Menu UUID and safe fields, never the PIN or raw Switch ID.
The latest semantic-grouping rerun passed three focused component tests, Vue
and isolated E2E typechecks, and the isolated non-mutating headless Menu check.
The earlier disposable live round-trip and cleanup evidence remains valid
because this follow-through changed presentation and error routing only, not
the verified payload.

## 12. Queue and Agent field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name`, `strategy` | Required name and schema enum | Implemented |
| `agent_ring_timeout`, `agent_wrapup_time`, `connection_timeout`, `max_queue_size`, `ring_simultaneously` | Schema minimums plus documented GridPBX safety caps | Implemented |
| `enter_when_empty`, `record_caller`, `caller_exit_key` | Typed boolean and enum controls | Implemented |
| `moh`, `announce` | API-backed public Media UUIDs resolved inside the account | Implemented |
| `max_priority` | Create-only 0–255 virtual field; preserved from `switch_json` on edit | Implemented and live verified |
| `announcements.interval` | 15–86400 second bounded virtual field | Implemented and live verified |
| `announcements.position_announcements_enabled`, `wait_time_announcements_enabled` | Explicit periodic announcement switches | Implemented and live verified |
| `announcements.media.*` | Four public Media choices accepted only as a complete schema-valid set | Implemented |
| `cdr_url`, `recording_url`, runtime `call_recording_url` | Hidden pending outbound URL/SSRF allowlist policy. The installed schema exposes `recording_url`, but the installed ACDc queue FSM reads `call_recording_url`; existing values under both keys are preserved and never returned | Intentionally policy-gated |
| safe unknown Queue fields | Authoritative pre-update GET merges unknown top-level, `announcements`, and nested prompt metadata; modeled fields win, while IDs, revisions, private/redacted values, and raw roster IDs are discarded | Implemented and focused-tested |
| queue roster | Public Extension UUIDs resolved to Switch User identifiers and replaced separately | Implemented |
| live agent status | Login, logout, pause, resume, and end-wrapup commands with conditional pause timeout and audit logging | Implemented; no automated live mutation of real agents |
| runtime capability discovery | Safe account-level reads probe Queue configuration, aggregate Agent status, and Queue statistics independently; only three booleans enter the public contract, with a one-minute account cache | Implemented and live verified as configuration available, live controls unavailable, and statistics unavailable |

The Queue additions remain virtual projections from the redacted response
`data` object in `switch_json`; normalized MySQL columns are reserved for the
existing searchable operational fields. An isolated authenticated lifecycle
passed create, edit, clear, and cleanup against the connected Switch.

The installed Queue POST handler calls `crossbar_doc:load_merge`, whose runtime
implementation retains only private fields from the stored document before
merging the submitted public object. GridPBX therefore cannot rely on a full
POST to preserve unknown public data. Focused SDK evidence now covers the
authoritative GET/POST sequence, safe unknown preservation at all three Queue
levels, hidden URL preservation, create-only priority retention, and removal
of private/redacted values and raw roster IDs. The corrected path also passed
the focused public API update checks and a disposable isolated browser Queue
lifecycle. The installed Monster checkout has no Queue/Agent form, so no
Monster-only assumptions were added.

The Queue presentation now uses the shared Basic/Advanced control. Basic maps
the schema's `name`, `strategy`, and public `moh` reference together with the
separately managed public Extension roster. Advanced maps the runtime tuning,
caller behavior, `max_priority`, `announce`, and nested `announcements`
controls. This is an installed-schema/runtime semantic grouping, not a claimed
Monster mapping; the reference checkout contains only a Queue icon. Existing
hidden URL fields and safe unknown JSON remain preserved by the established
merge path and never enter either tab or the public contract. The latest
focused rerun passed two Queue component tests, Vue and E2E TypeScript checks,
and an isolated non-mutating headless Playwright check. Client and API errors
open the owning tab, including an Advanced error already present when the form
opens.

The 2026-08-31 runtime capability audit found `cb_queues` and `cb_agents`
loaded, but the ACDc OTP application was not running and
`cb_acdc_call_stats` was not loaded. Read-only account probes returned `200`
for Queue and Agent configuration, `500` for aggregate Agent status and Agent
statistics, `503` for Queue statistics, and `404` for `acdc_call_stats`. The
public Queue options response reduced that evidence to
`configuration_available = true`, `live_agent_controls_available = false`,
and `statistics_available = false`; no raw response body, account ID, Queue
ID, or Agent ID crossed the API. Focused SDK, Laravel API, Zod/store/component,
Vue type, E2E TypeScript, and one isolated headless check passed. The browser
confirmed Queue creation stayed available, live controls were visibly gated,
and no Queue or Agent mutation request was sent. No live Agent state or call
statistics were changed or claimed as verified.

The 2026-09-01 responsive/accessibility pass did not alter this field matrix,
public contract, or preservation path. It added named tables with scoped
headers and loading state, keyboard-operable Queue records, disabled Agent
controls when live commands are unavailable, a labelled Extension roster,
announced errors, and mobile-safe layout. Two focused component tests, both
TypeScript checks, focused lint, and one isolated 390-pixel Playwright check
passed. That browser check sent zero Queue, Agent, or Queue-sync mutations and
reported no errors; raw Switch identifiers remained outside the browser
contract.

### 12.1 Presence and parked-call operational matrix

| Endpoint or operation | Treatment | Current status |
| --- | --- | --- |
| account Presence summary | Probe availability only; the response is SIP subscription diagnostics, not authoritative live User status | Implemented and live verified |
| live User presence state | Hidden until a reliable state source and freshness contract are identified | Capability-gated |
| Presence set/reset | Hidden pending public Device/User UUID mapping, authorization, immutable audit, and command semantics | Capability-gated |
| parked-call summary | Read-only active-slot count; raw slots and slot numbers are discarded | Implemented and live verified |
| park/retrieve actions | Not exposed because installed Kazoo performs them through a live callflow media leg and provides no REST action | Capability-gated |
| caching and persistence | Ten-second account-scoped cache; no durable MySQL projection of transient runtime payloads | Implemented |

The public route accepts only the GridPBX account UUID. The server resolves the
raw Switch account reference internally and returns `observed_at`, three fixed
Presence capability booleans, two fixed parking capability fields, and the
nullable aggregate count. The strict API/Zod contract contains no Switch
account ID, call ID, Presence ID, SIP contact, subscription data, Switch URI,
node, caller identity, media field, or raw slot payload.

Installed-runtime inspection confirmed `cb_presence` and `cb_parked_calls`
were loaded and `omnipresence` was running. Live account reads returned `200`
for both endpoints; the parking summary contained zero active slots at the
observation time. Because the feature is read-only, unknown source fields are
neither rewritten nor deleted. Focused SDK and Laravel checks, strict Zod/store
checks, Vue and E2E TypeScript typechecks, and one isolated authenticated
headless browser test passed. This is an initial operational-status foundation,
not completion of live presence or parked-call actions.

### 12.2 Webhook resource capability matrix

| Endpoint or operation | Treatment | Current status |
| --- | --- | --- |
| global Webhook event catalog | Availability and aggregate event count only; event metadata and modifiers remain private | Implemented and live verified |
| account Webhook configuration inventory | Read-only configured and enabled counts; URLs, names, raw IDs, custom data, modifiers, and descendant settings are discarded | Implemented and live verified |
| create, edit, delete, enable, disable, and bulk re-enable | No public mutation until outbound delivery is hardened and separately authorized | Capability-gated |
| event selection, internal-leg and descendant inclusion, custom data, and retry policy | Not exposed because these controls can expand sensitive event delivery or overwrite payload fields | Capability-gated |
| attempts, samples, and delivery history | Not exposed; installed attempt records can include destination URI, raw hook ID, request/response headers, request/response bodies, and client errors | Capability-gated |
| caching and persistence | Ten-second account-scoped cache shared with operational status; no durable MySQL projection | Implemented |

The installed `webhooks.json` schema requires `hook`, `name`, and `uri`, permits
HTTP GET/POST/PUT, form or JSON bodies, zero to four configured retries,
arbitrary string `custom_data`, internal-leg inclusion, and optional descendant
delivery. Crossbar stores the documents in the global Webhooks database and
scopes account reads through the private Switch account identifier. The public
GridPBX endpoint instead accepts only the GridPBX account UUID and returns the
fixed Webhook fields `event_catalog_available`, `available_event_count`,
`configuration_summary_available`, `configured_count`, `enabled_count`, and
two literal-false mutation/history capabilities.

Installed-runtime and live read-only inspection found nine available event
types, zero configured hooks for the selected account, zero attempts, and seven
sample payloads. Event payloads may contain caller data, call/account/resource
identifiers, custom channel and application variables, SIP data, SMS/MMS
bodies, or broad notification content. Delivery sends raw `X-Hook-ID` and
`X-Account-ID` headers without a signature. URI validation blocks only the
configured literal-host and direct-IP blacklist; it does not resolve hostnames
or recheck every destination after redirects. Only a connect timeout is passed
by this runtime path, retries use fixed sleeps, and failed-attempt persistence
retains raw request and response material. Crossbar attempt reads strip only
document IDs, so their remaining content is not a safe public contract.

Focused SDK, Laravel API, strict Zod/store, Vue type, and E2E TypeScript checks
passed. One isolated authenticated headless check passed and confirmed the
aggregate Webhook card, fixed disabled controls, no operational mutation, and
absence of raw Webhook configuration or delivery data. No Webhook was created,
changed, enabled, disabled, deleted, or delivered during this audit. This is a
safe capability/inventory foundation, not Webhook CRUD or delivery-health
completion.

## 13. Conference field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name`, `owner_id` | Required bounded name; public account Extension UUID resolved to a Switch User reference | Implemented |
| `conference_numbers`, `member.numbers`, `moderator.numbers` | Present, unique digit lists; empty lists remain schema-valid | Implemented and live verified |
| `member.pins`, `moderator.pins` | Write-only arrays of up to 20 unique 1–32 digit values per role; replacement or explicit clear; never returned or persisted in plaintext | Implemented and live verified |
| `member.join_muted`, `member.join_deaf`, `member.play_entry_prompt` | Explicit member behavior controls | Implemented |
| `moderator.join_muted`, `moderator.join_deaf` | Explicit moderator behavior controls | Implemented |
| `max_participants`, `language` | Bounded capacity and prompt-language controls | Implemented |
| `max_members_media` | Public account Media UUID resolved server-side; unresolved existing Switch media is preserved | Implemented |
| `play_entry_tone`, `play_exit_tone` | Schema boolean/string union represented as standard, silent, projected Media, or opaque current-custom preservation | Implemented and live verified |
| `play_name`, `play_welcome`, `require_moderator`, `wait_for_moderator` | Explicit room and moderator behavior controls | Implemented |
| `profile_name`, `caller_controls`, `moderator_controls` | Bounded advanced profile references; runtime defaults and references remain named Switch configuration rather than raw profile JSON | Implemented and live verified |
| `bridge_username`, `bridge_password`, `domain` | Infrastructure-owned values; not accepted from account operators | Intentionally hidden |
| `flags[]` | External-application metadata; not operator-editable | Intentionally hidden; native recursive PATCH preservation live verified |
| `focus` | Read-only media-server location | Intentionally read-only |
| `controls`, `profile` | Arbitrary nested Switch configuration; no raw JSON editor in the simplified UI | Intentionally advanced/opaque; unknown nested `controls` preservation live verified |
| lock/unlock | Separate authorized command endpoint refreshes Switch runtime state, rejects locking an inactive room, records accepted/failed audit events, and triggers projection reconciliation | Implemented; focused SDK/API/UI tests passed |
| participant mute/unmute, deaf/undeaf, and kick actions | Runtime participants are fetched on demand and reduced to a strict public allowlist. A short-lived encrypted handle binds the raw participant ID to the account and Conference; every mutation resolves the handle server-side, verifies the participant is still active, audits safe metadata, and refreshes observed state | Implemented; focused SDK/API/UI tests passed |
| whole-room and participant media play | Kazoo accepts either a Media ID or URL. GridPBX accepts only an account-scoped public Media UUID, resolves the raw Media ID server-side, requires audio/streamable capability plus an active-room or current-participant preflight, and requires confirmation in both the strict Zod command and Laravel request contracts. The 202 response represents acceptance, not completed playback | Implemented with focused SDK/API/UI and isolated headless acceptance coverage. Raw URL fields and URL-shaped media references are rejected; audible playback remains unverified without an active media-server room |
| conference dial-out | Kazoo accepts Device/User IDs, raw numbers, arbitrary SIP URIs, participant flags, caller ID, profile, target call ID, and timeout; external number legs apply billing and limits | Intentionally disabled pending public-only destinations, caller-ID authorization, quote/limit policy, rate limits, confirmation, idempotency, compensation, and authoritative call-result reconciliation |
| bulk participant controls | Kazoo's native participants endpoint supports room-wide mute/unmute and deaf/undeaf by filtering non-moderators whose speak/hear state needs changing. GridPBX shows the exact eligible count, requires explicit confirmation, re-reads the room under a lock, rejects changed room/target counts, sends one atomic Kazoo command, records safe audit counts, and performs four bounded live observations over 750 ms. The UI distinguishes fully observed, partially/pending, and changed-room outcomes. Kazoo does not return trustworthy per-participant completion, so GridPBX never turns an unobserved asynchronous state into an invented failure | Implemented with focused SDK/API/Zod/Vue and isolated headless coverage. Bulk kick remains disabled |

The typed sound fields are read from the redacted response `data` stored in
`switch_json`; no JSON-derived Conference column was added to MySQL. Public
owner input is an account-scoped Extension UUID that Laravel resolves to the
raw 32-character Kazoo `owner_id`; responses return only the public UUID.
Updates use installed Kazoo recursive `PATCH`, omit unchanged write-only PIN
arrays, and send `null` only for explicit deletion of nullable managed fields.
The shared Device-style form tabs now preserve Monster's progressive-disclosure
workflow instead of flattening all non-Basic fields together. The outer Basic
view contains identity plus member and moderator access. Advanced reveals the
inner `Basic`, `Options`, and `Conference Server` sections: role access remains
available in Basic, participant/moderator behavior and sounds live in Options,
and access identifiers, capacity, language, and safe named profile references
live in Conference Server. The latter fields extend Monster's older template
only where the installed Kazoo schema supplies an explicit typed contract.
Opaque nested profile/control JSON remains hidden. The established disposable
lifecycle evidence remains the payload proof; this presentation-only change
uses focused component, type, and non-mutating browser verification.
The 2026-08-31 isolated authenticated lifecycle passed create, selective PIN
replacement, advanced-field reopen, unknown/external field preservation, and
cleanup. Its MySQL projection was independently confirmed soft-deleted and the
active Switch collection had no exact-name match.

## 14. Temporal Rule and Rule Set field-level matrix

### Temporal Rule

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name`, `cycle` | Upstream-required name and cycle enum | Implemented |
| `interval` | Optional upstream field; API/UI default 1, integer minimum 1, no invented maximum | Implemented |
| `start_date` | Optional public `YYYY-MM-DD`, converted server-side to/from Gregorian seconds | Implemented |
| `time_window_start`, `time_window_stop` | Optional integer seconds, 0–86400 | Implemented |
| `days[]` | Optional unique day-of-month values, 1–31; invalid text is reported rather than filtered | Implemented |
| `wdays[]` | Optional weekday enum; legacy `wensday` reads normalize to `wednesday` | Implemented |
| `month` | Optional month integer, 1–12, shown for yearly cycles | Implemented |
| `ordinal` | Optional enum `every`, `first`–`fifth`, or `last`, shown for monthly/yearly patterns | Implemented |
| `enabled` | Operational override only; prohibited in CRUD and changed through confirmed audited `true`/`false`/`null` PATCH commands | Implemented and live verified |
| `flags[]` | External-application metadata prohibited from operator input; normal edits omit it from native recursive PATCH while compensation retains the projected value | Implemented preservation boundary |
| effective status | GridPBX schedule projection evaluated in the account timezone and kept distinct from the manual override | Implemented |

### Temporal Rule Set

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required bounded editable name | Implemented |
| `temporal_rules[]` | Non-empty ordered membership; public Rule UUIDs resolve to account-scoped Switch identifiers | Implemented and live verified |
| `flags[]` | External-application metadata prohibited from operator input and omitted from normal native recursive PATCH edits | Implemented preservation boundary |
| enable/disable/reset | Confirmed command applies to every resolved member under a lock with partial-failure compensation | Implemented and live verified |
| Callflow and membership lifecycle | Delete is rejected while the Rule Set is referenced by temporal routing; successful deletion removes membership rows so member Rules can be removed safely | Implemented and live verified |

Both right-side panels use domain composables, Zod, Headless UI where an
interactive primitive adds value, shared red invalid controls, and inline-only
field errors. Ordinary edits preserve existing operational overrides, and no
new JSON-derived MySQL columns were added; the complete redacted response
`data` remains in `switch_json`. Installed-schema review confirmed that
GridPBX's `date` and `daily` choices are valid even though Monster exposes only
weekly/monthly/yearly. Rule and Rule Set edits use recursive PATCH; nullable
managed schedule fields are explicitly deleted when cleared, while unknown
fields and omitted external flags remain server-side. The 2026-08-31 isolated
lifecycle passed Rule and Rule Set create/edit/reopen, override force/reset,
ordered membership, and cleanup. Independent checks confirmed both projections
soft-deleted and zero exact-name active Switch matches.

The presentation re-audit confirms both forms are intentionally Basic-only.
Monster renders all Rule fields in its sole Basic panel and hides the generic
Basic/Advanced buttons because only one tab exists. Its Rule Set editor has no
tab control and contains only name plus ordered membership. This matches the
installed schemas: override `enabled` behavior remains in separate operational
controls, while external flags and unknown values stay hidden and preserved.
The focused panel tests assert that neither compact form grows an empty
Advanced tab.

A focused 2026-09-01 presentation pass added accessible names and scoped
headers to both inventories, explicit loading/error announcements,
keyboard-operable record controls, and table-local overflow. An isolated
non-mutating headless run verified the existing conditional schema fields and
inline validation plus 390-pixel containment, zero temporal writes, and no
browser or server errors.

## 15. Blacklist field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required trimmed name, maximum 128 characters, with matching Zod and Laravel validation | Implemented |
| `numbers.<caller-id>` | Multiline UI normalized to unique keys; GridPBX applies an intentional E.164 policy and the Switch DTO emits an object even when empty | Implemented and package-tested |
| `should_block_anonymous` | Explicit boolean control | Implemented |
| `flags[]` | External-application metadata prohibited from operator input and preserved from redacted `switch_json` across updates and compensation | Implemented and feature-tested |
| account `blacklists[]` activation | `is_active` is a virtual form field coordinating the separate account-setting update; it is not added to the Blacklist Switch document | Implemented |
| deletion | Active lists must first be deactivated; Laravel rechecks the rule before deleting | Implemented |

The Blacklist form uses a right-side slide-over, domain composable, Zod,
shared red invalid controls, and inline-only field errors. Focused component,
store, Laravel, Switch package, and isolated authenticated Playwright checks
pass without creating a live Blacklist.

Blacklist intentionally remains Basic-only: Monster defines one Basic view
with no Advanced selector, and every operator-safe installed-schema field is
already present. External flags remain hidden and preserved, while account
activation is a separate coordinated setting rather than a Blacklist Advanced
field.

A focused 2026-09-01 presentation pass added accessible inventory structure,
keyboard record controls, explicit loading/error announcements, mobile-safe
header and search actions, and table-local overflow. One isolated stubbed
public-UUID scenario verified the 390-pixel inventory and form, keyboard edit
opening, inline validation, zero writes, and clean browser/server state.

## 16. Fax Box field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required trimmed name, maximum 128 characters | Implemented |
| `owner_id` | Public Extension UUID resolved server-side; null leaves the box unassigned | Implemented |
| `caller_id` | Headless UI choice sourced from projected account phone numbers; an existing unprojected value is retained safely | Implemented |
| `caller_name`, `fax_header`, `fax_identity` | Nullable bounded identity controls | Implemented |
| `fax_timezone` | Supported timezone choice; null inherits the account default | Implemented |
| `retries` | Integer 0–4 using the current schema default of 1 | Implemented |
| `media.fax_option` | Explicit T.38 boolean | Implemented |
| `custom_smtp_email_address` | Optional validated SMTP email address; generated `smtp_email_address` remains read-only | Implemented |
| `smtp_permission_list[]` | Unique bounded pattern strings; Switch remains authoritative for provider-specific regex acceptance | Implemented |
| `notifications.inbound.email.send_to`, `notifications.outbound.email.send_to` | Unique bounded email lists | Implemented |
| `notifications.*.callback` | Preserved from redacted `switch_json`; hidden until an outbound URL and SSRF policy is approved | Intentionally policy-gated |
| `notifications.*.sms` | Preserved from redacted `switch_json`; hidden until provider capability and messaging policy are confirmed | Intentionally capability-gated |
| `flags[]` | External-application metadata prohibited from operator input and preserved during full writes | Implemented preservation boundary |
| `attempts` | System-owned retry state reset by Switch on writes; never operator-editable | Intentionally read-only |
| deletion and document access | Callflow dependencies block Fax Box deletion; fax documents stream through an authorized audited API | Implemented |
| outbound Send Fax | Schema permits multipart documents or server-side HTTP GET/POST retrieval. Crossbar returns 202 before background conversion/storage finishes; URL fetch lacks a proven SSRF boundary and installed defaults retain original/TIFF/PDF copies | Switch-supported but disabled pending upload/content/malware limits, URL prohibition or hardened egress, caller-ID/destination authorization, billing, rate limits, idempotency, audit, and reconciliation |
| inbox Forward | Copies the retained inbound document into a random new outbound job and resets results/attempts | Switch-supported but disabled pending destination confirmation, retention authority, duplicate-safe execution, audit, and reconciliation |
| outbox Resubmit | Copies the retained outbox document into a random new outbound job and resets results/attempts | Switch-supported but disabled pending exact-message confirmation, duplicate-safe execution, abuse controls, audit, and reconciliation |
| message DELETE | Permanently deletes the Fax document record | Switch-supported but disabled pending retention, legal hold, exact-message confirmation, authorization, immutable audit, and projection reconciliation |
| attachment DELETE | Permanently deletes attachments independently of message metadata | Switch-supported but disabled pending binary-retention/legal-hold policy and message/document reconciliation |
| public operation capabilities | Collection-level object exposes only `switch_supported`, fixed `enabled = false`, and safe reasons for the five operations; strict Zod rejects extra fields | Implemented explicit capability gate |

The Fax Box slide-over uses a domain composable, Zod, Headless UI choices,
shared invalid controls, and inline-only errors. No new MySQL fields were added
for hidden JSON values. Focused component, Laravel, Switch package, and isolated
authenticated Playwright checks pass without creating a live Fax Box.

Its shared Basic/Advanced presentation follows Monster's actual view toggle:
Basic contains name, public owner UUID, and inbound/outbound email recipients;
Advanced contains caller/Fax identity, SMTP settings, retries, and timezone.
The installed-schema-only T.38 boolean also belongs to Advanced. Callback and
SMS objects, external flags, attempts, raw owner IDs, and unknown nested values
remain absent from the UI and preserve their existing server-side merge
behavior. Focused component, Vue, E2E TypeScript, and mocked non-mutating
browser checks passed with error-aware tab selection.

The live Fax-operation audit found empty available Fax Box, inbox, and outbox
collections, while the active outgoing-job collection returned HTTP 503. The
effective defaults validate caller ID and serialize by destination but also
allow ten account jobs, one-hour job control, URL retrieval, broad
content-type prefixes, five storage retries, and original/TIFF/PDF retention.
No write or attachment request was issued. Installed support is therefore not
an enabled GridPBX operation.

## 17. Phone Number field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| number identity, `state`, `used_by`, carrier, active `features[]` | Normalized searchable MySQL projection using a server-owned numeric primary key and public UUID; Switch number remains a display value | Implemented |
| `_read_only.features.available` | Preferred current runtime feature capability source; never writable | Implemented |
| `_read_only.features_available` / root `features_available` | Version-aware compatibility fallback for older Switch responses | Implemented and package-tested |
| `cnam.display_name`, `cnam.inbound_lookup` | Typed allowlisted detail projection. Installed schema is a 1–15 character name plus boolean inbound lookup; raw provider state and unknown nested fields remain private | Implemented read-only |
| `e911.status`, caller name, address, and notification emails | Typed allowlisted virtual values from redacted `switch_json`; provider activation time, location IDs, coordinates, legacy/provider state, and unknown nested fields are not exposed | Implemented read-only |
| `porting.requested_port_date`, `porting.service_provider` | Minimal Phone Number operational summary; billing identifiers, comments, customer/billing details, and Port Request IDs remain hidden. System Status separately reports only filtered Port Request collection availability | Implemented read-only capability foundation |
| callflow assignment | Resolved through the Callflow domain with public UUIDs; not duplicated as a Phone Number CRUD control | Implemented |
| CNAM mutation | Must use recursive `PATCH`, never public-document-resetting `POST`; requires a target provider completion contract, quote/charge confirmation, authorization, immutable audit, duplicate-safe recovery, and authoritative reconciliation. The live account inherits notification-only `knm_cnam_notifier` and has no numbers for disposable verification | Intentionally capability-gated after installed-runtime and live read-only audit |
| E911 mutation | Must use recursive `PATCH` and preserve provider-owned fields; requires configured provider readiness, verified transport, emergency-caller-ID dependency enforcement, address/privacy and notification policy, server-owned geocode choices, quote/charge confirmation, exact-number removal confirmation, immutable audit, duplicate-safe compensation, and authoritative reconciliation. The live deployment inherits uncredentialed Dash E911, leaves emergency-CID validation disabled, and has no number for disposable verification | Intentionally capability-gated after installed-runtime and live read-only audit |
| purchase, reserve, activate, port, release | Separate carrier commands, never generic CRUD; require provider capabilities, quote/billing behavior, authorization, confirmation, and audit | Intentionally capability-gated |
| SMS/MMS and carrier-specific features | Must be discovered from the connected provider rather than inferred from the base schema. System Status now reports only independent account endpoint availability; the live deployment reports both unavailable | Read-only capability foundation; mutations gated |

The Phone Number detail slide-over now shows the safe schema-backed state and
an explicit operational capability matrix. “Available” from Switch does not
mean “writable” in GridPBX: schema presence and carrier support are distinct
from authorization, billing, and compliance policy. Focused Switch package,
Laravel, Vue, TypeScript, and isolated authenticated Playwright checks pass
without executing any carrier mutation.

The 2026-08-31 form-drift re-audit found no missing Advanced fields because a
generic Phone Number edit form is deliberately absent. The installed schema
and runtime continue to require dedicated workflows for CNAM, E911, porting,
activation, reservation, purchase, and release. Callflow assignment remains
owned by the Callflow domain. The read-only detail therefore has no artificial
Basic/Advanced selector; those tabs remain reserved for writable field groups.
The public detail contract still exposes only
account-scoped GridPBX and Callflow UUIDs plus allowlisted operational values;
provider IDs, billing data, raw number documents, and internal primary keys
remain private. Five package tests / 26 assertions, three API tests / 37
assertions, the one detail component test, isolated E2E TypeScript typecheck,
and the one read-only headless detail test passed without a Switch write; the
focused UI checks also lock the absence of Basic/Advanced tabs.

For CNAM specifically, `_read_only.features.available` means Kazoo selected the
feature as allowable for that number; it is not a provider acknowledgement.
The installed default provider publishes an asynchronous notification for an
outbound-name change outside dry-run mode and returns no carrier completion
state. A future command must retain the projected raw document server-side,
merge only the two typed CNAM fields through Crossbar `PATCH`, and resynchronize
before reporting an authoritative outcome.

For E911, the installed provider contract is not uniform. Dash can return
multiple geocoded choices and can deactivate local feature state despite an
upstream removal error; Telnyx replaces addresses through a remove/create/
assign sequence with asynchronous cleanup; and Vitelity has separate provider
calls. The current Dash HTTP path also disables certificate verification. The
live account has no E911 provider override or phone numbers, the global Dash
credentials are absent, and Stepswitch's `ensure_valid_emergency_cid` safeguard
is unset. Schema availability is therefore not provider or routing readiness.

## 18. Media field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required trimmed name, 1–128 characters, with matching Zod and Laravel validation | Implemented |
| `description` | Optional 1–128 character schema value; blank removes the field | Implemented |
| `language` | Optional bounded product input with `en-us` create default | Implemented |
| `streamable` | Explicit boolean control | Implemented |
| `media_source` | Operator upload workflow always creates `upload`; existing `recording` and `tts` values are preserved and cannot be injected through metadata CRUD | Implemented boundary |
| raw audio | Required MP3/WAV/OGG create upload and confirmed replacement, maximum 5 MB; streamed through the authorized API and never duplicated in MySQL | Implemented |
| `content_type`, `content_length` | Switch-owned values are retained during metadata updates when present. The connected deployment returns `content_type` but omits `content_length` from Media detail even immediately after an 844-byte upload; GridPBX preserves the authoritative nullable value rather than inventing one | Implemented and live-verified |
| `prompt_id`, `source_id`, `source_type`, `tts` | Hidden schema-owned values preserved through typed DTOs during metadata updates; not accepted from operator payloads | Implemented preservation boundary |
| unknown safe public fields | Retained server-side from redacted `switch_json` during replacement writes, including unknown nested TTS siblings; modeled, private/read-only, and redaction-marker values are excluded | Implemented and live-verified |
| generated TTS and callflow recording | Separate provider/runtime operations rather than generic metadata CRUD | Intentionally capability-gated |
| account music on hold | Public Media UUID resolved server-side; Headless UI selection may set or clear the account reference | Implemented |
| deletion | Dependency summary covers music on hold, voicemail greetings, and Callflows before deletion | Implemented |

The Media forms use domain composables, Zod, shared red invalid controls,
inline-only API errors, and `novalidate`. The account-default selector uses the
viewport-bounded Headless UI listbox. A 2026-08-31 drift re-audit corrected the
installed full-document `POST` boundary so metadata edits preserve safe unknown
public fields, nested TTS siblings, and Switch-owned content metadata entirely
server-side. Focused Switch package, Laravel, Vue, TypeScript, and isolated
authenticated Playwright checks pass. A disposable 844-byte WAV lifecycle also
verified upload, production metadata edit, public/raw UUID separation, nested
unknown-field preservation, deletion, two soft-deleted audit projections, zero
active MySQL projections, and zero matching active Switch Media documents.

The Media editor now uses the shared Basic/Advanced presentation confirmed by
Monster's Basic and Options views. Basic contains safe upload metadata and the
audio file; Advanced contains `streamable`. TTS generation remains gated, and
existing media source, TTS, prompt/source, content, raw-ID, and unknown values
stay on the established private preservation path. Focused component, Vue,
E2E TypeScript, and mocked non-mutating browser checks passed.

### 18.1 Caller-ID List field-level matrix

| Schema path | Treatment | Current status |
| --- | --- | --- |
| list `name` | Required bounded name in Basic | Implemented |
| list `description` | Optional 1–128 character metadata in Advanced; blank removes the field | Implemented |
| list `org` | Optional organization metadata in Advanced, represented publicly as `organization` | Implemented |
| entry `number` / `pattern` | Mutually exclusive number/prefix or bounded safe Switch-regex input in Basic | Implemented |
| entry `displayname` | Optional display label, represented publicly as `display_name` | Implemented |
| entry `list_id` | Schema-required raw parent reference supplied privately by the Switch adapter | Implemented private mapping |
| entry `firstname`, `lastname`, `type`, `profile`, and unknown safe fields | Not part of the number/pattern workflow; retained by an authoritative safe read-merge-write and never accepted as hidden preservation input | Implemented preservation boundary |
| public identity | Account-scoped List and entry UUIDs only; raw Switch IDs and database keys are not returned | Implemented |

The installed schemas provide the field authority. This Monster checkout has
no standalone Caller-ID List editor, so GridPBX groups by resource semantics:
Basic contains the name and matching entries; Advanced contains optional list
metadata. The shared tab selector routes client and API errors to the owning
tab, preferring Basic if both groups fail. Focused component and type checks
plus one isolated mocked headless browser check passed without a live mutation.
Installed List and entry updates use full-document `load_merge`; the SDK now
reads the authoritative document and preserves safe unknown public fields while
excluding private/read-only/redacted data and allowing modeled values to win.
The focused SDK check passed two tests / 14 assertions.

A focused 2026-09-01 presentation pass added accessible inventory structure,
keyboard record controls, loading/error announcements, mobile-safe page and
entry actions, and table-local overflow. One isolated stubbed public-UUID run
verified the 390-pixel inventory and Basic/Advanced form, keyboard edit opening,
safe-regex validation, zero writes, and no browser or server errors.

## 19. Call activity field-level matrices

### 19.1 CallDetailRecord

| Field or operation | Treatment | Current status |
| --- | --- | --- |
| public `id` | GridPBX UUID only; internal `call_detail_record_id` and Switch resource IDs never cross the API | Implemented |
| `call_id`, `interaction_id`, direction, parties, URIs, start time, duration, billing duration, hangup cause, disposition | Allowlisted searchable/read-only projection | Implemented |
| `answered` | Derived from positive billable seconds | Implemented |
| date range | Inclusive UTC calendar dates with a seven-day default import window and 31-day maximum | Implemented |
| search, direction, outcome, hangup cause, duration range | Indexed account-scoped filters with matching Zod/Laravel bounds and reversed-range errors | Implemented |
| extension relationship | Account-scoped projection exposed through public Extension UUID | Implemented |
| related recordings | Eager-loaded safe summaries using public Recording UUIDs; no internal keys or media URLs | Implemented |
| raw CDR payload | Explicit allowlist only; costs, rates, authorization IDs, SIP headers, DTMF, SDP, recording URLs, and media lists are excluded | Implemented boundary |
| mutation or deletion | CDRs are historical Switch-owned records, not GridPBX CRUD objects | Intentionally read-only |
| production scheduling, partitioning, archival, retention | Requires approved account volume and privacy policy | Policy-gated |

### 19.2 Recording

| Field or operation | Treatment | Current status |
| --- | --- | --- |
| public `id` and normalized metadata | GridPBX UUID plus allowlisted call, party, timing, format, size, and source summaries | Implemented |
| date, direction, audio availability, duration, and search filters | Account-scoped bounded filters with matching Zod/Laravel validation and inline red invalid controls | Implemented |
| CDR and Extension relationships | Public UUID links in both directions; internal database keys remain server-side | Implemented |
| `switch_json` | Complete redacted Recording `data` snapshot retained in MySQL but never returned raw | Implemented boundary |
| binary audio | Kept in Switch/provider storage and streamed through an authenticated account-scoped endpoint with byte ranges, private/no-store caching, MIME controls, and audit logs | Implemented |
| playback/download | Right-side detail workflow; only projected records with available audio may request content | Implemented |
| delete, automatic retention, and provider cleanup | Disabled until legal retention and external-storage cleanup contracts are approved | Policy-gated |

Both pages use domain composables, Headless UI-backed selects, `novalidate`,
Zod validation, and shared red invalid-control styling. Isolated authenticated
Playwright verifies reversed ranges and reciprocal public-UUID navigation
without console, page, or server errors.

The 2026-08-31 presentation re-audit keeps both detail panels intentionally
single-view and read-only. CDR runtime methods are GET-only. Although the
installed Recording endpoint also exposes individual DELETE, that operation
owns retention and storage-cleanup risk and remains separately policy-gated;
it is not an Advanced form field. Monster's Call Logs reference workflow has
filters, details, interaction legs, and export but no entity editor. The UI's
“Advanced filters” disclosure therefore remains a query refinement control,
not a Basic/Advanced entity selector. Focused isolated browser coverage asserts
that neither detail panel exposes Basic/Advanced tabs or edit/delete actions.

## 20. Account field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| public `id` | GridPBX UUID only; internal `account_id` and Switch account ID never cross the API | Implemented |
| `name`, `realm`, `timezone`, `enabled` | Name and schema-bounded timezone are typed administrator settings; realm remains read-only; enabled uses a separate exact-name-confirmed administrator command | Implemented/gated by field |
| organization relationship | Public organization UUID and display name, scoped through authenticated membership | Implemented |
| header account search and selection | Monster's Account Jump filters loaded children, optionally calls Kazoo `search/multi` across descendant name/realm/raw ID, and changes its active masquerade account. GridPBX filters only the authenticated user's already-authorized projected accounts by safe name, realm, or organization fields and stores only a public Account UUID; it performs no raw-ID search, hierarchy discovery, Switch request, or masquerading | Implemented safe application-shell subset |
| Kazoo account `tree`, `parents`, `children`, and `descendants` | Raw Switch account IDs remain private reconciliation keys. Parent, ancestor, child, and descendant relationships are exposed only when projected into the same GridPBX organization and only through public Account UUIDs | Implemented read projection |
| descendant coverage | The Kazoo `descendants_count` is compared with projected descendants; unresolved accounts are exposed only as a count until an authorized discovery request issues short-lived opaque references | Implemented |
| onboard existing descendant | Reseller administrators select an opaque actor/scope-bound reference, type the exact account name, and acknowledge inherited organization access. GridPBX projects the existing Kazoo account, audits the operation, and queues service synchronization without exposing or accepting a raw Switch ID | Implemented confirmed operation |
| create/move/delete account or promote/demote reseller | Separate high-risk Kazoo operations requiring platform/reseller policy, dependency and billing checks, confirmation, compensation, and audit | Capability/policy-gated |
| projected resource counts | Tenant-scoped Extension, Device, Phone Number, Callflow, Voicemail, Queue, Media, and Recording counts | Implemented |
| `org`, `language` | Typed nullable identity/default settings with explicit clear semantics | Implemented |
| `music_on_hold.media_id` | Managed by the existing account Media workflow using a public Media UUID | Implemented in Media domain |
| `blacklists[]` | Managed by the Blacklist domain and account activation workflow | Implemented in Blacklist domain |
| `call_waiting.enabled`, `do_not_disturb.enabled`, `caller_id_options.outbound_privacy`, `caller_id_options.show_rate`, `ringtones.internal`, `ringtones.external` | Typed calling defaults; privacy includes a virtual Switch-default choice that sends JSON `null` through Account `PATCH` to remove the optional property; ringtone headers use the installed 256-character limit | Implemented |
| `caller_id.internal` | Bounded internal caller-ID name and number | Implemented |
| `caller_id.external` | Name plus account-owned Phone Number public UUID resolved server-side; unresolved current values are preserve-or-clear | Implemented |
| `caller_id.emergency` | Name plus account-owned E911-enabled Phone Number public UUID resolved server-side | Implemented |
| `caller_id.asserted` | Trusted-network identity and realm | Intentionally administrator/capability-gated |
| `call_restriction.<classification>.action` | Dynamic connected-Switch classifications with typed `inherit`/`deny` choices; stored unknown classifications remain editable | Implemented |
| `call_recording.account`, `call_recording.endpoint` | Guided direction/network matrices with bounded schema fields; existing storage URLs remain hidden and are preserved server-side | Implemented |
| `dial_plan.system[]`, regex-keyed dial-plan rules | Bounded guided rows with safe regex validation; unknown server-owned rule options are preserved without being exposed | Implemented |
| `formatters.<field>[]` | Ordered guided rules with direction, regex, prefix/suffix/value, strip, and INVITE-format controls; unknown server-owned options are preserved without being exposed | Implemented |
| `preflow.always` | Projected Callflow public UUID selector with explicit unresolved-reference preservation | Implemented |
| `metaflows.binding_digit`, `metaflows.digit_timeout`, `metaflows.listen_on` | Bounded account-level DTMF activation controls | Implemented |
| `metaflows.numbers`, `metaflows.patterns` | Shared guided recursive editor with public Media, Callflow, Device, and Extension UUID references | Implemented for Account, Device, and managed User edits; unsupported/unresolved roots are locked and preserved |
| complete Account `data` / `flags[]` | Redacted snapshot retained in MySQL `switch_json`; external metadata is never ordinary operator input | Implemented projection boundary |
| `notifications.first_occurrence` and delivery state | System-owned notification history | Intentionally read-only |
| `notifications.low_balance`, `topup` | Billing/provider workflow requiring authorization, currency semantics, confirmation, and audit | Provider/policy-gated |
| `voicemail.notify.callback` | Outbound callback workflow requiring URL allowlisting and SSRF policy | Policy-gated |
| `zones` | Infrastructure/reseller routing configuration | Administrator/capability-gated |
| account enable/disable | Dedicated administrator command requiring exact-name confirmation, Switch-first mutation, projection refresh, and audit | Implemented |

The Accounts page uses a main-page projection workspace and an
administrator-only right-side settings panel. Update and refresh use a typed
Switch boundary, then refresh normalized MySQL fields, redacted `switch_json`,
synchronization metadata, and audit history. Raw JSON, internal primary keys,
and the Switch account identifier never cross the public API. Enable/disable
is a separate exact-name-confirmed operation; higher-risk configuration
remains gated.

Reseller administration is a separate read-oriented workspace rather than an
Account Advanced tab. It shows the selected public Account relationship tree,
projection coverage, safe billing-owner/service health, and mutation-preflight
diagnostics. Existing descendant onboarding is a dedicated confirmed operation
because it changes GridPBX organization access; it accepts no raw account ID.
Promotion, demotion, account creation, deletion, and tree movement remain
unavailable. Focused SDK, Laravel, Zod/component, and isolated authenticated
browser checks passed; the browser exposed no raw Kazoo or database key.

The 2026-08-31 form-drift re-audit compared the complete installed
`accounts.json` schema and its `call_waiting`, `caller_id`, `call_recording`,
`dialplans`, `formatters`, and `metaflows` references with `cb_accounts` and
Monster's Account workflows. GridPBX now enforces the installed timezone
length of 5–32 characters, offers `Etc/UTC` rather than the schema-invalid
three-character alias, accepts the full 256-character ringtone limit, and
includes Monster's German and Russian language choices while retaining an
unknown current language as a safe display value. The privacy-default choice
maps to public `null`; the typed Switch DTO emits that null and installed
`kz_json:merge_left/2` removes the nested property during `PATCH`, restoring
inherited behavior without accepting Monster's virtual `inherit` string.

Account updates remain recursive partial patches. Installed
`crossbar_doc:patch_the_doc/2` merges public request fields over the existing
document, so unknown public Account properties and unknown nested siblings are
retained. GridPBX additionally preserves hidden recording storage URLs and
unknown dial-plan, formatter, and metaflow options from the redacted
server-side snapshot. Music on Hold and Blacklist assignment stay in their
dedicated account-scoped workflows. Realm/asserted identity, notification
state, billing/top-up, voicemail callback URLs, and zones remain explicitly
gated. Focused SDK, Laravel, Zod, Vue, E2E TypeScript, and one isolated
headless Account walkthrough passed; the re-audit made no live Account
mutation.

The Account drawer now adds a shared outer Basic/Advanced presentation without
flattening its existing recording target sub-tabs. Basic groups identity,
locale, general calling defaults, ringtones, privacy, and caller identity.
Advanced groups restrictions, recording policy, dial plans, request
formatters, preflow, and metaflow activation/action trees. Monster distributes
these fields across its profile/account, Accounts-manager, and Callflows
settings surfaces rather than one combined Account editor, so the grouping is
based on installed-schema field ownership and those workflow boundaries.
Client and API errors open the owning outer tab. Two focused component tests,
both TypeScript checks, and one isolated mocked headless walkthrough passed;
the public/raw mapping and server-side preservation contracts did not change.

Externally routable numbers entered by future purchasing, porting, CNAM, or
E911 forms must use a shared libphonenumber-grade parser in Vue and an
independent server-side validator, normalize accepted values to E.164, and
require an explicit default region when the user omits a country code. Internal
extensions and projected Phone Number UUID selections are separate concepts and
must not be rejected by public-number validation.

## 21. Page Group guided field-level matrix

The installed `callflows.page_group` schema and compiled runtime are the payload
and behavior authority. Monster confirms the Basic workflow, but GridPBX exposes
only the subset whose public references and fan-out are bounded safely.

| Schema path or operation | GridPBX treatment | Current status |
| --- | --- | --- |
| `audio` | Required guided enum: `one-way` or `two-way` | Implemented |
| `endpoints[]` with `endpoint_type = device` and raw `id` | One to twenty distinct account-scoped public Device UUIDs; Laravel resolves raw Switch resource IDs only for the SDK write | Implemented |
| `endpoints[]` with `endpoint_type = user` or `group` | Runtime expands these references into devices; kept read-only until expansion, deduplication, authorization, and the final fan-out cap can be enforced reliably | Capability-gated |
| endpoint `delay`, `timeout`, and `weight` | Private server-owned values; safe installed values are bounded and preserved but are neither accepted nor exposed publicly | Preservation boundary implemented |
| top-level `timeout` | Private server-owned value, bounded to the installed safe range and preserved; live Crossbar materialized the default `5` | Preservation boundary implemented |
| `barge_calls` | `true` can interrupt active endpoint calls; existing enabled configurations remain private and read-only | Capability-gated |
| `skip_module` | Guided boolean | Implemented |
| unknown node and endpoint properties | Hidden from public API/UI and merged losslessly by the Switch DTO; endpoint preservation is focused-test verified | Implemented boundary |

A 2026-08-30 disposable isolated-headless lifecycle verified one-way creation,
two-way edit, `skip_module`, authoritative reopen, public Device UUID to raw
Switch endpoint mapping, hidden Kazoo timing preservation, browser deletion,
MySQL soft deletion, and no matching active Switch callflow. Crossbar stripped
attempted unknown live endpoint properties, so unknown-field preservation is
claimed only from the focused SDK regression test; direct CouchDB writes were
not used. No media-leg page was originated, so this matrix records a verified
guided foundation rather than full Page Group completion.

## 22. Ring Group guided field-level matrix

The installed `callflows.ring_group` schema and compiled `cf_ring_group`
runtime define the payload and execution contract. Monster confirms the
Device/User/Group ordering workflow but has no installed weighted-random form.
The installed runtime sorts all endpoints without replacement for each
weighted attempt, then uses sequential FreeSWITCH dialing and reshuffles on a
retry. GridPBX now exposes the same three account-scoped member types through
public UUIDs; raw Switch endpoint identifiers remain server-side.

| Schema path or operation | GridPBX treatment | Current status |
| --- | --- | --- |
| `strategy = simultaneous` or `single` | Guided as At the same time or In order | Implemented |
| `strategy = weighted_random` | Guided as Weighted random order. Every member requires an explicit integer weight `1`–`100`; the runtime tries every resolved endpoint once per attempt and reshuffles for each retry | Implemented |
| `endpoints[]` with `endpoint_type = device` and raw `id` | Account-scoped public Device UUID; Laravel resolves the raw Switch ID only for the SDK write | Implemented |
| `endpoints[]` with `endpoint_type = user` | Account-scoped public Extension UUID mapped privately to Kazoo `endpoint_type = user`; runtime ownership expansion remains dynamic | Implemented with explicit runtime caveat |
| `endpoints[]` with `endpoint_type = group` | Account-scoped public Group UUID mapped privately to Kazoo `endpoint_type = group`; nested membership is managed through GridPBX's cycle-protected Group workflow | Implemented with explicit runtime caveat |
| member count and identity | One to twenty distinct type-plus-public-UUID selections. The configured-member cap does not claim a cap on Devices dynamically resolved from Users or Groups at call time | Implemented boundary |
| expanded endpoint deduplication and activity | Kazoo deduplicates the same raw Device/delay/timeout tuple; membership and Device eligibility are evaluated at call time, so synchronized relationship changes may change fan-out without changing the Callflow | Documented runtime behavior |
| endpoint `delay` | Guided integer `0`–`60`; in-order and weighted-random strategies require `0` | Implemented |
| endpoint `timeout` | Guided integer `1`–`60` | Implemented |
| endpoint `weight` | Public only for weighted-random and required as an explicit integer `1`–`100`; rejected for simultaneous/in-order public writes. Existing private weights on other strategies remain preserved | Implemented |
| top-level `timeout` | Server-computed per attempt: maximum `delay + timeout` for simultaneous and sum of endpoint timeouts for in-order or weighted-random; capped at `120`; never accepted or exposed publicly | Implemented |
| `repeats` | Guided integer `1`–`3` | Implemented |
| `ignore_forward` | Strict public boolean. Omitted existing values read as the installed schema/runtime default `true`; enabled maps to FreeSWITCH's fatal outbound-redirect behavior | Implemented and live verified |
| `fail_on_single_reject` | Strict public boolean. Omitted existing values read as disabled (`false`); enabled tells the bridge to stop the remaining legs after one rejection | Implemented and live verified |
| `ringback` | Publicly modeled only as nullable `ringback_media_id`, an account-scoped UUID for synchronized streamable `audio/*` Media. Laravel resolves the private raw Media ID only for the Switch write. URL, special-stream, system-path, unresolved, and non-audio values are rejected or make existing nodes read-only | Implemented and configuration-live verified |
| `ringtones.internal`, `ringtones.external` | Optional bounded phone `Alert-Info` strings. Values are trimmed, limited to 256 characters, and reject CR, LF, and NUL. These are SIP header values, not audio Media | Implemented and configuration-live verified |
| `skip_module` | Guided boolean | Implemented |
| unknown nested `ringtones` keys, endpoint `disable_until`, and other unknown properties | Hidden from public API/UI and merged losslessly by the Switch DTO; unsafe legacy ringback/ringtone values, malformed bridge flags, and other unsupported current shapes are read-only | Preservation boundary implemented |

The relationship follow-through inspected the installed schema and compiled
runtime exports, matching Kazoo source, the Group schema/Crossbar validator,
and Monster's Ring Group workflow. The public form now offers synchronized
Extensions, Devices, and Groups in one ordered selector. Laravel verifies
account ownership, maps those UUIDs to Kazoo `user`, `device`, and `group`
endpoint types immediately before mutation, and the public resolver maps the
authoritative response back without exposing raw IDs. The SDK accepts only
those three types, exact bounded timing/weight fields, and distinct type/ID
pairs. Focused SDK, Laravel validator/mutation/resolver, Zod, and component
regressions cover the mixed relationship contract. Dynamic User/Group fan-out
is Kazoo runtime behavior; the UI does not misrepresent the 20 configured
members as a resolved-device cap.

A 2026-08-30 disposable isolated-headless lifecycle verified creation below
Page Group, simultaneous-to-in-order editing, delay reset, bounded timeout and
attempts, `skip_module`, authoritative reopen, public Device UUID to raw Switch
endpoint mapping, computed top-level timeout, browser deletion, MySQL soft
deletion, and no matching active Switch callflow. Crossbar stripped attempted
live private markers, so private/unknown-field preservation is claimed from the
focused SDK regression test; direct CouchDB writes were not used. No media-leg
call was originated, so this matrix records a verified guided foundation rather
than full Ring Group completion.

The 2026-08-31 media-leg topology audit found Crossbar on TCP 8000 but no local
FreeSWITCH/media-server process, SIP or ESL listener, or RTP path. It created no
disposable resource and originated no call. Audible ringback and emitted
internal/external `Alert-Info` therefore remain pending live acceptance in a
representative external FreeSWITCH/ecallmgr environment, consistent with the
implementation plan's production-telephony boundary.

A second 2026-08-30 disposable lifecycle used
`E2E Ring Group Weighted 20260830150119890`. One focused isolated headless test
created simultaneous routing, edited it to weighted-random with endpoint weight
`75`, confirmed the automatic zero delay, reopened all authoritative values,
and verified that only the account-scoped public Device UUID crossed the API.
An independent raw watcher observed the expected private Device ID, nested
endpoint weight, computed timeout `30`, one attempt, and `skip_module = true`.
Browser deletion was followed by an independent synchronization proving MySQL
soft deletion and zero active Switch matches. Unknown/private-field
preservation remains focused SDK regression evidence because no direct CouchDB
write or unsafe live injection was used.

A final 2026-08-30 lifecycle used
`E2E Ring Group Flags 1788104697523`. It verified the public create defaults
`ignore_forward = true` and `fail_on_single_reject = false`, then edited them to
`false` and `true` with weighted-random weight `75`, endpoint timeout `30`, and
`skip_module = true`. Reopen returned those authoritative values. Public
responses omitted the raw Device ID, private `ringtones.external`, and an
unknown node marker. An independent watcher used the production DTO
normalization path to add those private values and then observed that the typed
edit retained both, while the raw Device endpoint matched the seeded public
Device. One isolated headless test passed in 4.4 seconds. Browser deletion and
independent synchronization confirmed MySQL soft deletion and zero active
Switch matches. No direct CouchDB write or media-leg call was used.

The 2026-08-31 lifecycle used disposable route
`E2E Ring Group Media 1788127297`, number `88127297`, and a disposable
synchronized silent WAV. One isolated headless test selected the audio through
its account-scoped public Media UUID, set both phone alerts, edited the Ring
Group to weighted-random with timeout `30`, weight `75`, updated alerts, and
`skip_module = true`, then reopened the authoritative values. Public payloads
never contained the raw Media or Device IDs. An independent raw observer
confirmed both mappings and proved an unknown nested ringtone key plus unknown
node key survived the typed edit. The test passed in 5.1 seconds. Browser
cleanup and independent reconciliation confirmed a soft-deleted Callflow
projection, zero active Switch matches, and deletion/soft-deletion of the
disposable Media. No media leg was originated, so audible ringback and emitted
SIP `Alert-Info` remain compiled-runtime evidence.

## 23. ACDC Queue guided field-level matrix

The installed `callflows.acdc_queue` schema and compiled `cf_acdc_queue`
runtime define this search-only compatibility action. It is separate from
`acdc_member`: Queue Member sends the current call into a Queue, while ACDC
Queue changes the authenticated caller owner's membership in one Queue.

| Schema path or operation | GridPBX treatment | Current status |
| --- | --- | --- |
| `action` | Required guided enum: `login` or `logout` | Implemented |
| raw Queue `id` | Public API/UI accept one account-scoped Queue UUID; Laravel resolves the raw Switch resource ID only for the SDK write | Implemented |
| design-time Agent selection | Not accepted. Installed runtime infers the authorizing endpoint's single Hotdesk user or owner | Runtime-owned |
| authentication | The installed module has no PIN challenge; the form warns that the action must remain behind a trusted feature-code route | Operational constraint documented |
| `skip_module` | Guided boolean | Implemented |
| unresolved or cross-account Queue | Public writes reject it; existing unresolved nodes and their descendants remain preserved and read-only | Implemented |
| unknown node properties | Hidden from the public API/UI and merged losslessly by the Switch DTO | Implemented boundary |

A 2026-08-30 disposable isolated-headless lifecycle verified login creation,
Queue selection by public UUID, `skip_module`, authoritative reopen, nested
logout creation, public-to-raw Queue mapping, raw-ID redaction, browser deletion,
MySQL soft deletion, and zero matching active Switch callflows. The independent
raw read found the expected private Queue ID on both nodes. Unknown-field
preservation is claimed from the focused SDK regression rather than live
injection. No media-leg call was originated, so prompts, Agent inference, and
User Queue membership changes remain compiled-runtime evidence.

## 24. Eavesdrop compatibility field-level matrix

The installed `callflows.eavesdrop` and `callflows.eavesdrop_feature` schemas
and compiled runtime define these search-only compatibility actions. They are
not present in the installed Monster palette and are not safe public mutation
contracts.

| Schema path or operation | GridPBX treatment | Current status |
| --- | --- | --- |
| direct target `device_id` / `user_id` | Raw Switch identifiers are never accepted or exposed; existing values remain private | Hidden and capability-gated |
| Feature `group_id` | Raw target-restriction Group ID is never accepted or exposed; existing values remain private | Hidden and capability-gated |
| `approved_device_id`, `approved_user_id`, `approved_group_id` | Raw caller-authorization identifiers are never accepted or exposed. Runtime denies when none is configured and evaluates only the first configured field in Device/User/Group precedence | Hidden and capability-gated |
| target discovery | Direct action finds live channels for one Device or a User's Devices. Feature captures an extension, recursively finds the first Device/User node in its callflow, then delegates to direct Eavesdrop | Runtime evidence only |
| Group restrictions | Approval Group membership expands direct Device members and User members to their Devices. Feature target Group matching checks direct endpoint keys instead | Runtime evidence only |
| monitoring behavior | Runtime enables DTMF control, can redirect to the target media server, monitors the live channel, and stops the callflow | Runtime evidence only |
| `skip_module` | Safe read-only public summary for existing nodes; no create or update contract | Read-only |
| descendants and unknown node properties | Existing descendants are exposed only as preserved branches; raw and unknown data remain private and survive unrelated typed SDK edits | Preservation boundary implemented |

Focused SDK, Laravel API/resolver/public-tree, catalog, and isolated E2E checks
passed. The browser confirmed both search-only cards are disabled and emitted
zero Callflow writes. No disposable Switch node or live monitoring call was
created because GridPBX does not yet provide supervisor entitlement, immutable
monitor auditing, privacy/consent policy, or bounded monitoring controls.

## 25. SMS/MMS capability matrix

The installed `sms.json` schema requires `body` and `to`, limits the SMS body
to 700 characters, and optionally accepts `from`. The installed `mms.json`
schema requires `to` and optionally accepts a MIME-encoded body or multipart
uploads plus `from`. These schemas describe request shape only; they do not
prove carrier enablement, number eligibility, consent, billing, or a running
delivery application.

| Capability or field | Installed/runtime evidence | GridPBX treatment |
| --- | --- | --- |
| SMS inventory | `cb_sms` is loaded, but the authenticated account collection probe returns HTTP 503 | System Status exposes `sms_inventory_available = false` only |
| MMS inventory | `cb_mms` is installed but not loaded; the authenticated account collection probe returns HTTP 404 | System Status exposes `mms_inventory_available = false` only |
| inbound routing | Doodle is not running and its listener handles SMS only | No receive workflow or delivery claim |
| number capability | Runtime requires an in-service assigned number, carrier feature enablement, and account service enablement; the audited account has no projected phone numbers | No inferred or writable SMS/MMS capability |
| outbound sending | Runtime additionally checks account/reseller standing and service entitlement, normalizes offnet destinations, and creates billable flat-rate ledger entries after broker confirmation | Hard-disabled pending carrier, consent, billing, authorization, rate-limit, idempotency, and audit policy |
| message content and participants | Raw list/detail views include document IDs, `from`, `to`, status, direction, timestamps, and full body | Never included in the public capability response or MySQL projection |
| MMS attachments | Crossbar applies only its global request limit, then builds MIME parts from supplied content types and filenames; no MMS-specific type, filename, or content policy was found | Hidden pending strict type/size/count, filename, storage, malware, and disposition controls |
| retention and backpressure | Doodle's durable queue uses unlimited TTL/length and infinite route timeout; Crossbar supports direct message-document deletion | Hidden pending explicit retention, deletion/legal-hold, retry, and backpressure policy |
| installed MMS entitlement check | The installed reseller MMS validator calls the SMS entitlement helper | MMS remains unavailable until corrected and live-verified upstream |

The Switch SDK probes each account collection independently with pagination
enabled and `page_size=1`, validates only that the response is a list, and
immediately reduces it to a boolean. Laravel hard-codes message content and
sending to unavailable. Strict Zod and browser assertions reject or detect
message bodies, participant numbers, raw IDs, and attachments. The slice is a
capability foundation, not an SMS/MMS messaging feature, and performs no
Switch mutation.

Focused verification passed with 2 SDK tests and 14 assertions, 3 Laravel
tests and 35 assertions, and 2 UI files with 5 tests. Vue and isolated E2E
TypeScript checks passed. One isolated authenticated headless Playwright check
passed in 3.5 seconds and confirmed the strict public shape, both live
inventory endpoints unavailable, hard-disabled content/sending, zero mutation
requests, and no raw message data.

## 26. Number Porting capability matrix

The installed `port_requests.json` schema requires a 1–128 character name and
at least one numeric-keyed number. It also permits losing-carrier account and
billing-address data, BTN, PIN, comments, notification recipients, winning
carrier references, signee identity, signing and transfer dates, and arbitrary
per-number objects. These fields are materially more sensitive than the
minimal `phone_numbers.json` porting summary already exposed by GridPBX.

| Capability or field | Installed/runtime evidence | GridPBX treatment |
| --- | --- | --- |
| account inventory | `cb_port_requests` is loaded. The live account endpoint responds successfully with zero records, but its active-state listing explicitly disables pagination | System Status uses `by_number=gridpbx-capability-probe` and exposes only `inventory_available = true` |
| request data | Public Switch documents can include request ID, numbers, losing-carrier billing account/PIN/address, winning-carrier references, signee/dates, notification recipients, uploads, comments, state, and read-only account/port-authority identifiers | No detail/list contract or MySQL projection; strict public schemas reject raw request data |
| creation and edit | Schema validation checks number conflicts and existing local ownership. Ordinary accounts may update only `unconfirmed` or `rejected`; super administrators have broader update power | Hard-disabled pending dedicated validation, public identity model, authority policy, audit, and safe recovery |
| state transitions | Runtime supports `unconfirmed → submitted`, `submitted → pending`, `submitted | pending → scheduled`, `submitted | pending | scheduled → rejected`, and active/rejected → canceled. Completion is allowed from pending, scheduled, or rejected | Hard-disabled; never modeled as generic CRUD |
| submission automation | Submission may forward the request and current auth token to the configured Phonebook URL. The live Phonebook configuration is unset/disabled | Hard-disabled pending fixed allowlisted HTTPS egress, scoped service authentication, timeouts, redaction, idempotency, and reconciliation |
| completion | Completing a request creates the numbers locally, assigns them in service, marks them ported-in, clears the request numbers, and reconciles callflow/trunk usage | Hard-disabled pending carrier confirmation, billing, atomic orchestration, compensation, projection sync, and immutable audit |
| submitted-request export | A configured account URL can receive the submitted request plus every attachment; the live audited account has no such URL | Hard-disabled pending the same SSRF, authentication, redaction, and delivery controls as other sensitive egress |
| documents | Crossbar accepts PDF, text, and generic octet-stream uploads under the global 8 MB limit. Monster restricts its own workflow to PDF and 5 MB, which is not the runtime contract | No document access/upload until strict PDF verification, 5 MB product limit, generated filenames, malware scanning, encrypted storage decision, retention/legal hold, and audited streaming exist |
| LOA generation | Runtime generates a PDF and calls Google Charts with raw account and Port Request identifiers to create a QR image | Hidden until external disclosure is removed or explicitly approved and audited |
| comments/timeline | Comments can be private or action-required depending on port-authority status; timelines include transition authorization account/user IDs and reasons | Hidden pending public UUID translation, field-level privacy rules, retention, and role/authority authorization |

The Switch SDK does not list Port Requests. It sends one read-only exact-number
filter using a non-number sentinel, validates only the list envelope, and
immediately reduces the result to a boolean. Laravel fixes request detail,
documents, and workflow mutations to false. No Port Request record, attachment,
comment, transition, number, or raw authority identifier is persisted or
returned.

Focused verification passed with 2 SDK tests and 17 assertions, 3 Laravel
tests and 42 assertions, and 2 UI files with 6 tests. Vue and isolated E2E
TypeScript checks passed. One isolated authenticated headless Playwright check
passed in 3.5 seconds and confirmed the live filtered endpoint is available,
all higher-risk capabilities remain false, zero mutation requests occur, and
no raw or sensitive Port Request field enters the public response or UI.

## 27. Number acquisition, reservation, and release capability matrix

The installed `find_numbers.json` schema requires a three-to-ten-character
prefix, accepts a two-character country defaulting to `US`, and accepts a
positive quantity defaulting to one. Runtime caps quantity at 500 and derives a
query identifier from the private account identifier plus the current auth
token. Search fans out to every effective carrier module and can therefore
perform external provider requests even though its HTTP verb is GET.

| Capability or field | Installed/runtime evidence | GridPBX treatment |
| --- | --- | --- |
| carrier information | `GET .../phone_numbers/carriers_info` returns maximum prefix length, the static usable-carrier catalog, and creation states allowed to the authorizing account. The catalog does not prove that those providers are configured or reachable | SDK validates the complete expected shape and exposes only `carrier_configuration_available`; names, modules, states, and provider configuration are discarded |
| live carrier configuration | Global and audited-account `carrier_modules` are unset, so installed defaults select `knm_local`. Live carrier-info returns a valid maximum prefix of 10, 24 catalog entries, and five allowed creation states | Recorded as deployment evidence only; the public boolean is true and makes no purchase/search claim |
| number search | `GET .../phone_numbers?prefix=...` invokes all effective carrier modules, hashes account plus token into the query ID, caches discovery results, and returns number/state pairs. Installed `knm_local` searches only internal available inventory and is non-billable, but other deployments can call external carriers | Hard-disabled in the public API/UI and never used as a periodic capability probe |
| purchase/activate | `PUT .../phone_numbers/{number}/activate` and collection activation first run with `dry_run=true`. Non-empty quotes produce HTTP 402; retry with `accept_charges=true` performs the state/carrier operation and may return per-number partial success | Hard-disabled pending server-owned quotes, explicit confirmation, authorization, stable idempotency, uncertain-outcome recovery, compensation, audit, and projection reconciliation |
| reservation | `PUT .../phone_numbers/{number}/reserve` can change account ownership/history and, from discovery, call the carrier's acquisition function | Hard-disabled pending the same provider, billing, hierarchy-authorization, idempotency, compensation, and reconciliation controls |
| release | DELETE uses release unless a super administrator supplies `hard=true`. Release strips public fields/features, unwinds reserve history to a previous account when present, and otherwise can disconnect the carrier and delete or age the number. With the live defaults, released state is `available`, aging and permanent deletion are globally disabled, but local numbers with no reserve history are still permanently deleted | Hard-disabled pending exact-number confirmation, current dependency checks, E911/CNAM cleanup policy, callflow/trunk compensation, immutable audit, and authoritative resynchronization |
| public identity | Existing acquired numbers already have account-scoped GridPBX UUIDs; search candidates do not | Future release/reservation commands must accept only an existing public Phone Number UUID. Future search/purchase must use a short-lived server-owned selection contract and issue a public UUID only after successful synchronization |
| raw/unknown fields | Carrier responses can include provider-specific discovery data and quote/transaction material | Never returned by this foundation; no carrier response or number mutation payload is persisted in MySQL |

Monster's reference workflow first reads carrier info, searches by regular,
toll-free, vanity, or locality criteria, lets the operator select numbers, and
activates the collection. Its shared API handler displays a 402 quote and then
retries with `accept_charges=true` after confirmation. Its availability recheck
is explicitly mocked rather than authoritative. The delete workflow groups
numbers, performs a UI-only E911 guard and confirmation, and then calls the
collection DELETE endpoint. Those interaction steps do not add server-side
idempotency, dependency, or compensation guarantees.

GridPBX performs one account-scoped carrier-info GET, validates the response,
and immediately reduces it to a boolean. Laravel fixes search, purchase,
reservation, and release to false. Strict Zod and browser assertions reject
carrier catalogs/modules, creation states, available numbers, quotes, charges,
and `accept_charges`. No search or mutation was executed during the live audit.

Focused verification passed with 2 SDK tests and 20 assertions, 3 Laravel tests
and 51 assertions, and 2 UI files with 7 tests. Vue and isolated E2E TypeScript
checks passed. One isolated authenticated headless Playwright test passed in
3.5 seconds and confirmed the live boolean, all four operational flags fixed to
false, zero mutation requests, and no raw carrier information in the public
response or UI.

## 28. Feature-code Callflow inventory matrix

Feature codes are ordinary Callflow documents with optional top-level
`featurecode` metadata. The installed schema has no standalone Feature Code
resource or enabled property; existence of the Callflow makes the route
active.

| Field or workflow | Installed/runtime evidence | GridPBX treatment |
| --- | --- | --- |
| identity | `featurecode.name` and `featurecode.number` are bounded strings on the Callflow document | Projected as display metadata beside an account-scoped public Callflow UUID; no raw Callflow ID is returned |
| dial entry | Actual matching remains in `numbers[]` or `patterns[]`; Monster stores a display-oriented number separately | The UI derives a readable star prefix from the projected entry shape without parsing or accepting a writable regular expression |
| enabled state | Installed Monster treats every listed Callflow with `featurecode.name` as enabled | Existing non-deleted records display as Projected active beside projection freshness; absent/deleted routes are not synthesized as editable defaults |
| action and dependencies | Runtime behavior is owned by the root `flow.module` and its private data/call context | The inventory shows only the safe root module/action label and a non-identifying runtime dependency summary; raw node data remains private |
| lifecycle operations | Monster creates, fully updates, or deletes the whole Callflow; no atomic enable/disable/renumber endpoint exists | Capability-gated pending a versioned supported-code registry, collision-safe validation, lossless Switch-first write/compensation, dependency checks, authorization/audit, and disposable live verification for each enabled action |
| unknown/private data | Callflows can contain raw resource references and arbitrary future fields | Preserved in the server-side redacted snapshot and existing lossless Callflow machinery; strict Zod reduction prevents unrelated response fields from entering Feature Codes state |
| live evidence | Direct Switch detail hydration and MySQL independently returned the same 17 active feature-code routes on 2026-08-31 | An isolated authenticated headless test was rerun on 2026-09-01 and confirmed the named six-column inventory, keyboard search, 390-pixel responsive containment, public UUIDs, raw-ID absence, zero mutations, and no browser or server errors |

The current UI intentionally does not render disabled defaults from Monster's
registry. That registry includes contracts which differ from the installed
schemas, while the active account has no Do Not Disturb feature-code Callflow.
Showing those defaults as safely enableable would make an unsupported mutation
claim. The normalized Callflow synchronization remains the single import,
reconciliation, freshness, and soft-deletion path for this read-only view.

## 29. Services and Billing visibility matrix

| Field or operation | Treatment | Current status |
| --- | --- | --- |
| standing, reseller/billing-owner summary, billing cycle | Account-scoped normalized read projection; raw Switch account and reseller IDs remain private | Implemented read-only |
| assigned plans, service quantities, limits, recurring impact, due-today impact | Allowlisted read projection from installed services/limits contracts | Implemented read-only |
| ledger source summaries, total, and Switch transactions | Immutable operational visibility with public UUIDs; bookkeeper/payment metadata and raw payloads remain private | Implemented read-only |
| reconciliation checks and sync history | Sanitized status, count comparison, failure category, and recovery guidance | Implemented read-only |
| invoice and receipt summaries/details | Provider-neutral, authority-labelled read models using account-scoped public UUIDs | Implemented when an approved source is configured |
| invoice/receipt PDF | Separate authenticated download after authoritative detail confirms a safe `application/pdf` document | Implemented operation; not an editable field |
| successful local payment confirmations | Explicitly non-authoritative confirmation records; never represented as invoices or provider receipts | Implemented read-only |
| plan assignment/removal, overrides, manual quantities, top-up, quote acceptance | Distinct installed Service commands requiring reseller authority, billing semantics, confirmation, audit, and reconciliation | Capability/policy-gated |
| ledger credit/debit and transaction sale/refund | Distinct privileged financial commands; never inferred from endpoint availability or exposed as Service fields | Capability/policy-gated |
| Authorize.Net sandbox charge/void/refund/profile/recovery | Separate default-off hosted-tokenized command workspace with independent flags and public attempt/profile UUIDs | Sandbox foundation; not a Service/Billing-record Advanced tab |
| production payment operations | Requires an approved authoritative billing/payment contract and production compliance controls | Unavailable |

The Service detail and every Billing-record slide-over are intentionally
single-view and read-only. Monster separates service-plan/item views, billing
settings, and transaction history rather than presenting one editable Service
document. Kazoo likewise exposes each mutation as its own endpoint. GridPBX
therefore does not add artificial Basic/Advanced tabs: the Billing page is a
read workspace, safe PDF retrieval is a document operation, and sandbox
payments remain a separately gated command bounded context. Focused component
and isolated-browser assertions lock the absence of entity tabs and financial
mutation buttons from the read-only detail panels.

## 30. System Status presentation matrix

| Probe or operation | Public treatment | Current status |
| --- | --- | --- |
| Presence subscription diagnostics | Endpoint availability boolean only; no live User state, SIP subscriptions, contacts, or identifiers | Implemented read-only |
| parked calls | Summary availability and nullable aggregate active-call count only; raw slots/calls discarded | Implemented read-only |
| Webhook event/configuration inventory | Catalog/configuration availability and aggregate counts only; URLs, hook IDs, custom data, attempts, and payloads discarded | Implemented read-only |
| SMS/MMS inventory | Independent endpoint-availability booleans only; numbers, participants, bodies, attachments, and messages discarded | Implemented read-only |
| Port Requests | Filtered collection-availability boolean only; request details, documents, identities, comments, and transitions unavailable | Implemented read-only |
| number management | Carrier-configuration endpoint-shape boolean only; providers, states, available numbers, quotes, and charges discarded | Implemented read-only |
| caching/persistence | Ten-second account-scoped cache; no durable System Status entity or raw probe persistence | Implemented |
| Refresh | Repeats the authorized read-only aggregate request | Implemented operation |
| presence commands, park/retrieve, Webhook CRUD/history, messaging, Porting workflow, number search/purchase/reserve/release | Separate runtime, security, carrier, billing, or regulated workflows; never System Status Advanced fields | Capability/policy-gated |

There is deliberately no Basic/Advanced selector. Monster separates Numbers,
Porting, Messaging, and Webhooks into their own applications and supplies no
combined editable System Status document. The strict Zod contract fixes all
mutation flags to false and rejects unknown raw payload fields. Focused
isolated-browser coverage asserts that the page emits no mutation, exposes no
administrative action buttons, and remains a single read-only operational view.

## 31. Connectivity, Trunk, and Resource capability matrix

| Field or workflow | Installed schema/runtime evidence | GridPBX treatment |
| --- | --- | --- |
| Resource identity and availability | `resources.json` requires `name` and `gateways`, with resource/gateway enable flags and sequential or random gateway selection | No public Resource entity yet; must use a future account-scoped public UUID and never a raw document ID |
| routing eligibility | Rules, classifiers, prefixes/suffixes, resource flags, required/ignored flags, flat-rate lists, weight/cost, and grace period select and order outbound routes | Administrator-only policy surface; unavailable until portable validation, final normalized-destination authorization, spend controls, and deterministic preview exist |
| gateway destination and authentication | Gateways contain server, port, static route, realm, username, password, endpoint/interface type, and hardware span | Entire document remains private. Credentials require server-side vaulting/rotation and must never enter projections, API responses, logs, previews, or browser state |
| SIP and invite customization | Gateways allow custom inbound/outbound SIP headers, static invite parameters, and dynamic values sourced from call channel variables, SIP headers, or zone | Capability-gated because these controls can disclose private call metadata or alter carrier trust/routing semantics |
| media and transport | Gateway codecs, bypass-media, T.38, RTCP mux, progress timeout, invite format, port enforcement, and From-realm formatting affect signaling and RTP behavior | Requires deployment-specific SIP/RTP validation and representative FreeSWITCH/ecallmgr tests; no generic writable form is safe yet |
| emergency behavior | Resource/classifier emergency flags and caller-ID selection feed StepSwitch; global validation defaults can allow unverified emergency caller ID, and account-hunted resources bypass the global validation path | Hard-gated pending fail-closed E911 ownership, emergency route testing, immutable audit, and a policy that also covers local/account resources |
| selector documents | Resource selectors contain raw Resource IDs, selector names/values, and optional effective times | No public contract; future projection must replace raw relationships with account-scoped public UUIDs and preserve unknown selector data privately |
| Trunkstore account and limits | `trunkstore.json` combines auth realm, caller IDs, emergency caller ID, prepaid credit, purchased trunk quantity, and call restrictions | Billing and connectivity cannot be one generic entity form. Credits, quantities, and restrictions require separate authorization, quote/confirmation, audit, and reconciliation operations |
| Trunkstore servers and DIDs | Server entries contain authentication credentials, DIDs, arbitrary options/SIP headers, force-outbound, media handling, timing, and SIP/E.164 failover | No projection or mutation. Failover targets require loop prevention, ownership/classification checks, SSRF-safe SIP policy, and live failover evidence |
| Monster Trunks workflow | The My Account slider reads and updates account limits and supports charge-cancellation callbacks; it does not configure Resource gateways | Workflow evidence only. GridPBX must not copy the slider until reseller authority and authoritative billing semantics are approved |
| Monster carrier callflow workflow | Global Carrier writes an empty `offnet` terminal node; Account Carrier writes `resources` with an operator-entered raw `hunt_account_id` | Both remain disabled. Public API/SDK writes reject them, private node data stays redacted and losslessly preserved, and raw account IDs are never accepted |
| current public capability | System Status validates carrier-info endpoint shape and reduces it to one boolean; provider names/modules, states, quotes, charges, and resource documents are discarded | Implemented read-only boundary only; it is not a connectivity-management foundation or proof that a live carrier is usable |

The 2026-08-31 audit intentionally made no raw Resource/Trunkstore collection
request because those documents can contain credentials and private routing
configuration. Focused verification passed one Switch SDK preservation test /
21 assertions, three Laravel rejection/redaction tests / 105 assertions, three
Vue files / 22 tests, and two isolated non-mutating headless Playwright checks.
The browser exposed only the safe carrier boolean, kept Global and Account
Carrier disabled, and emitted no Callflow or connectivity mutation.

## 32. Account Administration capability matrix

The installed `accounts.json` schema requires only `name`, but that is not the
runtime contract for a safely usable tenant. It also accepts account-wide
telephony defaults and policies such as caller ID, call restrictions,
recording, dial plan, voicemail, preflow, notifications, music on hold,
language, timezone, realm, and enabled state. GridPBX already models the
reviewed subset of these fields through the Account settings/status workflows;
the following matrix covers the separate P3 lifecycle, hierarchy, reseller,
limits, and service-management operations.

| Operation | Installed schema/runtime and Monster workflow evidence | GridPBX treatment |
| --- | --- | --- |
| create Account | `PUT /accounts` or `PUT /accounts/{parent}` validates the Account document in a parent context. Kazoo creates the account database and definition, loads views, publishes `account.created`, creates the current monthly database, reconciles services, opens a rollover transaction, inherits notification preference, and sends a new-account notification | Unavailable. A future command must accept a public parent Account UUID, enforce reseller authority, be idempotent, project the new public Account UUID only after authoritative success, and compensate or clearly recover every side effect |
| Monster create wizard | After creating the Account, Monster independently attempts app restrictions, limits, a no-match Callflow, service plans, credit, and one or more admin users. Feature-step failures are collected and the wizard still opens the new Account | Workflow evidence only. GridPBX must define an explicit resumable onboarding saga and cannot represent the wizard as one atomic create form |
| update Account settings and enabled state | Account POST/PATCH merges public Account fields; update spawns notification and provisioner workers. Enabled-state import is hierarchy-sensitive | Existing typed Account settings and exact-name-confirmed status workflows only. Their audited field allowlists, unknown-field preservation, public UUID boundary, and focused tests remain authoritative; there is no generic Account JSON editor |
| onboard existing descendant | Kazoo descendants expose raw hierarchy identifiers but perform no mutation | Implemented projection-only workflow: short-lived actor/scope-bound opaque reference, exact-name and inherited-access confirmation, public Account UUID response, audit event, and queued service projection. Raw Switch Account IDs never enter the UI |
| move Account | `POST /accounts/{id}/move` requires raw `to`, applies configurable super-administrator or common-tree authorization, and calls the hierarchy move runtime | Unavailable. Requires public source/destination Account UUIDs, ancestor/descendant loop rejection, complete hierarchy and billing-owner projections, explicit confirmation, audit, recovery, and post-move account/service reconciliation |
| delete Account | Master Account deletion is disallowed. Other deletion is blocked by descendants or an active port; execution cancels services, frees numbers, removes SIP aggregates, calls provisioner/mobile cleanup, deletes the Account database and monthly databases, then removes the global definition | Hard-gated destructive lifecycle. Requires complete dependency inventory, exact-name confirmation, retention/export policy, external cleanup contract, uncertain-outcome recovery, immutable audit, and independent Switch/MySQL absence verification |
| promote/demote reseller | Only a super administrator may call the reseller endpoints. Kazoo rejects the master Account and reseller descendants; billing/service ownership changes are delegated to `kz_services_reseller` | Unavailable. Existing hierarchy response exposes read-only public-UUID preflight checks and always fixes `mutation_available=false`; platform policy, billing-dependent reassignment, explicit confirmation, recovery, and reconciliation are still required |
| update Limits | `limits.json` exposes non-negative trunk/call caps and `allow_prepay`. A v2 update can return HTTP 402 with a quote and must be retried with `accept_charges=true`; Monster's Trunks and Account wizard workflows implement that callback/confirmation contract | Read projection only. No writable Limits form until reseller authority, server-owned quote expiry, explicit charge confirmation, idempotency, audit, rollback/reconciliation, and billing-source authority are approved |
| assign/remove service plans | Services supports bulk add/delete, per-plan assign/unassign, and validates plan IDs from the billing reseller's database. Only the billing reseller or a super administrator may change assignments | Read projection only. Raw plan document IDs are private; any future selector needs an account-scoped public reference plus authoritative price/term presentation and reconciliation |
| service overrides/manual quantities | POST replaces and PATCH merges global overrides or manual quantities, then commits the Services document | Unavailable. Editable-field policy, type/range validation, inheritance semantics, billable impact, public field identities, audit, and safe rollback are not yet modeled |
| quote, top-up, synchronization, reconciliation | Quote summarizes prospective service changes. Top-up can create ledger/transaction records. Synchronization calls the bookkeeper; reconciliation recalculates Services quantities | Separate commands, never Account Advanced fields. Quote may become a read-only preflight after its public/redacted contract is proven; financial and state-changing commands remain gated by their own authority, idempotency, audit, and recovery requirements |

The current Account hierarchy and Services APIs expose only account-scoped
public UUIDs and allowlisted projection fields. Internal MySQL keys, raw Switch
Account/Service Plan identifiers, full Account documents, Services overrides,
and raw billing payloads remain private. This audit performed no Switch
mutation and does not change Account Administration from Planned.

Focused verification passed 22 Laravel Account hierarchy, policy, descendant
onboarding, and Services visibility tests with 224 assertions. The isolated
E2E TypeScript check passed, and one isolated authenticated headless Playwright
scenario passed in 3.4 seconds. The browser confirmed public hierarchy and
billing-owner presentation, opaque unmanaged-descendant references, reseller
preflight guidance, and the absence of promote/demote controls. Its optional
service refresh uses GridPBX's existing read-projection synchronization path;
it does not invoke Kazoo's billing-side synchronization or reconciliation
commands.

## 33. White-labeling and tenant-brand ownership matrix

The installed `whitelabel.json` schema is an account-scoped public-portal
configuration document, not a generic theme contract. It contains company and
domain metadata, external links, display-only prices, Porting authority/support
fields, and SSO-provider definitions. Crossbar stores logo, icon, and welcome
content as attachments to the same document and publishes account branding by
domain through unauthenticated GET endpoints.

| Field or workflow | Installed schema/runtime and Monster workflow evidence | GridPBX treatment |
| --- | --- | --- |
| company name | `company_name` is an optional string. Monster maps it to its deployment `companyName` only when the requested document domain exactly matches `window.location.hostname` | Unavailable as tenant branding until platform/reseller ownership, inheritance, fallback, cache invalidation, and audit rules are approved |
| public domain | `domain` is schema-formatted as a URI, must be globally unique in the Accounts aggregate view, and is copied to the Account's private `pvt_whitelabel_domain`. Runtime does not prove DNS ownership or provision TLS | Hard-gated. A future workflow requires a normalized IDNA hostname contract, reserved-name checks, DNS challenge, verified ownership, deployment-managed certificate issuance/renewal, staged activation, rollback, and takeover prevention |
| domain records and testing | Account GET formats administrator-defined A/CNAME/MX/NAPTR/SRV/TXT templates for a supplied or stored domain. Account POST discovers nameservers and performs live DNS queries; only the global domain-template POST is explicitly super-administrator-only | Read/write unavailable. Future checks require bounded allowed record types, normalized hostnames, resolver timeouts/rate limits, rebinding/cache-poisoning considerations, result redaction, and no arbitrary network resolver behavior from an operator-controlled value |
| logo | POST accepts one JPG/JPEG/PNG/GIF or base64-labelled upload; validation relies on declared content type and the global 8 MB request cap. Replacement deletes the existing attachment metadata before saving the new binary | Switch Whitelabel logo remains unavailable. GridPBX instead implements a separate organization-owned shell logo: PNG/JPEG/WebP only, 2 MB and 32–2048 pixel input bounds, server-side decode/re-encode to a maximum 512×256 PNG, generated private filenames, transactional metadata/audit update, replacement cleanup, authenticated account-scoped delivery with `private, no-store` and `nosniff`, and the product mark as fallback. The public API exposes only organization UUID plus availability/timestamp metadata; it never returns a path or internal key |
| icon | POST accepts common ICO types plus the logo types and base64. It is retrieved publicly by domain | Unavailable under the same controls, with a stricter favicon format/dimension policy and content-sniffing-safe response headers |
| welcome content | POST accepts a single `text/html` attachment, which is publicly retrievable by domain. Monster also inserts a separate `custom_welcome_message` into the login page with `.html()` | Raw tenant HTML is prohibited. Any future welcome content must be a bounded plain-text/structured contract rendered with escaping; supporting HTML would require an approved sanitizer, CSP, link/media policy, and security review |
| public metadata lookup | `GET /whitelabel/{domain}` and public logo/icon/welcome GETs bypass ordinary authentication. Metadata can include external URLs, SSO client IDs/scopes, raw Port authority Account IDs, and display prices | No passthrough or raw projection. A future bootstrap endpoint must select only explicitly public fields, replace relationships with public UUIDs where appropriate, reject unknown fields, use host-scoped caching, and never expose raw Switch Account IDs |
| external navigation | `nav.help`, `nav.learn_more`, and Porting features/LOA/RespOrg/terms URLs are schema-formatted URIs; `fake_api_url` is a beta developer-facing API URL | HTTPS links require an approved host policy and safe browser navigation. `fake_api_url` will not be accepted because tenant data must not redirect GridPBX API traffic |
| Porting authority and support | `port.authority` accepts one or more raw Account IDs. `port.support_email` is display contact metadata; it is not an outbound mail sender identity | Port authority remains owned by the separate Porting authorization model and must use account-scoped public UUIDs. Support contact requires privacy/abuse policy. Neither field enables or configures email delivery |
| display prices and hide flags | Inbound/outbound/two-way trunk prices are strings documented as display-only. Hide Credits, Powered-by, and Registration flags alter presentation, not billing or authorization | No authority or entitlement decision may depend on them. Pricing must come from the authoritative billing domain; visibility flags require product/deployment policy before becoming tenant settings |
| SSO providers | Each provider requires an authorization URL and may expose client ID, response type `code`, scopes, and display name. Monster uses these values on the unauthenticated login page | Separate Identity and Access capability. Requires fixed provider/issuer registration, exact redirect URIs, state/nonce and PKCE, callback/session policy, key/secret handling, account binding, lockout recovery, and audit; never enabled through a generic Branding form |
| colors and shell theme | The installed Switch schema has no tenant color tokens. Monster's `brandColor`, application title, additional CSS, and many feature flags are deployment configuration, not fields in `whitelabel.json` | GridPBX's existing Header/Sidebar theme customizer is intentionally personal UI preference only: fixed accessible token sets, browser-local storage, no API call, no organization inheritance, and no claim of Switch parity |
| email identity | The installed schema has no outbound From name/address, DKIM, SPF, return-path, or notification-template identity | Owned by deployment and the future Notifications/email-delivery boundary. Requires verified sending domains, DKIM/SPF/DMARC, bounce/complaint handling, anti-abuse policy, and template authorization |
| update/delete and preservation | PUT creates, POST merge-updates, and DELETE hard-deletes the Whitelabel document then clears the Account's private domain. Document and Account updates are separate; attachment replacement is also multi-step | All mutations gated pending idempotency, optimistic concurrency, lossless preservation of unknown private fields, immutable audit, asset/domain versioning, compensation, and authoritative post-write reconciliation |

GridPBX has no Switch White-label API route, SDK client, or projection. It now
has one narrow local organization-logo store used only by the authenticated
application shell. This local asset must not be silently populated from the
public Switch Whitelabel document because that would merge unreviewed external
URLs, SSO configuration, raw authority IDs, and HTML into the application
bootstrap boundary.

This audit intentionally made no live Whitelabel document request: the public
runtime response can contain SSO and raw Port-authority configuration, and no
strict GridPBX reduction exists yet. No DNS lookup, attachment read, or Switch
mutation was performed. White-labeling remains Planned.

Focused verification passed the four-test Vue theme-store file, the isolated
E2E TypeScript check, and one isolated authenticated headless Playwright
scenario in 2.5 seconds. The browser applied only catalogued Header/Sidebar
tokens, persisted them in local storage, restored them after reload, and reset
them without exposing a tenant-brand or Switch Whitelabel control.

## 34. Provisioning Templates capability matrix

The installed Crossbar source exposes global and local Provisioner Template
resources, but the `provisioner_templates` validation schema referenced by
their runtime is absent from this installed checkout. Generated API
documentation lists the routes and methods without publishing a field
contract. GridPBX therefore treats runtime behavior as evidence for storage,
authorization, and lifecycle only; it does not infer editable template fields
from undocumented JSON examples.

| Field or workflow | Installed schema/runtime and Monster workflow evidence | GridPBX treatment |
| --- | --- | --- |
| identity and ownership | Global templates are documents in the shared Provisioner database; local templates are documents in the current Account database. Both mark private type, provider, and global/local scope values | No public Template entity exists. A future API must issue account-scoped GridPBX UUIDs for local templates and a separately authorized public identity for global templates; raw Couch/Switch IDs and internal database keys never enter the UI |
| list and detail | Collection views expose only raw `id` and `name`. Detail loads the document and separately decodes the `template` attachment, which may be arbitrary vendor JSON | Current GridPBX reads only a reduced phone-model catalog. It must not passthrough template detail, raw IDs, unknown vendor configuration, SIP credentials, firmware endpoints, certificates, or secret values |
| installed field schema | Both modules call validation for `provisioner_templates`, but that schema is missing and the generated docs contain no properties | No Basic/Advanced or JSON editor may be built until the exact installed/target validation contract and real representative payloads are proven. Unsupported fields stay private and losslessly preserved server-side |
| template attachment | The runtime removes `template` from the metadata document and stores it as a separate JSON attachment, with source comments describing templates near 300 KB | Full template content remains a private encrypted/server-side value. Any future typed form edits only its allowlisted fields and merges them with the authoritative attachment so unknown fields are preserved without round-tripping them through the browser |
| create and update atomicity | Metadata is saved first and the JSON attachment second. Attachment failure can leave a document without the intended template; update merge-preserves document fields but replaces the whole attachment | Unavailable until the workflow has immutable versions, optimistic concurrency, idempotency, staged validation, compensation/recovery, authoritative reread, and an audit record that never includes template or credential content |
| authorization | Authenticated users may read global templates; global mutations are restricted to system administrators. Local operations use the ordinary Account-tree authorization path | GridPBX requires dedicated platform/reseller template roles in addition to Account access. A safe public API must reduce global reads and prevent local administrators from changing shared templates or escalating through copied sensitive content |
| provisioning-default retrieval | On create, Kazoo URL-encodes operator-provided brand/model/product values into a GET to the configured Provisioner URL, logs the constructed URL, and decodes the response as template JSON. Error/non-200 responses become HTTP 500 | No browser-selected or operator-supplied URL is accepted. Future retrieval needs a deployment-owned allowlisted HTTPS origin, DNS/IP rebinding defenses, strict connect/read timeouts and response-size/type limits, no redirects to untrusted networks, secret-free logs, circuit breaking, and schema validation before storage |
| broader provisioner data flow | Depending on configured Provisioner mode, Device operations can send MAC, Account data, contact lists, SIP realm, username, and password to an external service; one installed path logs the encoded request body | Vendor credentials and SIP secrets remain vault-owned and are never projected, returned, or logged. A provider-specific data-minimization and retention contract plus redaction tests is required before enabling external provisioning |
| images | Each resource accepts one declared `image/*`, octet-stream, or base64-labelled attachment, bounded only by Crossbar's general request limit; replacement/deletion is a separate operation | Unavailable pending magic-byte verification, strict format/byte/pixel limits, decode/re-encode, generated filenames, content-sniffing-safe responses, malware policy, immutable asset versions, and recoverable replacement |
| global/local precedence | The two Crossbar modules prove separate stores but do not establish a safe inheritance, override, or effective-template resolution contract for GridPBX | No inheritance is inferred. Future UI must show the effective source, immutable global base/version, local override diff, conflict rules, and the exact Devices affected before publication |
| Device model relationship | Current catalog metadata can include a template identifier and model capabilities. GridPBX validates brand/family/model/template consistency but keeps that identifier as private provider metadata | Existing Device forms remain catalog selectors, not template administrators. Any future persisted relationship uses a public Template UUID resolved server-side; raw provider or Switch template IDs never cross the public API |
| line keys | GridPBX uses reduced model limits and supported key types to preview/apply line keys, resolving Extension relationships with account-scoped public UUIDs | Implemented bounded foundation only. Line-key application does not prove that arbitrary template JSON, vendor firmware, or template rollout is safe |
| deletion and dependencies | Crossbar deletes the template document without a visible Device/model dependency preflight in these modules; image deletion is independent | Hard-gated until GridPBX inventories model assignments and enrolled Devices, blocks unsafe deletion, offers an explicit replacement/migration plan, and independently reconciles Switch, provider, and MySQL state |
| firmware | No firmware download, signature, compatibility, or rollback contract is established by these template modules | Separate supply-chain capability. Require deployment-owned HTTPS allowlists, signed hashes/vendor authenticity, device-model compatibility, bounded downloads, staged canaries, failure telemetry, and rollback before exposure |
| zero-touch enrollment | Manufacturer enrollment is an external-provider concern, not implied by template CRUD. GridPBX currently reports the adapter as unavailable and requires explicit confirmation even when a provider is later configured | Keep capability-gated. Require MAC ownership/uniqueness, vendor authentication, replay/idempotency protection, exact Device confirmation, provider audit/reconciliation, detach/recovery, and no provider tokens in API responses |
| publish, reload, and reboot | Current per-Device synchronize/reprovision commands are explicit audited operations. Crossbar template storage does not prove automatic fleet application semantics | Template publication must preview affected Devices, support opt-in batches/canaries and maintenance windows, separate save from deploy, provide per-Device status, and roll back without silently rebooting a fleet |

Monster in this installed checkout contains Device model selection but no
Provisioner Template administration workflow. GridPBX likewise has no template
route, SDK CRUD client, projection, or form. Its existing foundation is the
reduced catalog, model-capability checks, safe Device selection, public-UUID
line-key values, redacted projection, explicit Device synchronization, and a
manufacturer-enrollment adapter that is unavailable by default.

This audit intentionally made no live template detail, image, default-retrieval,
or mutation request. A template response can contain SIP/vendor credentials and
arbitrary endpoints, and GridPBX has no strict public reduction for it. Template
administration and zero-touch provisioning remain Conditional, not Complete.

Focused verification passed the one-test Switch catalog mapper with 13
assertions, four exact Laravel catalog/capability/enrollment tests with 52
assertions, the isolated E2E TypeScript check, and the single authenticated
headless provisioning walkthrough in 7.3 seconds. The browser used one
disposable Device, confirmed enrollment remains disabled without an adapter,
used only public UUIDs for resource-backed line keys, completed line-key
create/edit/clear, and cleaned the Device up. It made no Provisioner Template
read or write.

## 35. Notifications and delivery ownership matrix

The installed `notifications.json` schema models email template metadata.
Crossbar stores the HTML and plain-text bodies as separate attachments and
resolves metadata and attachments through the Account hierarchy to the
reseller and then the system configuration database. Teletype renders and
sends the effective template; SMTP delivery logs and failed-notification retry
documents can retain complete message content and event payloads.

| Field or workflow | Installed schema/runtime and Monster workflow evidence | GridPBX treatment |
| --- | --- | --- |
| template identity and catalog | System documents use `notification.<type>` IDs and fixed categories. Account collection reads merge local overrides over the available system list; non-super-administrators do not see `system` or `skel` categories and Port templates are authority-dependent | No public Notification Template entity exists. A future projection must use GridPBX public UUIDs and a server-owned allowlist of supported semantic types; raw Couch IDs, internal keys, and unreviewed categories never enter the UI |
| ownership and roles | Top-level reads are authenticated and top-level mutations require super-administrator authority. Account-path mutations receive no Notification-specific role check beyond general Account authorization | Require distinct platform template, reseller template, and account template permissions. Account access alone must never authorize sender, destination, body, preview, delivery-log, reset, or force-system operations |
| metadata schema | `from`, `subject`, and `to` are required. The schema also exposes `enabled`, `friendly_name`, `category`, `reply_to`, `template_charset`, `macros`, and `cc`/`bcc`; subject is 1–200 characters and addresses use email format | A future form may expose only proven allowlisted metadata. Category, system macro definitions, charset, and immutable template type are server-owned. Unknown fields are preserved privately by authoritative read/merge/write and never round-trip through hidden browser inputs |
| sender identity | `from` and optional `reply_to` are template email addresses. SMTP transport is globally configured and does not prove that an Account controls the sender domain | Hard-gated by verified sending-domain ownership, fixed allowed From identities, DKIM/SPF/DMARC alignment, return-path/bounce/complaint handling, abuse policy, and immutable audit. Arbitrary account-supplied From addresses are prohibited |
| To/CC/BCC modes | Each destination can be `original`, `specified`, or `admins`; specified mode accepts address arrays. Teletype may derive `admins` from the Account or reseller hierarchy and `original` from event data | Show resolved destination class and safe counts before activation. Explicit addresses require bounded unique lists and privacy policy; dynamic modes need event-specific recipient rules, reseller-boundary tests, and no cross-account expansion or recipient disclosure |
| macros | System templates publish macro definitions, including Account/User identity, realm, parent identifiers, Port, billing, voicemail, Fax, and other event data. Account updates discard submitted `macros` and restore the system definitions | Operators cannot create tokens or supply macro values. A future editor uses an event-specific allowlist with friendly descriptions, rejects unknown expressions, keeps raw Account/Switch identifiers private, and previews only synthetic or explicitly authorized data |
| HTML and plain bodies | `text/html` and `text/plain` are independent attachments. Upload compiles ErlyDTL; master rendering explicitly disables auto-escape, and effective rendering may use account or ancestor attachments | Raw HTML is unavailable. Prefer structured content with escaped components. Any future HTML mode requires sanitizer and URL policy, no script/forms/active content, remote-image/tracking policy, bounded template size/complexity/render time, safe link handling, and a sandboxed non-DOM preview |
| inheritance and effective source | Detail first checks the Account, then parent Accounts up to the reseller, then system. Metadata can be locally overridden while missing body attachments are sourced from an ancestor; the response marks an account override | A future UI must show the effective source and version separately for metadata, HTML, and text. Editing creates an explicit local version; inherited content remains immutable. Reset must preview the exact fallback source and resulting values |
| update and migration | Updating an inherited template can migrate the system document and attachments into the Account, then save metadata or a body attachment in separate operations. A successful metadata update can also set the Account notification preference to `teletype` | Treat as a versioned multi-step command with optimistic concurrency, idempotency, compensation/recovery, preference-change disclosure, authoritative reread, and audit. A generic POST form cannot safely represent this lifecycle |
| deletion and bulk reset | System templates cannot be deleted. Account DELETE hard-deletes a customization and falls back to an ancestor. Collection actions can remove all Account customizations or delete them and copy every system template | No bulk mutation until exact Account confirmation, complete diff/impact preview, recoverable snapshots, partial-failure reporting, idempotency, and independent effective-state reconciliation exist. Per-template reset must be the first supported rollback operation |
| render preview | Preview publishes an AMQP notification request, waits for Teletype, and returns 202 rather than returning a rendered document. It can therefore invoke the delivery pipeline and recipient resolution | “Preview” must first mean server-side render only with synthetic/redacted macro data and no SMTP call. Test delivery is a separate explicitly confirmed command to a verified current operator address with rate limiting, audit, and status reconciliation |
| customer-update message | Crossbar accepts subject, From/Reply-To, To/CC/BCC, arbitrary HTML/plain content, optional template ID, and a raw 32-byte descendant recipient ID, then publishes a notification | Separate broadcast/messaging capability, not template CRUD. Keep unavailable pending public recipient Account UUID mapping, descendant authorization, recipient-count preview, verified sender, HTML/content policy, rate/spam controls, confirmation, idempotency, audit, and delivery results |
| SMTP configuration | Installed defaults permit relay `localhost`, port 25, authentication `never`, one retry, optional TLS, and SSL disabled; username/password live in system configuration | Deployment-only secret configuration. Credentials stay in a vault and never enter projections or browser state. Production readiness requires approved relay allowlist, certificate-verified mandatory TLS, rotation, bounded timeouts/retries, health checks, and secret-safe logging |
| SMTP log summary | The Account monthly-database view exposes template type, From, first To, subject, timestamp, receipt, and error | No general Account-user access. A future delivery summary uses a public UUID, redacted/hashes or policy-approved recipient display, bounded retention, least-privilege support access, pagination, export controls, and immutable access audit |
| SMTP log detail | Detail can contain every email address, rendered HTML/text, macro values, template source Account, receipt/error, and payload call ID; private Port comments receive only a narrow special-case reduction | Treat as sensitive message content. Never passthrough raw detail. Define per-template allowlists, privacy/legal retention, encryption, support break-glass access, redaction, safe HTML handling, and deletion/legal-hold policy first |
| failed-delivery persistence and retry | Notification publishing is persisted by default for retry except configured types. The pending document stores the full event payload and failure metadata; the task retries in batches and can leave max-retried records. Voicemail has a multi-hour/day schedule | Unavailable as public operations. Retry payloads require encryption, minimized per-event fields, retention/expiry, account isolation, stable idempotency keys, duplicate-delivery handling, dead-letter status, operator-safe retry/cancel commands, and reconciliation with SMTP logs |
| attachments and outbound fetch | Some notification types attach Fax/voicemail content. System Alert can fetch an event-provided `attachment_url` with an unbounded-looking `kz_http:get` path and use returned type/name/body | Hard-gated egress. Prohibit operator URLs by default; otherwise require fixed trusted origins, DNS/IP rebinding defense, redirect denial or revalidation, strict size/type/time limits, malware policy, generated safe filenames, and no credentials in URLs/logs |
| current GridPBX boundary | GridPBX exposes typed resource-specific notification settings for Fax, Voicemail, and Device behavior, but has no Notification Template SDK client, Laravel domain, API route, MySQL projection, Vue store, or editor | Preserve the separation. Resource destinations remain governed by their installed resource schemas; they do not grant template, sender, HTML, SMTP-log, or retry administration capability |

Monster in this installed checkout exposes Notification and SMTP-log calls in
its generic SDK but contains no Notification Template administration workflow.
It therefore provides no authoritative Basic/Advanced grouping or safe editor
behavior to copy.

This audit intentionally made no live Notification list/detail, body,
SMTP-log, preview, customer-update, reset, or mutation request. Even read
responses can contain sender and recipient addresses, macro definitions, raw
Account identifiers, rendered message bodies, and delivery errors, and
GridPBX has no strict public reduction for them. Notification administration
remains Conditional, not Complete.

Focused verification passed three exact Laravel resource-notification tests
with 24 assertions, two Vue files with 12 tests, the isolated E2E TypeScript
check, and one authenticated headless Account-settings scenario in 2.8
seconds. These checks confirm the current resource-specific validation and
private unknown-channel preservation boundary while the Account editor remains
limited to its reviewed settings contract. No Notification Template, SMTP-log,
preview, customer-update, or delivery request was emitted.

## 36. Security Controls and authentication trust matrix

The installed security surface is not one editable Account entity. Frontier
Access Lists govern SIP registration and request admission, `ip_auth` grants a
Crossbar login based on network source, auth-module configuration controls token
creation and MFA inheritance, token restrictions authorize API paths, and user
recovery can mint a new authenticated token. Each boundary needs a separate
threat model, role, recovery plan, and audit trail.

| Field or workflow | Installed schema/runtime and Monster workflow evidence | GridPBX treatment |
| --- | --- | --- |
| Account and Device Access Lists | `access_lists.json` requires `cidrs` and an `allow,deny` or `deny,allow` order, optionally accepts a `user_agent` regular expression, and permits unknown fields. Crossbar embeds the value in the Account or Device document and asynchronously flushes Frontier's cache | No generic JSON editor. A future command accepts bounded normalized CIDRs plus a safe, bounded expression language, maps a public Account or Device UUID server-side, preserves unknown private fields from an authoritative reread, and reports cache/reconciliation state |
| SIP lockout and bypass semantics | Frontier combines Device and realm lists; the Device key is the raw SIP username. An unknown realm receives a deny-all ACL. Order and list changes can deny every endpoint or broaden admission | Dedicated security-admin permission, step-up authentication, current/effective diff, affected-registration preview, explicit lockout acknowledgement, canary Device, timed rollback, break-glass path, immutable audit, and live registration reconciliation are required before writes |
| system and network ACLs | `acls.json` models `authoritative` or `trusted` network entries. `cb_acls` reads the raw ecallmgr configuration, while ecallmgr also builds ACLs from IP-auth Devices and local/global Resources. Trusted networks can participate in call authorization | Never expose the raw system ACL document or Resource relationships. Platform-only policy must use public references, fixed semantic roles, conflict/overlap detection, emergency-call review, deployment staging, rollback, and independent FreeSWITCH verification |
| hostname-backed ACL entries | ecallmgr can resolve configured Resource hosts into ACL IPs at runtime | Hard-gated by fixed trusted host ownership, resolver policy, DNSSEC where applicable, TTL/refresh behavior, IPv4/IPv6 normalization, private/link-local/metadata denial, rebinding defense, result pinning, drift alerts, and failure-safe behavior |
| source-IP authentication | `PUT /ip_auth` authenticates and authorizes without an existing token, looks up the exact Crossbar client IP in a global Account view, and creates an Account/User token when exactly one Account `ips` entry matches. The view includes a raw `owner_id`, while `ips` is not modeled by the installed public Account schema | Treat each IP grant as a credential, not an Account Advanced field. No form may be invented from this private field. Enable only with an authoritative trusted-proxy chain, canonical IPv4/IPv6 matching, global uniqueness, public Account/User UUID mapping, owner eligibility, bounded lifetime, reason/ticket, step-up, revocation, and audit |
| IP-auth collision and proxy failure | Zero or multiple matches fail, but a misreported proxy address or shared NAT can authenticate the wrong trust boundary; the installed auth config enables `cb_ip_auth` by default | GridPBX must fail closed, never infer from `X-Forwarded-For` without a fixed proxy allowlist, prohibit broad/shared-network grants by policy, continuously detect collisions, and provide a tested recovery path before activation |
| auth-module configuration | Per-module settings include `enabled`, successful/failed attempt logging, token expiry, and an optional MFA block containing raw Account and provider configuration IDs plus descendant inheritance. System config also contains authentication secrets and legacy MD5/SHA choices | Deployment/platform control only. Public forms use semantic capabilities and public UUIDs; raw IDs, signing keys, system keys, API keys, hash material, and provider settings never enter projections or browser state. Prevent disabling the final recovery-capable method and enforce bounded token expiry |
| MFA provider document | `multi_factor_provider.json` requires enabled/name/provider name but allows arbitrary `settings`; Duo settings include integration, secret, and application secret keys plus a provider hostname. Account summaries can merge Account and system providers | Provider credentials stay in a vault and write-only secret inputs. A future public provider has a GridPBX UUID, fixed supported provider type, hostname allowlist, rotation state, health status, inheritance/effective-source display, optimistic concurrency, and no raw settings passthrough |
| MFA enforcement failure behavior | The runtime applies MFA by auth method and Account hierarchy. Critically, if MFA is enabled but no provider resolves, the installed token-creation path logs the failure and still creates a token | Capability remains unavailable until the deployed runtime is proven fail-closed. Missing, disabled, invalid, unreachable, or ambiguous provider state must deny authentication except through a separately audited break-glass procedure |
| Duo challenge and callback | The installed integration signs a five-minute provider request and verifies signed provider/application responses for the raw owner ID. It accepts a configured hostname and logs the complete MFA response at debug level. Monster embeds the legacy Duo iframe and posts its signature back with the original login data | Do not copy the legacy iframe flow. Use a supported hosted/provider protocol with exact origin and callback allowlists, TLS validation, state/nonce/session and intended-user binding, replay prevention, bounded clock skew, CSP, secret-safe logging, provider-outage policy, and tested recovery |
| enrollment and recovery | The installed API manages provider configuration, not a per-user enrollment, recovery-code, trusted-device, or factor-reset lifecycle | MFA is not Complete until enrollment, factor verification, backup codes or approved recovery, lost-device replacement, administrator reset with step-up/two-person policy, notification, session revocation, and immutable audit are explicitly modeled |
| login-attempt collection | MFA attempts are stored in Account monthly databases and exposed through list/detail endpoints. Auth logging persists status, reason, client IP, all request headers, and request-document metadata, which can include credentials, MFA responses, reset identifiers, and raw Account/User data | Never ingest or passthrough raw attempt documents. A future reduced event uses a public UUID and allowlisted metadata, redacts headers and secrets before persistence, encrypts sensitive values, limits access/export, defines retention/legal hold, and audits every detail read |
| token restrictions document | The schema nests auth method, privilege, endpoint, Account matching, path-pattern rules, and HTTP verbs. Missing `allowed_accounts` means any Account, `_` is a catch-all, first matching Account/path rule wins, and a token with no effective restrictions is not restricted | Security-policy code, not a free-form form. Require a typed server-owned endpoint/action vocabulary, deny-by-default baseline, exact precedence, static validation, shadowing detection, representative request simulation, before/after privilege diff, versioning, rollback, and tests that policy cannot loosen platform invariants |
| restriction evaluation and lifecycle | The runtime dynamically selects Account restrictions and falls back to system configuration on each request; super-administrator tokens bypass restrictions. The installed documentation says rules are copied into tokens, but current code performs the lookup during authorization | Follow runtime, not stale documentation. Changes require immediate-session impact preview, cache invalidation proof, explicit super-admin exception policy, actor/target separation, and authoritative request-level verification. No raw Account IDs are accepted in allowed-account rules |
| impersonation | User-auth and token claims support Account/User impersonation and descendant Account context using raw identifiers; authorization and logs are distributed across auth modules | Separate audited support command only: public target UUIDs, short-lived actor-bound elevation, step-up and reason/ticket, descendant/role verification, visible impersonation banner, prohibited sensitive operations, easy exit, session revocation, and immutable actor plus target attribution |
| session inventory and revocation | Token auth can delete the current database token and JWTs have configured expiry, but the inspected surface does not provide a safe GridPBX per-device session inventory or revoke-all lifecycle. GridPBX itself uses a Laravel/Sanctum cookie session independent of Switch user auth | Model GridPBX sessions separately from Switch tokens. Require public opaque session UUIDs, current-session marker, device/IP reduction, expiry/last-use, revoke-one/revoke-all, password/MFA-change invalidation, remember-session policy, CSRF and cookie hardening, and no token value exposure |
| password recovery request | Recovery accepts username plus Account selector and an operator-supplied `ui_url`; distinct validation errors disclose missing/disabled Accounts or Users, and the success response includes the destination email. The reset link and ID are logged | Do not proxy this contract directly. Use a server-owned HTTPS origin and fixed route, uniform response and timing, per-IP/identity/Account throttles, abuse monitoring, verified destination policy, no address disclosure, no reset secret in logs/analytics/referrers, and user notification |
| password recovery consumption | The reset ID encodes the raw Account ID and month, loads the User, marks password update required, saves the User, and creates a token. The inspected path does not delete the reset document or enforce an explicit expiry/single-use check | Unavailable until opaque random hashed tokens are short-lived, single-use, transactionally consumed, purpose/audience bound, invalidated on replacement/use/password change, protected from replay/races, and followed by session revocation. MFA reset remains a separate higher-risk workflow |
| current GridPBX application boundary | GridPBX authenticates its own Users through Laravel's session guard and CSRF-protected Sanctum cookie flow, returns the User's public UUID, and authorizes Account resources through organization roles. Switch API-key/token authentication remains server-to-server with a private cache/provider. No Access List, IP-auth, MFA-provider, token-restriction, session-security, or Switch recovery entity, route, projection, store, or form exists | Preserve this separation. Switch security controls do not become GridPBX login controls by endpoint availability. GridPBX still needs a focused application-auth hardening phase for login throttling, email verification/recovery, MFA, step-up, session inventory, secure production cookie configuration, and security-event audit before advertising an operator Security workspace |
| unknown-field preservation and public identity | Several installed security schemas accept arbitrary fields and relationships use raw Account, User, Device, provider, Resource, SIP username, or owner identifiers | Future writes must authoritatively read/merge/write private documents, preserve unknown fields outside the public allowlist, reject hidden-field round trips, resolve only account-scoped public UUIDs server-side, and prove raw identifiers never appear in API, browser state, logs, previews, or audit metadata |

Monster makes Account Access Lists available only when deployment configuration
explicitly enables `allowAccessList`; its editor validates unique IPv4 CIDRs but
does not model IPv6, Device lists, user-agent matching, impact preview, canary,
rollback, or recovery. Its authentication application handles only the legacy
Duo challenge. These are workflow observations, not a safe GridPBX contract.

This audit intentionally made no live ACL, IP-auth, MFA provider, login-attempt,
token-restriction, token, impersonation, or recovery request. Even reads can
return credentials, auth material, raw identifiers, network trust policy, full
headers, and login metadata; mutations can lock out operators or bypass
authentication. Security Controls remain Conditional, not Complete.

Focused verification passed three Switch SDK token-provider tests with eight
assertions, four Laravel session-boundary tests with 16 assertions, the
isolated E2E TypeScript check, and one authenticated headless Account-settings
scenario in 2.0 seconds. These checks confirm the existing private
server-to-server token refresh, public User UUID session response,
login/logout/unauthenticated behavior, and the reviewed Account form boundary.
No Security Controls endpoint or mutation was exercised.

## 37. Next matrices

After Device, matrices are produced and implemented in dependency order:

1. User, Voicemail, Directory, and LineKey;
2. Callflow and every enabled callflow module, Menu, Group, Queue, Conference,
   TemporalRule, and TemporalRuleSet;
3. carrier actions, CNAM/E911 mutations, Fax message operations, and SMS/MMS;
4. Recording and CallDetailRecord (complete above);
5. Account, Services, SystemStatus, provisioning administration, trunks,
   carriers/resources, billing/reseller management, and webhooks.

No entity is marked schema-complete until its detailed matrix and end-to-end
implementation satisfy the same acceptance standard as Device.
