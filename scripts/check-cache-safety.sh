#!/usr/bin/env bash
#
# Cache-safety checks for anonymous page views.
#
# An anonymous page view must not start a session, must not contain a CSRF
# token, and must be marked cacheable. Pages that require a database render are
# not checked by default; pass the paths you care about.
#
#   ./scripts/check-cache-safety.sh
#   ./scripts/check-cache-safety.sh https://www.tvshowcalendar.com / /discover /shows/1234-some-show

set -euo pipefail

BASE_URL="${1:-http://localhost:8000}"
shift || true

paths=("$@")
if [[ ${#paths[@]} -eq 0 ]]; then
  paths=("/" "/about")
fi

failures=0
body="$(mktemp)"
trap 'rm -f "$body"' EXIT

check_path() {
  local path="$1"
  local path_failures=0
  local headers

  headers="$(curl -sS -D - -o "$body" "${BASE_URL}${path}")"

  if grep -qi '^set-cookie:' <<<"$headers"; then
    echo "FAIL [$path] Set-Cookie on an anonymous request"
    path_failures=$((path_failures + 1))
  fi

  if grep -q 'id="csrfValue"' "$body"; then
    echo "FAIL [$path] CSRF token rendered for an anonymous visitor"
    path_failures=$((path_failures + 1))
  fi

  if ! grep -qi '^cache-control: *public' <<<"$headers"; then
    echo "FAIL [$path] missing public Cache-Control"
    path_failures=$((path_failures + 1))
  fi

  if [[ $path_failures -eq 0 ]]; then
    echo "ok   [$path]"
  fi

  failures=$((failures + path_failures))
}

for path in "${paths[@]}"; do
  check_path "$path"
done

if [[ $failures -gt 0 ]]; then
  echo
  echo "$failures cache-safety check(s) failed"
  exit 1
fi

echo
echo "All cache-safety checks passed."