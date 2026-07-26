#!/usr/bin/env bash
#
# Bluedots Technologies — ERP Health Check
# --------------------------------------------------------------
# Lightweight, self-hosted. Checks:
#   1. Web app responds (HTTP 200) at the configured URL
#   2. Database is reachable
#   3. Disk usage on the data partition is below threshold
#   4. TLS certificate is not expiring soon
# Alerts via Telegram through cron/notify.php on any failure.
# Only one Telegram message is sent per run (summary of all problems).
#
# Schedule:
#   */15 * * * *  /path/to/erp/cron/healthcheck.sh >> /var/log/bluedots-health.log 2>&1
#
set -uo pipefail

ERP_DIR="${BLUEDOTS_ERP_DIR:-/var/www/erp}"
SITE_URL="${BLUEDOTS_SITE_URL:-https://my.bluedots.com.ng}"
DISK_PATH="${BLUEDOTS_DISK_PATH:-/}"
DISK_WARN_PCT=85
CERT_WARN_DAYS=14
HOST="$(hostname)"

# DB creds from .env (same keys config.php uses)
if [ -f "$ERP_DIR/.env" ]; then set -a; . "$ERP_DIR/.env"; set +a; fi
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-1100erp}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

PROBLEMS=()

# 1) Web app
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$SITE_URL" || echo 000)"
if [ "$HTTP_CODE" != "200" ] && [ "$HTTP_CODE" != "302" ] && [ "$HTTP_CODE" != "301" ]; then
  PROBLEMS+=("Site unreachable: HTTP $HTTP_CODE at $SITE_URL")
fi

# 2) Database
if ! mysql -h "${DB_HOST:-localhost}" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
  PROBLEMS+=("Database unreachable ($DB_USER@${DB_HOST:-localhost}/$DB_NAME)")
fi

# 3) Disk
DISK_USED="$(df -P "$DISK_PATH" | awk 'NR==2 {print $5}' | tr -d '%')"
if [ -n "$DISK_USED" ] && [ "$DISK_USED" -ge "$DISK_WARN_PCT" ]; then
  PROBLEMS+=("Disk $DISK_PATH at ${DISK_USED}% (warn >= ${DISK_WARN_PCT}%)")
fi

# 4) TLS cert expiry
if command -v openssl >/dev/null 2>&1; then
  DOMAIN="$(echo "$SITE_URL" | sed -E 's#https?://##; s#/.*##')"
  EXP_EPOCH="$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null \
    | openssl x509 -noout -enddate 2>/dev/null | sed 's/notAfter=//')"
  if [ -n "$EXP_EPOCH" ]; then
    EXP_DAYS="$(( ( $(date -d "$EXP_EPOCH" +%s) - $(date +%s) ) / 86400 ))"
    if [ "$EXP_DAYS" -le "$CERT_WARN_DAYS" ]; then
      PROBLEMS+=("TLS cert for $DOMAIN expires in $EXP_DAYS days")
    fi
  fi
fi

# Report
if [ "${#PROBLEMS[@]}" -gt 0 ]; then
  BODY="ERP Health Alert — $HOST
$(printf '%s\n' "${PROBLEMS[@]}")"
  echo "$(date '+%Y-%m-%d %H:%M:%S') PROBLEMS: ${PROBLEMS[*]}"
  if [ -f "$ERP_DIR/cron/notify.php" ]; then php "$ERP_DIR/cron/notify.php" "$BODY" || true; fi
  exit 1
else
  echo "$(date '+%Y-%m-%d %H:%M:%S') OK — site:$HTTP_CODE db:up disk:${DISK_USED}%"
fi
