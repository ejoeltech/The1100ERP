#!/usr/bin/env bash
#
# Bluedots Technologies — ERP Backup Script
# --------------------------------------------------------------
# Self-hosted, zero recurring cost. Dumps the MySQL DB, archives the
# web root + uploads, encrypts the bundle with openssl, rotates old
# backups, and reports status via Telegram (through cron/notify.php).
#
# Reads DB credentials from .env (getenv()) — no hardcoding. The repo's
# config.php uses getenv('DB_HOST') etc, so we source .env the same way.
# Configure the variables below, then schedule:
#   30 2 * * *  /path/to/erp/cron/backup.sh >> /var/log/bluedots-backup.log 2>&1
#
set -euo pipefail
umask 077

# ---- CONFIG (edit these) ------------------------------------------------
ERP_DIR="/var/www/erp"                 # path to the ERP web root
BACKUP_DIR="/var/backups/bluedots"     # where bundles are written
RETENTION_DAYS=14                     # daily bundles kept
RETENTION_WEEKLY=8                     # weekly bundles kept (Sundays)
ENCRYPT_PASS="${BLUEDOTS_BACKUP_PASS:-}"  # required; no default passphrase
# -------------------------------------------------------------------------

if [ -z "$ENCRYPT_PASS" ]; then
  echo "FATAL: BLUEDOTS_BACKUP_PASS is not set. Refusing to create a backup with a default/known key." >&2
  exit 1
fi

TS="$(date +%Y%m%d-%H%M%S)"
DAY_OF_WEEK="$(date +%u)"   # 7 = Sunday
HOST="$(hostname)"
OK=1
MSG=""

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

mkdir -p "$BACKUP_DIR"

# --- Load DB creds from .env (same keys config.php uses) -------------------
if [ -f "$ERP_DIR/.env" ]; then
  set -a; . "$ERP_DIR/.env"; set +a
fi
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-1100erp}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
  MSG="Backup FAILED on $HOST: DB_NAME/DB_USER not found in .env"
  OK=0
fi

BUNDLE="$BACKUP_DIR/erp-backup-$TS"
SQL_DUMP="$BUNDLE.sql"
ARCHIVE="$BUNDLE.tar.gz"
ENC="$ARCHIVE.enc"

cleanup() { rm -f "$SQL_DUMP" "$ARCHIVE" "$BUNDLE" 2>/dev/null || true; }
trap cleanup EXIT

if [ "$OK" -eq 1 ]; then
  log "Dumping database $DB_NAME ..."
  if ! MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --routines --events \
        -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$SQL_DUMP" 2>/tmp/bluedots-mysqldump.err; then
    MSG="Backup FAILED on $HOST: mysqldump error — $(tail -1 /tmp/bluedots-mysqldump.err)"
    OK=0
  fi
fi

if [ "$OK" -eq 1 ]; then
  log "Archiving files ..."
  # Never archive secrets, logs, temp files, git history, or dependencies.
  if ! tar -czf "$ARCHIVE" \
        --exclude='./.env' --exclude='./.env.*' --exclude='./config.php' \
        --exclude='./logs/*' --exclude='./tmp/*' --exclude='./.git/*' \
        --exclude='./vendor/*' \
        -C "$ERP_DIR" . ; then
    MSG="Backup FAILED on $HOST: tar archive error"
    OK=0
  fi
fi

if [ "$OK" -eq 1 ]; then
  log "Encrypting ..."
  # Pass the key through the environment, never as a command-line argument
  # (arguments are visible in the process list).
  export BLUEDOTS_BACKUP_PASS="$ENCRYPT_PASS"
  if ! openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
        -in "$ARCHIVE" -out "$ENC" -pass env:BLUEDOTS_BACKUP_PASS; then
    MSG="Backup FAILED on $HOST: encryption error"
    OK=0
  else
    chmod 600 "$ENC"
  fi
fi

if [ "$OK" -eq 1 ]; then
  SIZE="$(du -h "$ENC" | cut -f1)"
  log "Rotating old backups (keep $RETENTION_DAYS daily) ..."
  ls -1t "$BACKUP_DIR"/erp-backup-*.tar.gz.enc 2>/dev/null \
    | tail -n +"$((RETENTION_DAYS + 1))" | xargs -r rm -f

  if [ "$DAY_OF_WEEK" = "7" ]; then
    cp "$ENC" "$BACKUP_DIR/weekly-erp-backup-$TS.tar.gz.enc"
    ls -1t "$BACKUP_DIR"/weekly-erp-backup-*.tar.gz.enc 2>/dev/null \
      | tail -n +"$((RETENTION_WEEKLY + 1))" | xargs -r rm -f
    log "Weekly copy stored."
  fi

  MSG="ERP backup OK on $HOST — $SIZE (daily$( [ "$DAY_OF_WEEK" = 7 ] && echo '+weekly'))"
  log "$MSG"
fi

# --- Off-site sync (best-effort) -----------------------------------------
if [ "$OK" -eq 1 ] && [ -f "$ERP_DIR/cron/offsite-backup.sh" ]; then
  log "Running off-site sync ..."
  if ! bash "$ERP_DIR/cron/offsite-backup.sh" >>/var/log/bluedots-offsite.log 2>&1; then
    log "Off-site sync reported a problem — local backup still OK."
  fi
fi

# --- Report via Telegram (through existing notify wrapper) ---------------
if [ -f "$ERP_DIR/cron/notify.php" ]; then
  php "$ERP_DIR/cron/notify.php" "$MSG" || true
else
  echo "notify.php missing — $MSG" >&2
fi

[ "$OK" -eq 1 ]
