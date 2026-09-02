#!/usr/bin/env bash
set -euo pipefail

deploy_dir="${GRIDPBX_DEPLOY_DIR:-/opt/gridpbx}"
environment_file="${deploy_dir}/.env"
credentials_file="${deploy_dir}/bootstrap.credentials"

if [[ -e "${environment_file}" ]]; then
    echo "${environment_file} already exists; no values were changed." >&2
    exit 1
fi

public_url="${GRIDPBX_PUBLIC_URL:-http://localhost:8080}"
admin_email="${GRIDPBX_ADMIN_EMAIL:-admin@gridpbx.local}"
admin_password="${GRIDPBX_ADMIN_PASSWORD:-$(openssl rand -base64 24 | tr -d '\n')}"
app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
database_password="$(openssl rand -hex 24)"
database_root_password="$(openssl rand -hex 24)"
stateful_domain="${public_url#*://}"
stateful_domain="${stateful_domain%%/*}"

if [[ "${public_url}" == https://* ]]; then
    secure_cookie=true
else
    secure_cookie=false
fi

environment_tmp="$(mktemp "${deploy_dir}/.env.XXXXXX")"
credentials_tmp="$(mktemp "${deploy_dir}/bootstrap.credentials.XXXXXX")"
umask 077

{
    printf 'GRID_API_IMAGE=%s\n' "${GRID_API_IMAGE:-ghcr.io/chibelsonda/grid-pbx-api}"
    printf 'GRID_UI_IMAGE=%s\n' "${GRID_UI_IMAGE:-ghcr.io/chibelsonda/grid-pbx-ui}"
    printf 'GRID_IMAGE_TAG=%s\n' "${GRID_IMAGE_TAG:-bootstrap}"
    printf 'GRID_HTTP_PORT=%s\n' "${GRID_HTTP_PORT:-8080}"
    printf 'GRID_MYSQL_TUNNEL_PORT=%s\n' "${GRID_MYSQL_TUNNEL_PORT:-3309}"
    printf '\n'
    printf 'APP_KEY=%s\n' "${app_key}"
    printf 'APP_URL=%s\n' "${public_url}"
    printf 'FRONTEND_URL=%s\n' "${public_url}"
    printf 'FRONTEND_URLS=%s\n' "${public_url}"
    printf 'SANCTUM_STATEFUL_DOMAINS=%s\n' "${stateful_domain}"
    printf 'SESSION_SECURE_COOKIE=%s\n' "${secure_cookie}"
    printf '\n'
    printf 'DB_DATABASE=%s\n' "${DB_DATABASE:-gridpbx}"
    printf 'DB_USERNAME=%s\n' "${DB_USERNAME:-gridpbx}"
    printf 'DB_PASSWORD=%s\n' "${database_password}"
    printf 'DB_ROOT_PASSWORD=%s\n' "${database_root_password}"
    printf '\n'
    printf 'GRID_ADMIN_EMAIL=%s\n' "${admin_email}"
    printf 'GRID_ADMIN_PASSWORD=%s\n' "${admin_password}"
    printf '\n'
    printf 'SWITCH_BASE_URL=%s\n' "${SWITCH_BASE_URL:-http://kazoo:8000/v2}"
    printf 'SWITCH_API_KEY=%s\n' "${SWITCH_API_KEY:-}"
    printf 'SWITCH_ACCOUNT_ID=%s\n' "${SWITCH_ACCOUNT_ID:-}"
    printf 'SWITCH_ACCOUNT_NAME=%s\n' "${SWITCH_ACCOUNT_NAME:-GridPBX}"
    printf 'SWITCH_ACCOUNT_REALM=%s\n' "${SWITCH_ACCOUNT_REALM:-gridpbx.local}"
    printf 'SWITCH_ACCOUNT_TIMEZONE=%s\n' "${SWITCH_ACCOUNT_TIMEZONE:-UTC}"
    printf 'SWITCH_LINE_KEY_MUTATIONS_ENABLED=false\n'
    printf '\n'
    printf 'PAYMENTS_ENABLED=false\n'
    printf 'PAYMENT_PROVIDER=authorize_net\n'
    printf 'PAYMENT_MUTATIONS_ENABLED=false\n'
    printf 'AUTHORIZENET_ENVIRONMENT=sandbox\n'
} >"${environment_tmp}"

{
    printf 'GRID_ADMIN_EMAIL=%s\n' "${admin_email}"
    printf 'GRID_ADMIN_PASSWORD=%s\n' "${admin_password}"
} >"${credentials_tmp}"

chmod 0600 "${environment_tmp}" "${credentials_tmp}"
mv "${environment_tmp}" "${environment_file}"
mv "${credentials_tmp}" "${credentials_file}"

echo "Created ${environment_file} and ${credentials_file} with mode 0600."
echo "Review the environment before the first deployment; secrets were not printed."
