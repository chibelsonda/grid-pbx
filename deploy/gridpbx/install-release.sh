#!/usr/bin/env bash
set -euo pipefail

release_dir="${1:?release directory is required}"
deploy_dir="${GRIDPBX_DEPLOY_DIR:-/opt/gridpbx}"

sudo -n install -d -m 0755 "${deploy_dir}"
sudo -n install -m 0644 "${release_dir}/compose.yaml" "${deploy_dir}/compose.yaml"
sudo -n install -m 0644 "${release_dir}/Caddyfile" "${deploy_dir}/Caddyfile"
sudo -n install -m 0755 "${release_dir}/deploy.sh" "${deploy_dir}/deploy.sh"
sudo -n install -m 0755 "${release_dir}/bootstrap-env.sh" "${deploy_dir}/bootstrap-env.sh"

if [[ ! -f "${deploy_dir}/.env" ]]; then
    sudo -n install -m 0600 "${release_dir}/.env.example" "${deploy_dir}/.env.example"
    echo "Created ${deploy_dir}/.env.example. Create ${deploy_dir}/.env before the first deployment." >&2
    exit 2
fi

exec "${deploy_dir}/deploy.sh"
