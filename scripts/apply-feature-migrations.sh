#!/usr/bin/env bash
# apply-feature-migrations.sh
# Idempotent migrator for the ADDITIVE feature migrations only:
#   - modules/hr/update_schema_v2/v3/v5/v10*.sql (HR extensions + payroll)
#   (Leads schema + settings seed moved to the schema patcher / install-schema;
#   reference SQL kept at docs/reference/leads-schema.sql.)
#
# This DOES NOT run one-off fix files (those were removed in WP0-A).
#
# The BASE schema is installed by the web Setup Wizard
# (maintenance/setup/install.php -> database/install-schema.sql), which this
# script assumes already ran. HR base (hr_schema.sql) is installed by
# modules/hr/install.php (run that once first, or it's safe to run again).
#
# Usage:
#   DB_HOST=localhost DB_NAME=1100erp DB_USER=root DB_PASS= ./scripts/apply-feature-migrations.sh
#   or:  ./scripts/apply-feature-migrations.sh --host ... --db ... --user ... --pass ...
set -euo pipefail

# ---- read DB creds (env or flags) ----
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-1100erp}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
PORT="${DB_PORT:-3306}"

while [ $# -gt 0 ]; do
  case "$1" in
    --host) DB_HOST="$2"; shift 2;;
    --db)   DB_NAME="$2"; shift 2;;
    --user) DB_USER="$2"; shift 2;;
    --pass) DB_PASS="$2"; shift 2;;
    --port) PORT="$2"; shift 2;;
    *) echo "Unknown arg: $1" >&2; exit 2;;
  esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# Keep the password out of the process list (MYSQL_PWD, not --password=).
export MYSQL_PWD="$DB_PASS"
MYSQL="mysql --host=$DB_HOST --port=$PORT --user=$DB_USER --default-character-set=utf8mb4 $DB_NAME"
trap 'unset MYSQL_PWD' EXIT

# Run a migration file in --force mode (mysql otherwise stops at the first
# "Duplicate column name", skipping the rest of the file). Expected idempotency
# errors are ignored; anything else is surfaced and fails the run.
run_sql_file() {
  local file="$1" out=""
  out=$($MYSQL --force < "$file" 2>&1) || true
  if [ -n "$out" ]; then
    local unexpected
    unexpected=$(printf '%s\n' "$out" | grep -Ev \
      'Duplicate column name|Duplicate key name|Duplicate entry|already exists|check that column/key exists|Can.t DROP.*check that' || true)
    if [ -n "$unexpected" ]; then
      printf '%s\n' "$unexpected" >&2
      return 1
    fi
  fi
  return 0
}

echo "==> Applying HR payroll + leads migrations to '$DB_NAME' on $DB_HOST:$PORT"

# 1) HR base schema (idempotent: all CREATE TABLE IF NOT EXISTS)
if [ -f "$ROOT/modules/hr/hr_schema.sql" ]; then
  echo "  [1/3] HR base schema (hr_schema.sql)"
  run_sql_file "$ROOT/modules/hr/hr_schema.sql" || { echo "FAILED hr_schema.sql"; exit 1; }
fi

# 2) HR incremental migrations (all additive). Discover every vN file so new
#    migrations (v11 signature, v12 onboarding types, ...) are never skipped.
echo "  [2/3] HR incremental migrations"
mapfile -t HR_MIGRATIONS < <(find "$ROOT/modules/hr" -maxdepth 1 -name 'update_schema_v*.sql' -print | sort -V)
for f in "${HR_MIGRATIONS[@]}"; do
  echo "        - $(basename "$f")"
  run_sql_file "$f" || { echo "FAILED $(basename "$f")"; exit 1; }
done

# 3) Leads schema + settings seed is owned by the schema patcher now
# (System Update in the app; database/install-schema.sql on fresh installs).
# Kept here as a no-op note so existing deploy checklists keep working.
echo "  [3/3] Leads schema + settings seed (via System Update patcher - nothing to do here)"

echo "==> DONE. Feature migrations applied. Next: configure .env/config.php, set Telegram/WhatsApp tokens in Settings -> Leads & Follow-up, and add the cron jobs from DEPLOY.md."
