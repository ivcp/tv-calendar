#!/usr/bin/env bash
#
# Index-hygiene checks: robots.txt, sitemap.xml and noindex coverage.
#
#   ./scripts/check-index-hygiene.sh
#   ./scripts/check-index-hygiene.sh https://www.tvshowcalendar.com
#
# A thin show page (no summary, no poster, fewer than 3 episodes) can only be
# discovered from the database, so pass one explicitly when you want it checked:
#
#   THIN_SHOW_PATH=/shows/73108-tomb-raider ./scripts/check-index-hygiene.sh
#
# The URL-count assertion replaces crawling 8,000+ URLs: it catches a silently
# truncated or widened sitemap query, not a bad individual URL.

set -euo pipefail

BASE_URL="${1:-http://localhost:8000}"
BASE_URL="${BASE_URL%/}"

failures=0
body="$(mktemp)"
headers="$(mktemp)"
trap 'rm -f "$body" "$headers"' EXIT

fail() { echo "FAIL [$1] $2"; failures=$((failures + 1)); }
pass() { echo "ok   [$1]"; }

fetch() {
  curl -sS -D "$headers" -o "$body" "${BASE_URL}${1}"
}

# Calendar month offset in YYYY-MM, portable across GNU and BSD date.
month_offset() {
  local months=$(( $(date +%Y) * 12 + 10#$(date +%m) - 1 + $1 ))
  printf '%04d-%02d\n' $(( months / 12 )) $(( months % 12 + 1 ))
}

expect_indexable() {
  local path="$1" label="$2"

  fetch "$path"

  if grep -q 'name="robots"' "$body"; then
    fail "$label" "unexpected robots meta on an indexable page"
  else
    pass "$label"
  fi
}

expect_noindex() {
  local path="$1" label="$2"

  fetch "$path"

  if grep -q 'name="robots" content="noindex' "$body"; then
    pass "$label"
  else
    fail "$label" "missing noindex"
  fi
}

# --- robots.txt -------------------------------------------------------------

fetch /robots.txt

if grep -qi '^sitemap: ' "$body"; then
  pass "robots.txt sitemap directive"
else
  fail "robots.txt" "missing Sitemap directive (still the Cloudflare stub?)"
fi

for rule in /profile /showlist /search /reset-password/; do
  if grep -qi "^disallow: ${rule}$" "$body"; then
    pass "robots.txt disallows ${rule}"
  else
    fail "robots.txt" "missing Disallow: ${rule}"
  fi
done

# --- sitemap.xml ------------------------------------------------------------

fetch /sitemap.xml

if grep -qi '^content-type: *text/xml' "$headers"; then
  pass "sitemap content type"
else
  fail "sitemap.xml" "content type is not text/xml"
fi

if grep -qi '^cache-control: *public' "$headers"; then
  pass "sitemap cache header"
else
  fail "sitemap.xml" "missing public Cache-Control"
fi

if grep -q 'changefreq\|priority' "$body"; then
  fail "sitemap.xml" "changefreq/priority present"
else
  pass "sitemap has no changefreq/priority"
fi

urls="$(grep -o '<loc>' "$body" | wc -l | tr -d ' ' || true)"
if (( urls > 5 && urls < 50000 )); then
  pass "sitemap URL count (${urls})"
else
  fail "sitemap.xml" "URL count ${urls} outside 6..49999"
fi

for path in / /discover /about /get-notified /watch-while-traveling; do
  if grep -q "<loc>${BASE_URL}${path}</loc>" "$body"; then
    pass "sitemap lists ${path}"
  else
    fail "sitemap.xml" "missing static page ${path}"
  fi
done

if [[ "$BASE_URL" == *localhost* || "$BASE_URL" == *127.0.0.1* ]]; then
  echo "skip [sitemap host] dev URL, not asserting the public host"
elif grep -q 'localhost\|127\.0\.0\.1' "$body"; then
  fail "sitemap.xml" "contains a local URL"
else
  pass "sitemap host"
fi

# --- calendar months --------------------------------------------------------

expect_indexable "/$(month_offset 0)" "current month"
expect_indexable "/$(month_offset -2)" "month -2"
expect_noindex "/$(month_offset -3)" "month -3"
expect_indexable "/$(month_offset 2)" "month +2"
expect_noindex "/$(month_offset 3)" "month +3"
expect_noindex "/1920-01" "route range edge"

# --- listings ---------------------------------------------------------------

expect_indexable "/discover" "discover page 1"
expect_noindex "/discover?page=2" "discover page 2"

fetch "/discover?page=2"

if grep -q 'rel="canonical"' "$body"; then
  fail "discover page 2" "canonical present on a noindex page"
else
  pass "discover page 2 has no canonical"
fi

# --- show pages -------------------------------------------------------------

if [[ -n "${THIN_SHOW_PATH:-}" ]]; then
  expect_noindex "$THIN_SHOW_PATH" "thin show page"
else
  echo "skip [thin show] pass THIN_SHOW_PATH=/shows/<id>-<slug> to check it"
fi

# --- summary ----------------------------------------------------------------

if (( failures > 0 )); then
  echo
  echo "${failures} index-hygiene check(s) failed"
  exit 1
fi

echo
echo "All index-hygiene checks passed."
