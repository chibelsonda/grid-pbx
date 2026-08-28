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

account_id="$(php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $payload["data"][0]["id"] ?? "";' "${smoke_dir}/accounts.json")"

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/extensions" -o "${smoke_dir}/extensions.json"; then
  echo "GridPBX extension projection smoke test failed:" >&2
  cat "${smoke_dir}/extensions.json" >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/devices" -o "${smoke_dir}/devices.json"; then
  echo "GridPBX device projection smoke test failed:" >&2
  cat "${smoke_dir}/devices.json" >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/phone-numbers" -o "${smoke_dir}/phone-numbers.json"; then
  echo "GridPBX phone number projection smoke test failed:" >&2
  cat "${smoke_dir}/phone-numbers.json" >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/callflows" -o "${smoke_dir}/callflows.json"; then
  echo "GridPBX call routing projection smoke test failed:" >&2
  cat "${smoke_dir}/callflows.json" >&2
  exit 1
fi

callflow_id=""
if [[ -n "${account_id}" ]]; then
  callflow_id="$(php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $payload["data"][0]["id"] ?? "";' "${smoke_dir}/callflows.json")"
fi

if [[ -n "${callflow_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/callflows/${callflow_id}/editor" -o "${smoke_dir}/callflow-editor.json"; then
  echo "GridPBX guided call routing editor smoke test failed:" >&2
  cat "${smoke_dir}/callflow-editor.json" >&2
  exit 1
fi

if [[ -n "${callflow_id}" ]] && ! php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $editor=$payload["data"] ?? []; $encoded=json_encode($editor, JSON_THROW_ON_ERROR); exit(array_key_exists("phone_numbers", $editor) && !str_contains($encoded, "switch_resource_id") ? 0 : 1);' "${smoke_dir}/callflow-editor.json"; then
  echo "GridPBX guided call routing editor smoke test failed: safe phone-number options are missing." >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/callflows/editor" -o "${smoke_dir}/callflow-create-editor.json"; then
  echo "GridPBX guided call routing creation options smoke test failed:" >&2
  cat "${smoke_dir}/callflow-create-editor.json" >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); exit(($payload["data"]["mode"] ?? null) === "create" ? 0 : 1);' "${smoke_dir}/callflow-create-editor.json"; then
  echo "GridPBX guided call routing creation options smoke test failed: create mode is missing." >&2
  exit 1
fi

if [[ -n "${account_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/voicemail-boxes" -o "${smoke_dir}/voicemail-boxes.json"; then
  echo "GridPBX voicemail projection smoke test failed:" >&2
  cat "${smoke_dir}/voicemail-boxes.json" >&2
  exit 1
fi

voicemail_box_id=""
if [[ -n "${account_id}" ]]; then
  voicemail_box_id="$(php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $payload["data"][0]["id"] ?? "";' "${smoke_dir}/voicemail-boxes.json")"
fi

if [[ -n "${voicemail_box_id}" ]] && ! php -r '$payload=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $box=$payload["data"][0] ?? []; exit(array_key_exists("unavailable_greeting", $box) ? 0 : 1);' "${smoke_dir}/voicemail-boxes.json"; then
  echo "GridPBX voicemail greeting projection smoke test failed: unavailable_greeting is missing." >&2
  exit 1
fi

if [[ -n "${voicemail_box_id}" ]] && ! curl --fail-with-body -sS -b "${cookie_jar}" -H "Accept: application/json" -H "Origin: ${ui_url}" "${api_url}/api/v1/accounts/${account_id}/voicemail-boxes/${voicemail_box_id}/messages" -o "${smoke_dir}/voicemail-messages.json"; then
  echo "GridPBX voicemail message projection smoke test failed:" >&2
  cat "${smoke_dir}/voicemail-messages.json" >&2
  exit 1
fi

echo "GridPBX UI, API health, Sanctum login, session, account, extension, device, phone number, call routing/editor, voicemail box, message, and greeting metadata projection endpoints are healthy."
