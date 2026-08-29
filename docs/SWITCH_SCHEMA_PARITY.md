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

## 4. All-entity registry

`Detailed matrix` identifies the field-level audit status, not implementation
completion.

| Entity | Canonical schema or endpoint source | Related objects and additional boundaries | Current implementation | Detailed matrix | Delivery order |
| --- | --- | --- | --- | --- | --- |
| Account | `accounts.json` | hierarchy, limits, service plans, capabilities | Foundation | Pending | 6 |
| Blacklist | `blacklists.json` | account activation and number entries | Foundation | Pending | 4 |
| CallDetailRecord | `cdrs.json` plus MODB CDR endpoints | interactions, recordings, retention | Foundation | Pending | 5 |
| Callflow | `callflows.json` and `callflows.*.json` module schemas | users, devices, groups, queues, menus, temporal routes, numbers | Foundation | Pending | 3 |
| Conference | `conferences.json` and conference action endpoints | users, role numbers, callflows, live participants | Foundation | Form matrix complete below; actions pending | 3 |
| Device | `devices.json` and referenced endpoint schemas | users, registrations, line keys, provisioner, numbers | Foundation | Complete below | 1 |
| Directory | `directories.json` | users and destination callflows | Foundation | Complete below | 2 |
| Fax | `faxbox.json`, `faxes.json`, and document endpoints | users, numbers, callflows, media | Foundation | Pending | 4 |
| Group | `groups.json` | users, devices, ring groups, callflows | Foundation | Pending | 3 |
| LineKey | `devices.combo_key.json` embedded in `devices.provision` | device, provisioner brand/family/model | Foundation | Complete below | 2 |
| Media | `media.json` plus upload/content endpoints | menus, music on hold, prompts | Foundation | Pending | 5 |
| Menu | `menus.json` | media prompts and callflow DTMF branches | Foundation | Pending | 3 |
| PhoneNumber | `phone_numbers.json` plus number-manager feature/action endpoints | callflows, CNAM, E911, porting, carriers, SMS/MMS | Foundation | Pending | 4 |
| Queue | `queues.json`, agent endpoints, and ACDc runtime | users, devices, callflows, agent state/statistics | Foundation | Pending | 3 |
| Recording | MODB recording documents and content endpoints; no single Crossbar CRUD schema | CDRs, storage policy, retention | Foundation | Pending | 5 |
| Services | services, limits, service-plan, ledger, and quote endpoints | accounts, reseller hierarchy, billing provider | Foundation/read-only | Pending | 6 |
| SystemStatus | Crossbar/system health and capability endpoints; no durable entity schema | applications, nodes, registrations, provider health | Foundation/read-only | Pending | 6 |
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
| `smartphone` | name, owner, enabled, forwarding number | Basic, Wi-Fi calling, Options, Restrictions |
| `softphone` | name, owner, enabled | Basic, Caller ID, SIP, Audio, Video, Options, Restrictions; recording and notifications are grouped under Options |
| `landline` | name, owner, enabled, forwarding number | legacy forwarding behavior and contact-list visibility; current-schema extensions are grouped under Advanced forwarding |
| `fax` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Options, Restrictions; T.38 and notifications are grouped under Options |
| `ata` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Options, Restrictions; optional T.38 and notifications are grouped under Options |
| `sip_uri` | name, owner, enabled, SIP URI/route | Basic plus Options containing only contact-list visibility |

Device-type selection controls visibility and defaults; it does not define a
different database table.

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
| `sip.custom_sip_headers.<name>` | string map | Conditional/admin | Advanced / SIP headers | legacy undirected maps hydrate as outbound; authentication headers are denied | Implemented compatibility read; live verification pending |
| `sip.custom_sip_headers.in.<name>` | string map | Conditional/admin | Advanced / SIP headers | bounded name/value rows mapped to a Switch object; authentication headers denied | Implemented; live create/edit/clear pending |
| `sip.custom_sip_headers.out.<name>` | string map | Conditional/admin | Advanced / SIP headers | same as above | Implemented; live create/edit/clear pending |

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
| `provision.feature_keys.<position>` | combo-key object or null | Conditional | Advanced / Feature keys | Account-scoped suggestions resolve only through fixed extension/user and device providers; arbitrary catalog source identifiers are ignored |
| combo-key `type` | `line`, `presence`, `personal_parking`, `speed_dial`, or `parking` | Conditional | key editor | Foundation |
| combo-key `value` | string, parking position 1–10, or `{label,value}` | Conditional | key editor | Foundation |

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
| `caller_id.internal` | Managed from the extension name and number | Implemented |
| `caller_id.external`, `caller_id.emergency`, `caller_id.asserted` | Conditional on number/E911 capability | Pending |
| `caller_id_options.outbound_privacy` | Editable | Implemented |
| `directories.<directory_id>` | Managed through public Directory and Callflow relationships | Foundation; detailed Directory audit next |
| `call_forward` | Editable with the same bounded leaf contract as Device | Pending |
| `call_recording` | Editable with the direction/network matrix | Pending |
| `call_restriction` | Editable from account number classifications | Pending |
| `dial_plan`, `formatters` | Conditional/admin guided editors; no unrestricted JSON | Pending |
| `flags[]` | Conditional/admin allowlisted values | Pending |
| `hotdesk.enabled`, `hotdesk.id`, `hotdesk.keep_logged_in_elsewhere` | Editable in the Extension slide-over through a typed user hotdesk profile | Implemented and live create/edit/clear verified |
| `hotdesk.pin`, `hotdesk.require_pin` | PIN is write-only and redacted; an unchanged configured PIN is preserved through a private read-before-write | Implemented and live preserve/clear verified |
| `media`, `music_on_hold.media_id`, `ringtones` | Capability-gated; media references use public UUIDs | Pending |
| `metaflows` | Conditional guided recursive editor | Pending |
| `password` | Write-only; required on login creation or normalized username change, omitted when unchanged, and never returned or persisted readably | Implemented and live create/unchanged/clear verified |
| `require_password_update` | Editable only while a login username exists | Implemented and live set/clear verified |
| `priv_level` | Administrator-only role mapping; never accepted from ordinary account forms | Pending policy mapping |
| `feature_level` | Capability/service-plan controlled | Pending |
| `profile` | Editable only through a bounded profile schema | Pending |
| `pronounced_name.media_id` | Managed through authorized Media selection/recording | Pending |
| `verified` | Read-only operational status | Pending |
| `vm_to_email_enabled`, `voicemail` | Managed through the Voicemail domain | Foundation; detailed Voicemail matrix complete below |

Current checkpoint: create and edit forms use one domain-owned Zod contract,
Laravel revalidates the boundary, and the implemented configuration is written
through `UserAdvancedData`, the entity-organized `UserHotdeskData`, and
`UserCredentialsData`. Crossbar hashes and deletes the submitted plaintext
password; GridPBX therefore never attempts to read or preserve it. An unchanged
username is updated without resending the password, while username removal
requires explicit UI/API confirmation. The complete redacted response remains
in `switch_json`; only the safe subset and configured-state metadata are returned
as `configuration`. Hotdesk IDs remain
account-scoped Switch values, while primary keys and upstream resource IDs are
not exposed.

The Wave 2 form audit found presentation and contract work that is not visible
in the field table alone. Shared invalid styling and inline-only field error
placement are implemented for User/Extension. Timezone, language, and presence
now use account-backed choices that preserve existing projected values. The
Devices domain publishes the endpoint types and capabilities safe for the
aggregate's intentionally small starter Device; full provisioner catalog and
advanced configuration remain in the Device editor. The remaining work and
intentional aggregate boundaries are recorded in
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
| `transcribe` | Editable schema field; UI reports runtime availability as unknown until Switch authentication capabilities are projected | Implemented with explicit capability caveat |
| `require_pin` | Editable | Implemented |
| `pin` | Write-only, 4–6 digits; omitted on edit preserves the current PIN | Implemented and redacted |
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
| voicemail key maps and account playback keys | Operational/account configuration, not mailbox CRUD | Pending administration workflow |

The Vue create/edit form uses a domain-owned Zod schema and right-hand
slideover. Laravel repeats all trust-boundary validation. A typed
`VoicemailBoxAdvancedData` DTO owns the Switch field mapping, while MySQL keeps
the searchable mailbox fields normalized and stores the complete redacted
response `data` object in `switch_json`. The API returns only the safe
configuration subset and never exposes the PIN. `notify.callback` is returned
as typed safe configuration; the form never edits raw JSON.

The Wave 2 form audit also identified incomplete UI acceptance behavior and
conditional contracts. Shared invalid styling, inline-only field errors,
account-backed timezone/assignment choices, and create-versus-edit PIN behavior
are now implemented for Voicemail. The Switch schema accepts ASR fields, but
the GridPBX session layer does not yet retain the authentication response's
runtime transcription capability; the options endpoint therefore returns an
explicit unknown state and the UI warns without discarding existing values.
External flags are preserved rather than exposed, and the callback object is
typed and bounded end-to-end. Runtime create/edit/clear acceptance remains
documented in [`SWITCH_FORM_AUDIT.md`](SWITCH_FORM_AUDIT.md#voicemail-findings):
the paused callback lifecycle and disposable cleanup passed against the
connected Switch. Unassigned mailbox writes omit `owner_id` because the
connected schema rejects an explicit `null`.

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

The API never accepts Switch User or Callflow identifiers from the UI. It
resolves each public Extension UUID inside the selected account, requires a
projected destination Callflow, and patches only the User `directories`
mapping. The complete redacted Directory response remains in `switch_json`.
Flags remain visible in the safe read projection for diagnostics, but Laravel
prohibits operator flag input and the mutation service preserves existing
values. The Vue slideover uses a domain composable, Zod, a non-clipping
Headless UI sort listbox, shared invalid borders, and inline-only field errors
before Laravel repeats validation at the trust boundary.

## 10. LineKey field-level matrix

Line keys are positions inside a Device's `provision.combo_keys` and
`provision.feature_keys` maps; they are not independent Switch documents.
GridPBX uses a standalone MySQL projection and public UUIDs for UI workflows,
but applies changes as one bounded patch to the owning Device provisioning
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
| `provision.endpoint_brand`, `endpoint_family`, `endpoint_model` | Read-only capability identity in this workflow; edited in Device | Implemented |
| `check_sync_event`, `check_sync_reload`, `check_sync_reboot` | Managed in the Device provisioning panel; reload and reboot are explicit audited Device actions | Implemented and live verified for provisionable Device types |

The right-side editor uses a domain composable and Zod, including duplicate
category/position detection and type-dependent value validation. Laravel and
the typed Switch DTO repeat the same boundary rules. Labeled parking values
are normalized to the integer shape required by `devices.combo_key.json`.
Preview and mutation responses exclude SIP credentials and provisioning
infrastructure, and the complete redacted Device response remains in
`switch_json`.

## 11. Group and Menu field-level matrices

### Group

| Schema path | Treatment | Current status |
| --- | --- | --- |
| `name` | Required bounded editable name | Implemented |
| `endpoints.<id>.type/weight` | Public User, Device, or Group UUIDs are resolved server-side; ordered weights are bounded 1–100 | Implemented |
| `music_on_hold.media_id` | API-backed projected Media UUID; `null` inherits the account default | Implemented |
| `flags[]` | External-application metadata initialized empty and preserved from `switch_json`; prohibited from operator input | Implemented preservation boundary |

### Menu

| Schema path | Treatment | Current status |
| --- | --- | --- |
| `name`, `timeout`, `interdigit_timeout`, `max_extension_length`, `retries` | Required schema-bounded controls with matching Zod and Laravel validation | Implemented |
| `hunt`, `hunt_allow`, `hunt_deny` | Direct-extension dialing and bounded optional patterns | Implemented |
| `allow_record_from_offnet`, `suppress_media` | Explicit boolean controls | Implemented |
| `record_pin` | Write-only 3–6 digit value; blank edit securely preserves the Switch value without returning or persisting it | Implemented |
| `media.greeting` | API-backed projected Media UUID | Implemented |
| `media.invalid_media`, `transfer_media`, `exit_media` | Schema union represented as enabled/system-prompt boolean or projected Media UUID | Implemented |
| `flags[]` | External-application metadata initialized empty and preserved from `switch_json`; prohibited from operator input | Implemented preservation boundary |

The legacy Monster menu presents a smaller prompt workflow. GridPBX keeps the
additional invalid, transfer, and exit prompt controls because they are typed
by the connected schema and mapped without raw JSON editing. Both Group and
Menu slideovers now use domain composables, Zod, Headless UI choices, shared
invalid styling, and isolated authenticated visual acceptance.

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
| `cdr_url`, `recording_url` | Hidden pending outbound URL/SSRF allowlist policy; existing Switch values are preserved and never returned | Intentionally policy-gated |
| queue roster | Public Extension UUIDs resolved to Switch User identifiers and replaced separately | Implemented |
| live agent status | Login, logout, pause, resume, and end-wrapup commands with conditional pause timeout and audit logging | Implemented; no automated live mutation of real agents |

The Queue additions remain virtual projections from the redacted response
`data` object in `switch_json`; normalized MySQL columns are reserved for the
existing searchable operational fields. An isolated authenticated lifecycle
passed create, edit, clear, and cleanup against the connected Switch.

## 13. Conference field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name`, `owner_id` | Required bounded name; public account Extension UUID resolved to a Switch User reference | Implemented |
| `conference_numbers`, `member.numbers`, `moderator.numbers` | Present, unique digit lists; empty lists remain schema-valid | Implemented and live verified |
| `member.pins`, `moderator.pins` | Write-only replacement or explicit clear; never returned or persisted in plaintext | Implemented |
| `member.join_muted`, `member.join_deaf`, `member.play_entry_prompt` | Explicit member behavior controls | Implemented |
| `moderator.join_muted`, `moderator.join_deaf` | Explicit moderator behavior controls | Implemented |
| `max_participants`, `language` | Bounded capacity and prompt-language controls | Implemented |
| `max_members_media` | Public account Media UUID resolved server-side; unresolved existing Switch media is preserved | Implemented |
| `play_entry_tone`, `play_exit_tone` | Schema boolean/string union represented as standard, silent, projected Media, or opaque current-custom preservation | Implemented and live verified |
| `play_name`, `play_welcome`, `require_moderator`, `wait_for_moderator` | Explicit room and moderator behavior controls | Implemented |
| `profile_name`, `caller_controls`, `moderator_controls` | Bounded advanced profile references | Implemented |
| `bridge_username`, `bridge_password`, `domain` | Infrastructure-owned values; not accepted from account operators | Intentionally hidden |
| `flags[]` | External-application metadata; not operator-editable | Intentionally hidden/preserved by Switch partial update |
| `focus` | Read-only media-server location | Intentionally read-only |
| `controls`, `profile` | Arbitrary nested Switch configuration; no raw JSON editor in the simplified UI | Intentionally advanced/opaque |
| lock/unlock, participant mute/deaf/kick actions | Runtime conference operations require a separate audited command surface | Pending |

The typed sound fields are read from the redacted response `data` stored in
`switch_json`; no JSON-derived Conference column was added to MySQL. The
isolated authenticated lifecycle passed visual validation, create, edit, and
cleanup against the connected Switch.

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
| `flags[]` | External-application metadata preserved from `switch_json`; prohibited from operator input | Implemented preservation boundary |
| effective status | GridPBX schedule projection evaluated in the account timezone and kept distinct from the manual override | Implemented |

### Temporal Rule Set

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required bounded editable name | Implemented |
| `temporal_rules[]` | Non-empty ordered membership; public Rule UUIDs resolve to account-scoped Switch identifiers | Implemented and live verified |
| `flags[]` | External-application metadata preserved from `switch_json`; prohibited from operator input | Implemented preservation boundary |
| enable/disable/reset | Confirmed command applies to every resolved member under a lock with partial-failure compensation | Implemented and live verified |
| Callflow and membership lifecycle | Delete is rejected while the Rule Set is referenced by temporal routing; successful deletion removes membership rows so member Rules can be removed safely | Implemented and live verified |

Both right-side panels use domain composables, Zod, Headless UI where an
interactive primitive adds value, shared red invalid controls, and inline-only
field errors. Ordinary edits preserve existing operational overrides, and no
new JSON-derived MySQL columns were added; the complete redacted response
`data` remains in `switch_json`. The isolated lifecycle passed create, edit,
override preservation, force/reset commands, ordered Rule Set creation, and
cleanup against the connected Switch.

## 15. Next matrices

After Device, matrices are produced and implemented in dependency order:

1. User, Voicemail, Directory, and LineKey;
2. Callflow and every enabled callflow module, Menu, Group, Queue, Conference,
   TemporalRule, and TemporalRuleSet;
3. PhoneNumber, Blacklist, Fax, carrier actions, CNAM, E911, and SMS/MMS;
4. Media, Recording, and CallDetailRecord;
5. Account, Services, SystemStatus, provisioning administration, trunks,
   carriers/resources, billing/reseller management, and webhooks.

No entity is marked schema-complete until its detailed matrix and end-to-end
implementation satisfy the same acceptance standard as Device.
