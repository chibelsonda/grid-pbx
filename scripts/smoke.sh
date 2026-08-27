#!/usr/bin/env bash
set -euo pipefail

api_url="${GRID_API_URL:-http://localhost:8081}"
ui_url="${GRID_UI_URL:-http://localhost:5173}"
admin_email="${GRID_ADMIN_EMAIL:-admin@gridpbx.local}"
admin_password="${GRID_ADMIN_PASSWORD:-admin-change-me}"
smoke_dir="$(mktemp -d)"
cookie_jar="${smoke_dir}/cookies.txt"

cleanup() {
  rm -rf -- "${smoke_dir}"
}
trap cleanup EXIT

curl -fsS -o /dev/null "${ui_url}/"
curl -fsS -o /dev/null "${api_url}/api/v1/health"
curl -fsS -c "${cookie_jar}" -H "Origin: ${ui_url}" "${api_url}/sanctum/csrf-cookie" -o /dev/null

encoded_token="$(awk '$6 == "XSRF-TOKEN" { print $7 }' "${cookie_jar}" | tail -n 1)"
csrf_token="$(php -r 'echo urldecode($argv[1]);' "${encoded_token}")"

if ! curl --fail-with-body -sS -b "${cookie_jar}" -c "${cookie_jar}" \
  -H "Accept: application/json" \
  -H "Origin: ${ui_url}" \
  -H "X-XSRF-TOKEN: ${csrf_token}" \
  --data-urlencode "email=${admin_email}" \
  --data-urlencode "password=${admin_password}" \
  --data-urlencode "remember=1" \
  "${api_url}/login" -o "${smoke_dir}/login.json"; then
  echo "GridPBX login smoke test failed:" >&2
  cat "${smoke_dir}/login.json" >&2
  exit 1
fi

if ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/session" -o "${smoke_dir}/session.json"; then
  echo "GridPBX session smoke test failed:" >&2
  cat "${smoke_dir}/session.json" >&2
  echo >&2
  echo "Received cookie names:" >&2
  awk 'NF >= 7 { print $6 }' "${cookie_jar}" >&2
  exit 1
fi

if ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts" -o "${smoke_dir}/accounts.json"; then
  echo "GridPBX accounts smoke test failed:" >&2
  cat "${smoke_dir}/accounts.json" >&2
  exit 1
fi

echo "GridPBX UI, API health, Sanctum login, session, and account endpoints are healthy."
