#!/usr/bin/env bash
# apply-feature-migrations.sh
# Idempotent migrator for the ADDITIVE feature migrations only:
#   - modules/hr/update_schema_v2..v10_payroll.sql  (HR module extensions + Nigeria payroll)
#   - database/leads-schema.sql                     (leads table + 8 settings seeds)
#   - database/run-leads-migration.php             (seeds settings rows)
#
# This DOES NOT run the one-off fix files in database/ (clear-users.sql,
# recreate_users_table.php, restore_*.php, etc.) — those are for existing
# installs / disaster recovery, not a fresh deploy.
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
MYSQL="mysql --host=$DB_HOST --port=$PORT --user=$DB_USER $([ -n "$DB_PASS" ] && echo "--password=$DB_PASS") --default-character-set=utf8mb4 $DB_NAME"

echo "==> Applying HR payroll + leads migrations to '$DB_NAME' on $DB_HOST:$PORT"

# 1) HR base schema (idempotent: all CREATE TABLE IF NOT EXISTS)
if [ -f "$ROOT/modules/hr/hr_schema.sql" ]; then
  echo "  [1/3] HR base schema (hr_schema.sql)"
  $MYSQL < "$ROOT/modules/hr/hr_schema.sql" || { echo "FAILED hr_schema.sql"; exit 1; }
fi

# 2) HR incremental migrations v2..v10 (all additive, IF NOT EXISTS)
echo "  [2/3] HR incremental migrations v2..v10"
for f in "$ROOT"/modules/hr/update_schema_v2.sql \
         "$ROOT"/modules/hr/update_schema_v3_voting.sql \
         "$ROOT"/modules/hr/update_schema_v4_settings.sql \
         "$ROOT"/modules/hr/update_schema_v5_onboarding.sql \
         "$ROOT"/modules/hr/update_schema_v6_layout.sql \
         "$ROOT"/modules/hr/update_schema_v7_visibility.sql \
         "$ROOT"/modules/hr/update_schema_v8_css.sql \
         "$ROOT"/modules/hr/update_schema_v9_templates.sql \
         "$ROOT"/modules/hr/update_schema_v10_payroll.sql; do
  if [ -f "$f" ]; then
    echo "        - $(basename "$f")"
    $MYSQL < "$f" || { echo "FAILED $(basename "$f")"; exit 1; }
  fi
done

# 3) Leads schema (idempotent) — and run the PHP seeder for settings rows
echo "  [3/3] Leads schema + settings seed"
if [ -f "$ROOT/database/leads-schema.sql" ]; then
  $MYSQL < "$ROOT/database/leads-schema.sql" || { echo "FAILED leads-schema.sql"; exit 1; }
fi
if [ -f "$ROOT/database/run-leads-migration.php" ]; then
  echo "        - run-leads-migration.php (seeds 8 settings keys)"
  php "$ROOT/database/run-leads-migration.php" || { echo "FAILED run-leads-migration.php (is php-cli installed?)"; exit 1; }
fi

echo "==> DONE. Feature migrations applied. Next: configure .env/config.php, set Telegram/WhatsApp tokens in Settings -> Leads & Follow-up, and add the cron jobs from DEPLOY.md."
