#!/usr/bin/env bash
# verify-deployment.sh — post-deploy exposure check (WP0-F).
#
# Requests paths that must be gone or denied on the DEPLOYED site and
# expects 403/404. Run ONLY after the deletions are deployed (never against
# a box that still needs its installer, and never run removed scripts).
#
# Usage: ./deploy/verify-deployment.sh https://your-host
set -uo pipefail

BASE="${1:-}"
if [ -z "$BASE" ]; then
  echo "Usage: $0 https://your-host" >&2
  exit 2
fi
BASE="${BASE%/}"

FAIL=0
check_gone() { # $1 = path, must be 404 (or 403 where the server denies)
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$BASE$1")
  if [ "$code" = "404" ] || [ "$code" = "403" ]; then
    echo "OK   $1 -> $code"
  else
    echo "FAIL $1 -> $code (expected 403/404)"
    FAIL=$((FAIL + 1))
  fi
}
check_ok() { # $1 = path, must be 200
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$BASE$1")
  if [ "$code" = "200" ]; then
    echo "OK   $1 -> $code"
  else
    echo "FAIL $1 -> $code (expected 200)"
    FAIL=$((FAIL + 1))
  fi
}

echo "== installer must be gone =="
for p in /maintenance/setup/ /maintenance/setup/index.php \
         /maintenance/setup/install.php /maintenance/setup/cleanup.php \
         /maintenance/setup/run-schema-update.php \
         /maintenance/setup/tools/clear-users.php \
         /maintenance/setup/factory-reset.php \
         /maintenance/setup/tools/recreate_users_table.php \
         /maintenance/setup/tools/restore_full_schema.php \
         /tests/security_test.php; do
  check_gone "$p"
done

echo "== sensitive files must be denied =="
for p in /database/install-schema.sql /updates/v1.1_payment_module.sql \
         /.env /composer.json /composer.lock /storage/installed; do
  check_gone "$p"
done

echo "== app must answer =="
check_ok "/login.php"

if [ "$FAIL" -ne 0 ]; then
  echo "RESULT: $FAIL check(s) failed" >&2
  exit 1
fi
echo "RESULT: all checks passed"
