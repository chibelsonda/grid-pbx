# Local provisioning catalog

GridPBX discovers Device brand, family, and model choices from the
Monster-compatible `GET /api/phones` contract.

Docker Compose runs a small discovery-only catalog at
`http://localhost:8082/api/phones` and configures the API to use
`http://provisioner-catalog/api`. Its source data is
`docker/provisioner-catalog/phones.json`.

This service is not a phone provisioning server. It does not store account or
Device documents, render phone configuration files, or communicate with
physical phones. It exists so local UI and API development exercise live HTTP
catalog discovery without importing the legacy provisioner and its CouchDB
databases into this repository.

For a real deployment, configure an external OpenTelecom-compatible
provisioner and set:

```dotenv
SWITCH_PROVISIONER_URL=https://provisioner.example.com/api
SWITCH_PROVISIONER_AUTH_TYPE=bearer
SWITCH_PROVISIONER_TOKEN=replace-with-a-secret-from-the-client
SWITCH_PROVISIONER_VERIFY_TLS=true
```

Supported authentication modes are:

- `none` for a network-restricted endpoint with no HTTP authentication;
- `bearer` using `SWITCH_PROVISIONER_TOKEN`;
- `basic` using `SWITCH_PROVISIONER_USERNAME` and `SWITCH_PROVISIONER_PASSWORD`;
- `header` using `SWITCH_PROVISIONER_HEADER_NAME` and `SWITCH_PROVISIONER_TOKEN`.

Do not put provisioner credentials in `VITE_*` variables. Catalog discovery is
performed by the Laravel API so credentials are never sent to the browser.
GridPBX preserves the catalog selector keys for `endpoint_brand`,
`endpoint_family`, and `endpoint_model`, and separately maps a model's catalog
`id` to the Switch `provision.id` template field when the connected schema
supports it.

Each model may also publish bounded, optional capability metadata:

- `max_keys`, `max_expansion_modules`, and `keys_per_expansion_module`;
- `supported_key_types` using GridPBX's fixed Switch line-key allowlist;
- `value_sources` as safe identifiers, never executable SQL; and
- `manufacturer_provider` for a future allowlisted ZTP adapter.

The line-key editor uses matched capacity and type metadata on both the Vue and
Laravel validation boundaries. Missing metadata falls back to the safe generic
editor limit; it does not grant unrestricted model capabilities.

When discovery is available, Device create and update requests must select a
brand, family, and model from the same catalog branch. A supplied template ID
must belong to that selected model. This is enforced in both Zod and Laravel
before the Switch mutation. Manual values remain available only when catalog
discovery is unconfigured or unavailable.

Model `value_sources` are identifiers, not queries. GridPBX recognizes only
the server-side `extensions`/`users` and `devices` providers, scopes their
choices to the active account, limits each result set, and returns display-safe
choices in the line-key preview. Each choice value is the resource's public
UUID, never its raw Switch identifier. Laravel resolves that UUID inside the
active account only when building the Switch write and maps known raw values
back to public UUIDs for reads. Unknown identifiers return no choices.

Device MAC addresses are canonicalized to uppercase colon notation. MySQL also
enforces one active canonical MAC per Switch account through an indexed virtual
identity, while allowing the same hardware identity in a different account or
after the prior projection is soft-deleted.

After changing the root `.env`, recreate the API and worker so Compose applies
the new environment, then run the server-side verification:

```bash
docker compose up -d --force-recreate grid-api grid-worker
docker compose exec -T grid-api php artisan switch:provisioner:verify
```

For automation, add `--json`. The command returns exit code `0` only when the
authenticated catalog contains at least one brand, family, and model. It never
prints configured credentials.

The real provisioner remains responsible for its databases, provider ACLs,
templates, generated endpoint documents, and network security. GridPBX reads
its safe phone catalog for form choices; device create/edit and explicit
sync/reprovision commands continue through the authenticated Switch API.
