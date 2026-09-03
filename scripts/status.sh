#!/usr/bin/env bash
set -euo pipefail

grid_ui_url="http://127.0.0.1:${GRID_UI_PORT:-5173}/"
grid_api_url="http://127.0.0.1:${GRID_API_PORT:-8081}/api/v1/health"
mailpit_url="http://127.0.0.1:${GRID_MAILPIT_UI_PORT:-8026}/readyz"

docker compose ps

grid_ui_status="$(curl -sS -o /dev/null -w '%{http_code}' "$grid_ui_url")"
grid_api_status="$(curl -sS -o /dev/null -w '%{http_code}' "$grid_api_url")"
mailpit_status="$(curl -sS -o /dev/null -w '%{http_code}' "$mailpit_url")"

if [ "$grid_ui_status" != "200" ]; then
	echo "Grid UI check failed: $grid_ui_url returned HTTP $grid_ui_status" >&2
	exit 1
fi

if [ "$grid_api_status" != "200" ]; then
	echo "Grid API check failed: $grid_api_url returned HTTP $grid_api_status" >&2
	exit 1
fi

if [ "$mailpit_status" != "200" ]; then
	echo "Mailpit check failed: $mailpit_url returned HTTP $mailpit_status" >&2
	exit 1
fi

echo "Grid UI:   HTTP $grid_ui_status ($grid_ui_url)"
echo "Grid API:  HTTP $grid_api_status ($grid_api_url)"
echo "Mailpit:   HTTP $mailpit_status ($mailpit_url)"
