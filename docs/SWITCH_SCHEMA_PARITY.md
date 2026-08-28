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
| Conference | `conferences.json` and conference action endpoints | users, role numbers, callflows, live participants | Foundation | Pending | 3 |
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
| TemporalRule | `temporal_rules.json` plus enable/disable/reset actions | callflows, rule sets, account timezone | Foundation | Pending | 3 |
| TemporalRuleSet | `temporal_rules_sets.json` plus member rule actions | temporal rules and callflows | Foundation | Pending | 3 |
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
| `cellphone` | name, owner, enabled, forwarding number | forwarding behavior, contact-list visibility |
| `smartphone` | name, owner, enabled, forwarding number | Basic, Wi-Fi calling, Options, Restrictions |
| `softphone` | name, owner, enabled | Basic, Caller ID, SIP, Audio, Video, Options, Restrictions; recording and notifications are grouped under Options |
| `landline` | name, owner, enabled, forwarding number | forwarding behavior, contact-list visibility |
| `fax` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Options, Restrictions; T.38 and notifications are grouped under Options |
| `ata` | name, owner, enabled, MAC, provisioning | Basic, Caller ID, SIP, Options, Restrictions; optional T.38 and notifications are grouped under Options |
| `sip_uri` | name, owner, enabled, SIP URI/route | Basic and Options |

Device-type selection controls visibility and defaults; it does not define a
different database table.

### 5.2 Core, relationship, and operational fields

| Schema path | Type/default | GridPBX treatment | UI location | MySQL and security treatment | Current status |
| --- | --- | --- | --- | --- | --- |
| `name` | string, required | Editable | Basic | normalized `name`; retained in `switch_json` | Implemented |
| `device_type` | string | Editable from supported type set | Device type selector | normalized; retained | Implemented for all eight types; full runtime matrix pending |
| `enabled` | boolean, default `true` | Editable | Basic | normalized `is_enabled`; retained | Implemented |
| `owner_id` | Switch object ID | Editable through public extension/user UUID | Basic / Assignment | normalized relationship; upstream ID never exposed; unassignment removes the key from a preserved full-document update | Implemented and runtime-verified |
| `mac_address` | string | Conditional for provisionable hardware | Basic / Provisioning | normalized; retained | Implemented without type/capability gating |
| `language` | string | Editable with account-supported options | Advanced / Locale | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `timezone` | string | Editable with account default/inheritance | Advanced / Locale | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `presence_id` | string | Editable | Advanced / Presence | application virtual field from `switch_json`; empty string is the runtime-verified clear value | Implemented and runtime-verified for create/edit/clear |
| `do_not_disturb.enabled` | boolean | Editable | Advanced / Options | `switch_json` | Implemented |
| `call_waiting.enabled` | boolean | Editable | Advanced / Options | `switch_json` | Implemented |
| `exclude_from_queues` | boolean, default `false` | Editable when queues are available | Advanced / Options | `switch_json`; optional normalized agent projection | Implemented |
| `contact_list.exclude` | boolean, default `false` | Editable | Advanced / Options | `switch_json` | Implemented |
| `music_on_hold.media_id` | Switch media ID | Editable using public media UUID | Advanced / Routing and endpoint behavior | relationship resolved server-side; upstream ID hidden | Implemented; live create/edit/clear pending |
| `mwi_unsolicited_updates` | boolean, default `true` | Editable | Advanced / Options / Notifications | `switch_json` | Implemented for applicable types |
| `register_overwrite_notify` | boolean, default `false` | Editable | Advanced / Options / Notifications | `switch_json` | Implemented for applicable types |
| `suppress_unregister_notifications` | boolean, default `false` | Editable using positive UI wording | Advanced / Options / Notifications | `switch_json` | Implemented for applicable types |
| `hotdesk.users` | map keyed by Switch user ID | Managed | Advanced / Metaflows and hotdesk | only a safe active-user count is exposed; membership and upstream IDs remain in the dedicated People workflow | Read-only count implemented; membership workflow pending |
| `flags[]` | string array | Conditional/admin | Advanced / Routing flags | `switch_json` | Missing |
| `outbound_flags[]` | string array, or `static[]`/`dynamic[]` | Conditional/admin | Advanced / Routing flags | legacy flat arrays hydrate as `static`; writes use the object variant in `switch_json` | Implemented; live create/edit/clear pending |

### 5.3 Call forwarding

| Schema path | Type/default | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `call_forward.enabled` | boolean, default `false` | Editable | Basic for external-number types; Advanced / Options | Implemented |
| `call_forward.number` | string, max 15 | Editable | Basic for cellphone/landline/smartphone | Implemented |
| `call_forward.direct_calls_only` | boolean, default `false` | Editable | Advanced / Options | Implemented |
| `call_forward.failover` | boolean, default `false` | Editable | Advanced / Options | Implemented |
| `call_forward.ignore_early_media` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.keep_caller_id` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.require_keypress` | boolean, default `true` | Editable | Advanced / Options | Implemented |
| `call_forward.substitute` | boolean, default `true` | Editable | Advanced / Options | Implemented |

All forwarding fields remain in redacted `switch_json`; the number may be
normalized later only if account-wide routing search requires it.

### 5.4 SIP

| Schema path | Type/default | Treatment | UI location | MySQL and security treatment | Current status |
| --- | --- | --- | --- | --- | --- |
| `sip.method` | `password` or `ip`; default `password` | Editable | Advanced / SIP | `switch_json` | Missing |
| `sip.username` | string | Write-only on mutation; masked configured state on read | Basic or Advanced / SIP | never persisted or returned as raw credential unless policy later explicitly permits username display | Partial |
| `sip.password` | string | Write-only | Basic or Advanced / SIP | redact before logs, MySQL, exceptions, and responses | Implemented write-only |
| `sip.realm` | string | Read-only by default; conditional override | Advanced / SIP | redacted safe value in `switch_json` | Missing |
| `sip.expire_seconds` | integer, default `300` | Editable | Advanced / SIP | `switch_json` | Missing |
| `sip.invite_format` | enum, default `contact` | Editable | Advanced / SIP | `switch_json` | Missing |
| `sip.ip` | string | Conditional when method is `ip` | Advanced / SIP | safe projection only for authorized admins; otherwise masked | Missing |
| `sip.number` | string | Conditional on invite format | Advanced / SIP | `switch_json` | Missing |
| `sip.route` | string | Conditional on invite format `route`/SIP URI | Basic for `sip_uri`; Advanced otherwise | validate as SIP URI; redact embedded credentials | Missing |
| `sip.static_route` | string | Conditional/admin | Advanced / SIP | validate and redact embedded credentials | Missing |
| `sip.ignore_completed_elsewhere` | boolean | Editable | Advanced / Miscellaneous | `switch_json` | Missing |
| `sip.custom_sip_headers.<name>` | string map | Conditional/admin | Advanced / SIP headers | legacy undirected maps hydrate as outbound; authentication headers are denied | Implemented compatibility read; live verification pending |
| `sip.custom_sip_headers.in.<name>` | string map | Conditional/admin | Advanced / SIP headers | bounded name/value rows mapped to a Switch object; authentication headers denied | Implemented; live create/edit/clear pending |
| `sip.custom_sip_headers.out.<name>` | string map | Conditional/admin | Advanced / SIP headers | same as above | Implemented; live create/edit/clear pending |

### 5.5 Caller ID and privacy

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `caller_id.internal.name` | string | Editable | Advanced / Caller ID | Missing |
| `caller_id.internal.number` | string | Editable from authorized numbers | Advanced / Caller ID | Missing |
| `caller_id.external.name` | string | Editable subject to carrier/account rules | Advanced / Caller ID | Missing |
| `caller_id.external.number` | string | Editable from authorized numbers | Advanced / Caller ID | Missing |
| `caller_id.emergency.name` | string | Conditional on E911 capability | Advanced / Emergency caller ID | Missing |
| `caller_id.emergency.number` | string | Conditional; select only E911-enabled numbers | Advanced / Emergency caller ID | Missing |
| `caller_id.asserted.name` | string | Conditional/admin | Advanced / Asserted identity | Missing |
| `caller_id.asserted.number` | string | Conditional/admin | Advanced / Asserted identity | Missing |
| `caller_id.asserted.realm` | string | Conditional/admin | Advanced / Asserted identity | Missing |
| `caller_id_options.outbound_privacy` | `full`, `name`, `number`, or `none` | Editable | Advanced / Caller ID | Missing |

Caller ID values are retained in `switch_json`. Number selection is resolved
through GridPBX public phone-number UUIDs where a relationship exists.

### 5.6 Media

| Schema path | Type/default | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `media.audio.codecs[]` | unique ordered codec enum | Editable | Advanced / Audio | Missing |
| `media.video.codecs[]` | unique ordered codec enum | Editable | Advanced / Video | Missing |
| `media.bypass_media` | boolean or legacy `auto` | Editable with compatibility handling | Advanced / Media | Missing |
| `media.encryption.enforce_security` | boolean, default `false` | Editable | Advanced / Encryption | Missing |
| `media.encryption.methods[]` | `zrtp`, `srtp` | Editable/capability-gated | Advanced / Encryption | Missing |
| `media.fax_option` | boolean | Conditional for fax/ATA | Advanced / Media | Missing |
| `media.ignore_early_media` | boolean | Editable | Advanced / Media | Missing |
| `media.progress_timeout` | integer seconds | Editable | Advanced / Media | Missing |

Codec order is significant and must be preserved by DTOs, the API, and UI
drag/reorder controls.

### 5.7 Restrictions, dial plans, and formatters

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `call_restriction.<classification>.action` | `inherit` or `deny` | Editable from live account number classifications; unknown stored keys remain editable | Advanced / Restrictions | Implemented; live create/edit matrix pending |
| `dial_plan.system[]` | string array | Conditional/admin | Advanced / Dial plan | Implemented; live create/edit/clear pending |
| `dial_plan.<regex>.description` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field; live verification pending |
| `dial_plan.<regex>.prefix` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field; live verification pending |
| `dial_plan.<regex>.suffix` | string | Conditional/admin | Advanced / Dial plan | Implemented as a bounded rule-row virtual field; live verification pending |
| `formatters.<field>[]` | ordered formatter rules | Conditional/admin | Advanced / Formatters | Missing |
| formatter `direction` | `inbound`, `outbound`, or `both` | Conditional/admin | formatter editor | Missing |
| formatter `match_invite_format` | boolean | Conditional/admin | formatter editor | Missing |
| formatter `prefix` / `suffix` / `value` | string | Conditional/admin | formatter editor | Missing |
| formatter `regex` | regex string | Conditional/admin | formatter editor | Missing |
| formatter `strip` | boolean | Conditional/admin | formatter editor | Missing |

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
| `provision.endpoint_brand` | string | Conditional | Basic / Hardware | Partial as free text |
| `provision.endpoint_family` | string | Conditional | Basic / Hardware | Missing |
| `provision.endpoint_model` | string or integer | Conditional | Basic / Hardware | Partial as free text |
| `provision.check_sync_event` | string | Managed/admin | Operational controls | Missing |
| `provision.check_sync_reload` | string | Managed/admin | Reload command configuration | Missing |
| `provision.check_sync_reboot` | string | Managed/admin | Reboot command configuration | Missing |
| `provision.combo_keys.<position>` | combo-key object or null | Conditional | Advanced / Line keys | Foundation editor implemented; parity review required |
| `provision.feature_keys.<position>` | combo-key object or null | Conditional | Advanced / Feature keys | Foundation editor implemented; parity review required |
| combo-key `type` | `line`, `presence`, `personal_parking`, `speed_dial`, or `parking` | Conditional | key editor | Foundation |
| combo-key `value` | string, parking position 1–10, or `{label,value}` | Conditional | key editor | Foundation |

Provisioning mutations remain capability-gated. GridPBX never exposes vendor
credentials, provisioning URLs containing secrets, templates containing
secrets, or generated endpoint documents.

### 5.10 Ringtones and metaflows

| Schema path | Type | Treatment | UI location | Current status |
| --- | --- | --- | --- | --- |
| `ringtones.internal` | string | Editable | Advanced / Ringtones | Implemented and runtime-verified for create/edit/clear |
| `ringtones.external` | string | Editable | Advanced / Ringtones | Implemented and runtime-verified for create/edit/clear |
| `metaflows.binding_digit` | DTMF enum, default `*` | Conditional/admin | Advanced / In-call features | Implemented; live create/edit/clear pending |
| `metaflows.digit_timeout` | non-negative integer | Conditional/admin | Advanced / In-call features | Implemented with a 60000 ms UI/API safety cap; live verification pending |
| `metaflows.listen_on` | `both`, `self`, or `peer` | Conditional/admin | Advanced / In-call features | Implemented; live create/edit/clear pending |
| `metaflows.numbers.<digits>` | recursive metaflow | Conditional/admin | dedicated guided editor | Preserved during Device edits and exposed only as a count; guided editor pending |
| `metaflows.patterns.<pattern>` | recursive metaflow | Conditional/admin | dedicated guided editor | Preserved during Device edits and exposed only as a count; guided editor pending |
| metaflow `module` | string, required | Conditional/admin | guided node editor | Missing |
| metaflow `data` | module-specific object | Conditional/admin | module-specific controls | Missing |
| metaflow `children` | recursive object | Conditional/admin | guided node editor | Missing |

Metaflow module `data` must be validated against the selected module contract;
GridPBX will not provide an unrestricted JSON editor to ordinary account users.

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

- account-derived restriction classification editing;
- custom SIP header editing and sensitive-header policy;
- dial-plan and formatter editors;
- music-on-hold selection;
- hotdesk operational management;
- external and outbound routing flags;
- asserted caller-ID administration and E911 capability-driven number choices;
- provisioning template discovery, reload/reboot commands, and complete
  line-key parity;
- ringtone, codec, and other ordered-value reordering where order is material;
- metaflow module discovery and guided recursive editing.

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
| `hotdesk.enabled`, `hotdesk.id`, `hotdesk.keep_logged_in_elsewhere` | Editable through a dedicated hotdesk workflow | Pending |
| `hotdesk.pin` | Write-only and redacted; `require_pin` controls requirement | Pending |
| `media`, `music_on_hold.media_id`, `ringtones` | Capability-gated; media references use public UUIDs | Pending |
| `metaflows` | Conditional guided recursive editor | Pending |
| `password` | Write-only credential workflow; never returned or persisted readably | Pending |
| `require_password_update` | Managed with the GUI password workflow | Pending |
| `priv_level` | Administrator-only role mapping; never accepted from ordinary account forms | Pending policy mapping |
| `feature_level` | Capability/service-plan controlled | Pending |
| `profile` | Editable only through a bounded profile schema | Pending |
| `pronounced_name.media_id` | Managed through authorized Media selection/recording | Pending |
| `verified` | Read-only operational status | Pending |
| `vm_to_email_enabled`, `voicemail` | Managed through the Voicemail domain | Foundation; detailed Voicemail matrix complete below |

Current checkpoint: create and edit forms use one domain-owned Zod contract,
Laravel revalidates the boundary, and the implemented configuration is written
through `UserAdvancedData`. The complete redacted response remains in
`switch_json`; only the safe subset is returned as `configuration`.

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
| `transcribe` | Editable when an ASR provider is configured | Implemented |
| `require_pin` | Editable | Implemented |
| `pin` | Write-only, 4–6 digits; omitted on edit preserves the current PIN | Implemented and redacted |
| `is_setup` | Read-only mailbox setup status | Implemented |
| `check_if_owner` | Editable, default `true` | Implemented |
| `delete_after_notify` | Editable, default `false` | Implemented |
| `include_message_on_notify` | Editable, default `true` | Implemented |
| `include_transcription_on_notify` | Editable, default `true` | Implemented |
| `media_extension` | Editable enum: `mp3`, `mp4`, or `wav` | Implemented |
| `not_configurable` | Editable, default `false` | Implemented |
| `oldest_message_first` | Editable, default `false` | Implemented |
| `save_after_notify` | Editable; Switch precedence over `delete_after_notify` is preserved | Implemented |
| `skip_envelope` | Editable beta Switch field | Implemented |
| `skip_greeting`, `skip_instructions` | Editable playback controls | Implemented |
| `is_voicemail_ff_rw_enabled` | Editable playback control | Implemented |
| `seek_duration_ms` | Editable non-negative integer; GridPBX safety cap is 300000 ms | Implemented |
| `media.unavailable` | Managed through the authenticated greeting upload/remove workflow | Implemented |
| messages, folders, raw audio, and transcription | Managed through mailbox message workflows; audio is streamed | Implemented |
| `announcement_only` | Hidden because the upstream schema marks it unsupported | Deliberately excluded |
| `flags[]` | Conditional/admin allowlisted values | Pending |
| `notify.callback` | Managed through a dedicated bounded callback workflow | Pending |
| voicemail key maps and account playback keys | Operational/account configuration, not mailbox CRUD | Pending administration workflow |

The Vue create/edit form uses a domain-owned Zod schema and right-hand
slideover. Laravel repeats all trust-boundary validation. A typed
`VoicemailBoxAdvancedData` DTO owns the Switch field mapping, while MySQL keeps
the searchable mailbox fields normalized and stores the complete redacted
response `data` object in `switch_json`. The API returns only the safe
configuration subset and never exposes the PIN.

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
| `flags[]` | Editable advanced integration metadata; maximum 20 unique 64-character strings | Implemented |
| `users[]` | Managed through public Extension UUIDs and resolved User/Callflow mappings | Implemented |

The API never accepts Switch User or Callflow identifiers from the UI. It
resolves each public Extension UUID inside the selected account, requires a
projected destination Callflow, and patches only the User `directories`
mapping. The complete redacted Directory response remains in `switch_json`;
the API exposes safe flags and public member relationships only. The Vue
slideover uses a domain composable and Zod before Laravel repeats validation at
the trust boundary.

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
| `check_sync_event`, `check_sync_reload`, `check_sync_reboot` | Managed provisioning commands, never ordinary line-key form fields | Pending provisioning administration |

The right-side editor uses a domain composable and Zod, including duplicate
category/position detection and type-dependent value validation. Laravel and
the typed Switch DTO repeat the same boundary rules. Labeled parking values
are normalized to the integer shape required by `devices.combo_key.json`.
Preview and mutation responses exclude SIP credentials and provisioning
infrastructure, and the complete redacted Device response remains in
`switch_json`.

## 11. Next matrices

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
