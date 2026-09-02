#!/usr/bin/env bash
set -euo pipefail

deploy_dir="${GRIDPBX_DEPLOY_DIR:-/opt/gridpbx}"
health_url="http://127.0.0.1:${GRID_HTTP_PORT:-8080}/api/v1/health"
lock_file="${GRIDPBX_DEPLOY_LOCK:-/tmp/gridpbx-production.deploy.lock}"

if [[ ! -f "${deploy_dir}/.env" ]]; then
    echo "Missing ${deploy_dir}/.env; deployment was not started." >&2
    exit 1
fi

cd "${deploy_dir}"

exec 9>"${lock_file}"
if ! flock -n 9; then
    echo "Another GridPBX deployment is already running." >&2
    exit 1
fi

required_variables=(GRID_API_IMAGE GRID_UI_IMAGE GRID_IMAGE_TAG)
for variable_name in "${required_variables[@]}"; do
    if [[ -z "${!variable_name:-}" ]]; then
        echo "${variable_name} is required." >&2
        exit 1
    fi
done

export GRID_API_IMAGE GRID_UI_IMAGE GRID_IMAGE_TAG

sudo -n docker network inspect gridpbx-switch-backplane >/dev/null 2>&1 \
    || sudo -n docker network create gridpbx-switch-backplane >/dev/null

sudo -n docker compose pull
sudo -n docker compose up -d mysql redis
sudo -n docker compose run --rm grid-api php artisan migrate --force
sudo -n docker compose run --rm grid-api php artisan db:seed --force
sudo -n docker compose up -d --remove-orphans

for attempt in $(seq 1 36); do
    if curl -fsS "${health_url}" >/dev/null; then
        sudo -n docker compose ps
        echo "GridPBX deployment ${GRID_IMAGE_TAG} is healthy."
        exit 0
    fi

    sleep 5
done

sudo -n docker compose ps >&2
sudo -n docker compose logs --tail=100 grid-api grid-ui edge >&2
echo "GridPBX deployment failed its health check." >&2
exit 1
