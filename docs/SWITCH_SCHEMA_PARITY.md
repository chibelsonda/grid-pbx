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
| Account | `accounts.json` | hierarchy, limits, service plans, capabilities | Foundation | Safe projection matrix complete below; typed settings mutations pending | 6 |
| Blacklist | `blacklists.json` | account activation and number entries | Foundation | Complete below | 4 |
| CallDetailRecord | `cdrs.json` plus MODB CDR endpoints | interactions, recordings, retention | Foundation | Safe read/filter/relationship matrix complete below; retention remains policy-gated | 5 |
| Callflow | `callflows.json` and `callflows.*.json` module schemas | users, devices, groups, queues, menus, temporal routes, numbers | Foundation | Full-width main-page safe recursive workspace, node inspector, 73-module schema reference palette, root, entry-point, wildcard fallback, Menu keys, Rule Set routing, and ordered direct Temporal Rule match routes complete; drag-and-drop and deeper recursive mutation editors pending | 3 |
| Conference | `conferences.json` and conference action endpoints | users, role numbers, callflows, live participants | Foundation | Form matrix complete below; actions pending | 3 |
| Device | `devices.json` and referenced endpoint schemas | users, registrations, line keys, provisioner, numbers | Foundation | Complete below | 1 |
| Directory | `directories.json` | users and destination callflows | Foundation | Complete below | 2 |
| Fax | `faxbox.json`, `faxes.json`, and document endpoints | users, numbers, callflows, media | Foundation | Fax Box form matrix complete below; message mutations remain gated | 4 |
| Group | `groups.json` | users, devices, ring groups, callflows | Foundation | Complete below | 3 |
| LineKey | `devices.combo_key.json` embedded in `devices.provision` | device, provisioner brand/family/model | Foundation | Complete below | 2 |
| Media | `media.json` plus upload/content endpoints | menus, music on hold, prompts | Foundation | Upload/audio/MOH matrix complete below; generated sources gated | 5 |
| Menu | `menus.json` | media prompts and callflow DTMF branches | Foundation | CRUD form audited; root-level DTMF/timeout routing delivered | 3 |
| PhoneNumber | `phone_numbers.json` plus number-manager feature/action endpoints | callflows, CNAM, E911, porting, carriers, SMS/MMS | Foundation | Safe read/detail matrix complete below; mutations policy-gated | 4 |
| Queue | `queues.json`, agent endpoints, and ACDc runtime | users, devices, callflows, agent state/statistics | Foundation | Pending | 3 |
| Recording | MODB recording documents and content endpoints; no single Crossbar CRUD schema | CDRs, storage policy, retention | Foundation | Safe metadata/playback matrix complete below; deletion and retention remain policy-gated | 5 |
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
| `caller_id.internal` | Editable as bounded text, initially derived from the managed extension identity | Implemented for managed edits |
| `caller_id.external`, `caller_id.emergency` | Public account-number selections; emergency selection requires projected E911 capability | Implemented for managed edits with unresolved-current-value preservation |
| `caller_id.asserted` | Switch-managed and never exposed as editable identity; unknown metadata is preserved server-side | Implemented boundary |
| `caller_id_options.outbound_privacy` | Editable | Implemented |
| `directories.<directory_id>` | Managed through public Directory and Callflow relationships | Foundation; detailed Directory audit next |
| `call_forward` | Editable through the current eight-field bounded leaf contract; destination accepts internal extensions and dialable public numbers | Implemented for managed edits |
| `call_recording` | Editable with the current User direction/network matrix; Account/Endpoint branches are Account-schema-only and storage URLs stay server-owned | Implemented for managed edits |
| `call_restriction` | Editable from live Switch number classifications, including preserved projected legacy keys | Implemented for managed edits |
| `dial_plan`, `formatters` | Guided bounded editors with safe-regex checks; no unrestricted JSON | Implemented for managed edits with retained-rule unknown metadata preservation |
| `flags[]` | Values owned by external applications | Read-only count exposed; values preserved server-side and never accepted from Vue |
| `hotdesk.enabled`, `hotdesk.id`, `hotdesk.keep_logged_in_elsewhere` | Editable in the Extension slide-over through a typed user hotdesk profile | Implemented and live create/edit/clear verified |
| `hotdesk.pin`, `hotdesk.require_pin` | PIN is write-only and redacted; an unchanged configured PIN is preserved through a private read-before-write | Implemented and live preserve/clear verified |
| `media` | Ordered current-schema audio/video codecs plus bounded bypass, encryption, T.38, early-media, and progress-timeout controls | Implemented for managed edits; unknown nested properties are preserved server-side |
| `music_on_hold.media_id` | Account-scoped public Media UUID with explicit unresolved-current-value preservation | Implemented for managed edits |
| `ringtones.internal`, `ringtones.external` | Bounded Alert-Info header values | Implemented for managed edits |
| `metaflows` | Shared guided recursive editor with bounded activation controls and public resource references | Implemented for managed User edits; unsupported/unresolved roots lock and preserve |
| `password` | Write-only; required on login creation or normalized username change, omitted when unchanged, and never returned or persisted readably | Implemented and live create/unchanged/clear verified |
| `require_password_update` | Editable only while a login username exists | Implemented and live set/clear verified |
| `priv_level` | Administrator-only role mapping; never accepted from ordinary account forms | Read-only status implemented; policy mutation intentionally excluded |
| `feature_level` | Capability/service-plan controlled | Read-only status implemented; mutation intentionally excluded |
| `profile` | Bounded addresses, assistant, birthday, nicknames, note, role, sort string, and title | Implemented for managed edits |
| `pronounced_name.media_id` | Account-scoped public Media UUID with unresolved-current-value preservation | Implemented for managed edits |
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

Extension create and edit reuse the complete Voicemail field component inside
the same right-hand aggregate drawer. The embedded flow derives the mailbox
name, number, and owner relationship but delegates field validation and Switch
mutation mapping to the Voicemail domain. Standalone and aggregate operations
therefore cannot drift into separate Kazoo payload contracts. Isolated live
verification covers aggregate create, full edit hydration, advanced
callback/audio clearing, persistence reload, and cleanup.

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

The Fax Box slide-over uses a domain composable, Zod, Headless UI choices,
shared invalid controls, and inline-only errors. No new MySQL fields were added
for hidden JSON values. Focused component, Laravel, Switch package, and isolated
authenticated Playwright checks pass without creating a live Fax Box.

## 17. Phone Number field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| number identity, `state`, `used_by`, carrier, active `features[]` | Normalized searchable MySQL projection using a server-owned numeric primary key and public UUID; Switch number remains a display value | Implemented |
| `_read_only.features.available` | Preferred current runtime feature capability source; never writable | Implemented |
| `_read_only.features_available` / root `features_available` | Version-aware compatibility fallback for older Switch responses | Implemented and package-tested |
| `cnam.display_name`, `cnam.inbound_lookup` | Typed allowlisted detail projection | Implemented read-only |
| `e911.status`, caller name, address, and notification emails | Typed allowlisted virtual values from redacted `switch_json`; provider location IDs and coordinates are not exposed | Implemented read-only |
| `porting.requested_port_date`, `porting.service_provider` | Minimal operational summary; billing identifiers, comments, and customer/billing details remain hidden | Implemented read-only |
| callflow assignment | Resolved through the Callflow domain with public UUIDs; not duplicated as a Phone Number CRUD control | Implemented |
| CNAM and E911 mutation | Requires both runtime feature availability and an approved server-side billing/compliance confirmation policy | Intentionally policy-gated |
| purchase, reserve, activate, port, release | Separate carrier commands, never generic CRUD; require provider capabilities, quote/billing behavior, authorization, confirmation, and audit | Intentionally capability-gated |
| SMS/MMS and carrier-specific features | Must be discovered from the connected provider rather than inferred from the base schema | Intentionally capability-gated |

The Phone Number detail slide-over now shows the safe schema-backed state and
an explicit operational capability matrix. “Available” from Switch does not
mean “writable” in GridPBX: schema presence and carrier support are distinct
from authorization, billing, and compliance policy. Focused Switch package,
Laravel, Vue, TypeScript, and isolated authenticated Playwright checks pass
without executing any carrier mutation.

## 18. Media field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| `name` | Required trimmed name, 1–128 characters, with matching Zod and Laravel validation | Implemented |
| `description` | Optional 1–128 character schema value; blank removes the field | Implemented |
| `language` | Optional bounded product input with `en-us` create default | Implemented |
| `streamable` | Explicit boolean control | Implemented |
| `media_source` | Operator upload workflow always creates `upload`; existing `recording` and `tts` values are preserved and cannot be injected through metadata CRUD | Implemented boundary |
| raw audio | Required MP3/WAV/OGG create upload and confirmed replacement, maximum 5 MB; streamed through the authorized API and never duplicated in MySQL | Implemented |
| `content_type`, `content_length` | Refreshed from Switch after upload; content type is retained during metadata updates and length remains Switch-owned | Implemented |
| `prompt_id`, `source_id`, `source_type`, `tts` | Hidden schema-owned values preserved through typed DTOs during metadata updates; not accepted from operator payloads | Implemented preservation boundary |
| generated TTS and callflow recording | Separate provider/runtime operations rather than generic metadata CRUD | Intentionally capability-gated |
| account music on hold | Public Media UUID resolved server-side; Headless UI selection may set or clear the account reference | Implemented |
| deletion | Dependency summary covers music on hold, voicemail greetings, and Callflows before deletion | Implemented |

The Media forms use domain composables, Zod, shared red invalid controls,
inline-only API errors, and `novalidate`. The account-default selector uses the
viewport-bounded Headless UI listbox. Focused Switch package, Laravel, Vue,
TypeScript, and isolated authenticated Playwright checks pass without creating
or replacing live audio.

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

## 20. Account field-level matrix

| Schema path or operation | Treatment | Current status |
| --- | --- | --- |
| public `id` | GridPBX UUID only; internal `account_id` and Switch account ID never cross the API | Implemented |
| `name`, `realm`, `timezone`, `enabled` | Name/timezone are typed administrator settings; realm and enabled state remain read-only | Partially implemented |
| organization relationship | Public organization UUID and display name, scoped through authenticated membership | Implemented |
| projected resource counts | Tenant-scoped Extension, Device, Phone Number, Callflow, Voicemail, Queue, Media, and Recording counts | Implemented |
| `org`, `language` | Typed nullable identity/default settings with explicit clear semantics | Implemented |
| `music_on_hold.media_id` | Managed by the existing account Media workflow using a public Media UUID | Implemented in Media domain |
| `blacklists[]` | Managed by the Blacklist domain and account activation workflow | Implemented in Blacklist domain |
| `call_waiting.enabled`, `do_not_disturb.enabled`, `caller_id_options.outbound_privacy`, `caller_id_options.show_rate`, `ringtones.internal`, `ringtones.external` | Typed calling defaults; no raw nested JSON editor | Implemented |
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
Device/User/Group ordering workflow; GridPBX currently exposes only direct
Device endpoints so fan-out and total attempt duration remain enforceable.

| Schema path or operation | GridPBX treatment | Current status |
| --- | --- | --- |
| `strategy = simultaneous` or `single` | Guided as At the same time or In order | Implemented |
| `strategy = weighted_random` | Existing values remain private and read-only until weight semantics and operator expectations are modeled | Capability-gated |
| `endpoints[]` with `endpoint_type = device` and raw `id` | One to twenty ordered account-scoped public Device UUIDs; Laravel resolves raw Switch IDs only for the SDK write | Implemented |
| `endpoints[]` with `endpoint_type = user` or `group` | Runtime expands memberships into devices; kept read-only until expansion, deduplication, inactive-member filtering, authorization, and final fan-out can be enforced | Capability-gated |
| endpoint `delay` | Guided integer `0`–`60`; in-order strategy requires `0` | Implemented |
| endpoint `timeout` | Guided integer `1`–`60` | Implemented |
| top-level `timeout` | Server-computed per attempt: maximum `delay + timeout` for simultaneous and sum of endpoint timeouts for in-order; capped at `120`; never accepted or exposed publicly | Implemented |
| `repeats` | Guided integer `1`–`3` | Implemented |
| `skip_module` | Guided boolean | Implemented |
| `ringback`, `ringtones`, `ignore_forward`, `fail_on_single_reject`, endpoint `weight`/`disable_until`, and unknown properties | Hidden from public API/UI and merged losslessly by the Switch DTO; unsafe or unsupported current shapes are read-only | Preservation boundary implemented |

A 2026-08-30 disposable isolated-headless lifecycle verified creation below
Page Group, simultaneous-to-in-order editing, delay reset, bounded timeout and
attempts, `skip_module`, authoritative reopen, public Device UUID to raw Switch
endpoint mapping, computed top-level timeout, browser deletion, MySQL soft
deletion, and no matching active Switch callflow. Crossbar stripped attempted
live private markers, so private/unknown-field preservation is claimed from the
focused SDK regression test; direct CouchDB writes were not used. No media-leg
call was originated, so this matrix records a verified guided foundation rather
than full Ring Group completion.

## 23. Next matrices

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
