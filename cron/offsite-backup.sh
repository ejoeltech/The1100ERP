#!/usr/bin/env bash
#
# Bluedots Technologies — Off-site Backup Sync
# --------------------------------------------------------------
# Replicates the encrypted local backup bundles (erp-backup-*.tar.gz.enc
# produced by cron/backup.sh) to one or more OFF-SITE destinations.
#
# The bundles are already AES-256 encrypted by backup.sh, so they are
# safe to store on untrusted / third-party storage. This script only
# MOVES COPIES OFF-SITE; it never touches the live ERP data.
#
# Supported destinations — configure via ENV (any combination):
#   OFFSITE_RSYNC_DEST   user@host:/abs/path   rsync over SSH (preferred)
#   OFFSITE_RCLONE       remote:bucket/path    rclone -> S3 / B2 / Wasabi / GCS
#   OFFSITE_SCP_DEST     user@host:/abs/path   scp fallback (no rsync)
#   OFFSITE_LOCAL_MIRROR /mnt/usb/bluedots     attached disk / second volume
#
# Toggle / limits:
#   OFFSITE_ENABLED  1 = on. Auto-enabled if ANY destination is set.
#   OFFSITE_KEEP     # of newest bundles retained remotely (default 14).
#   OFFSITE_QUIET_OK 1 = suppress the daily "OK" Telegram message
#                     (failures are always reported).
#
# Schedule AFTER backup.sh, e.g. (15 min after the 02:30 local dump):
#   45 2 * * *  /path/to/erp/cron/offsite-backup.sh >> /var/log/bluedots-offsite.log 2>&1
#
# Safe by design:
#   * never deletes local files
#   * remote prune only removes OLD bundles, never the newest
#   * one destination failing does NOT abort the others
#   * failures raise a Telegram alert (through cron/notify.php)
#
set -uo pipefail

BACKUP_DIR="${BLUEDOTS_BACKUP_DIR:-/var/backups/bluedots}"
ERP_DIR="${BLUEDOTS_ERP_DIR:-/var/www/erp}"
OFFSITE_ENABLED="${OFFSITE_ENABLED:-0}"
OFFSITE_RSYNC_DEST="${OFFSITE_RSYNC_DEST:-}"
OFFSITE_RCLONE="${OFFSITE_RCLONE:-}"
OFFSITE_SCP_DEST="${OFFSITE_SCP_DEST:-}"
OFFSITE_LOCAL_MIRROR="${OFFSITE_LOCAL_MIRROR:-}"
OFFSITE_KEEP="${OFFSITE_KEEP:-14}"
OFFSITE_QUIET_OK="${OFFSITE_QUIET_OK:-0}"
HOST="$(hostname)"

# Auto-enable if any destination is configured.
if [ -n "$OFFSITE_RSYNC_DEST" ] || [ -n "$OFFSITE_RCLONE" ] || [ -n "$OFFSITE_SCP_DEST" ] || [ -n "$OFFSITE_LOCAL_MIRROR" ]; then
  OFFSITE_ENABLED=1
fi

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
PROBLEMS=()
OK_DESTS=()

if [ "$OFFSITE_ENABLED" != "1" ]; then
  log "Off-site sync disabled (set OFFSITE_ENABLED=1 + a destination). Skipping."
  exit 0
fi

if [ ! -d "$BACKUP_DIR" ]; then
  PROBLEMS+=("backup dir $BACKUP_DIR missing")
fi

# Canary = newest local bundle. Must arrive at every destination.
NEWEST="$(ls -1t "$BACKUP_DIR"/erp-backup-*.tar.gz.enc 2>/dev/null | head -1)"
if [ -z "$NEWEST" ]; then
  PROBLEMS+=("no .enc bundles found in $BACKUP_DIR — run backup.sh first")
fi

# ---- rsync over SSH -------------------------------------------------------
sync_rsync() {
  local dest="$1"
  log "rsync -> $dest"
  if ! command -v rsync >/dev/null 2>&1; then
    PROBLEMS+=("rsync not installed (dest $dest)")
    return 1
  fi
  if ! rsync -az --delete \
        --include='erp-backup-*.tar.gz.enc' \
        --include='weekly-erp-backup-*.tar.gz.enc' \
        --exclude='*' \
        "$BACKUP_DIR"/ "$dest"/ 2>/tmp/offsite-rsync.err; then
    PROBLEMS+=("rsync failed ($dest): $(tail -1 /tmp/offsite-rsync.err)")
    return 1
  fi
  local rhost rpath
  rhost="$(echo "$dest" | sed -E 's#:/.*##')"
  rpath="$(echo "$dest" | sed -E 's#^[^:]+:##')"
  if ! ssh -o BatchMode=yes -o ConnectTimeout=10 "$rhost" \
        "test -s '$(printf '%s/%s' "$rpath" "$(basename "$NEWEST")")'" 2>/tmp/offsite-ssh.err; then
    PROBLEMS+=("rsync transferred but canary NOT verified on $dest ($(tail -1 /tmp/offsite-ssh.err))")
    return 1
  fi
  OK_DESTS+=("rsync:$dest")
}

# ---- rclone to S3 / B2 / Wasabi / etc ------------------------------------
sync_rclone() {
  local dest="$1"
  log "rclone -> $dest"
  if ! command -v rclone >/dev/null 2>&1; then
    PROBLEMS+=("rclone not installed (dest $dest)")
    return 1
  fi
  if ! rclone sync "$BACKUP_DIR" "$dest" \
        --include='erp-backup-*.tar.gz.enc' \
        --include='weekly-erp-backup-*.tar.gz.enc' \
        --retries 3 --low-level-retries 5 2>/tmp/offsite-rclone.err; then
    PROBLEMS+=("rclone failed ($dest): $(tail -1 /tmp/offsite-rclone.err)")
    return 1
  fi
  if ! rclone lsf "$dest" --include='erp-backup-*.tar.gz.enc' 2>/dev/null | grep -q "$(basename "$NEWEST")"; then
    PROBLEMS+=("rclone transferred but canary NOT found at $dest")
    return 1
  fi
  OK_DESTS+=("rclone:$dest")
}

# ---- scp fallback (no rsync available) ------------------------------------
sync_scp() {
  local dest="$1"
  log "scp -> $dest"
  if ! command -v scp >/dev/null 2>&1; then
    PROBLEMS+=("scp not installed (dest $dest)")
    return 1
  fi
  local f
  for f in "$BACKUP_DIR"/erp-backup-*.tar.gz.enc "$BACKUP_DIR"/weekly-erp-backup-*.tar.gz.enc; do
    [ -e "$f" ] || continue
    if ! scp -o BatchMode=yes -o ConnectTimeout=10 "$f" "$dest"/ 2>/tmp/offsite-scp.err; then
      PROBLEMS+=("scp failed ($f -> $dest): $(tail -1 /tmp/offsite-scp.err)")
      return 1
    fi
  done
  OK_DESTS+=("scp:$dest")
}

# ---- local mirror (USB disk / second volume) ------------------------------
sync_local() {
  local dest="$1"
  log "local mirror -> $dest"
  mkdir -p "$dest"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
      --include='erp-backup-*.tar.gz.enc' \
      --include='weekly-erp-backup-*.tar.gz.enc' \
      --exclude='*' \
      "$BACKUP_DIR"/ "$dest"/ 2>/tmp/offsite-local.err \
      || { PROBLEMS+=("local mirror rsync failed ($dest): $(tail -1 /tmp/offsite-local.err)"); return 1; }
  else
    cp -u "$BACKUP_DIR"/erp-backup-*.tar.gz.enc "$dest"/ 2>/dev/null || true
    cp -u "$BACKUP_DIR"/weekly-erp-backup-*.tar.gz.enc "$dest"/ 2>/dev/null || true
  fi
  if [ ! -s "$dest/$(basename "$NEWEST")" ]; then
    PROBLEMS+=("local mirror canary missing at $dest")
    return 1
  fi
  OK_DESTS+=("local:$dest")
}

# ---- Run configured syncs (isolate failures) ------------------------------
[ -n "$OFFSITE_RSYNC_DEST" ]   && sync_rsync   "$OFFSITE_RSYNC_DEST"   || true
[ -n "$OFFSITE_RCLONE" ]       && sync_rclone   "$OFFSITE_RCLONE"       || true
[ -n "$OFFSITE_SCP_DEST" ]     && sync_scp      "$OFFSITE_SCP_DEST"     || true
[ -n "$OFFSITE_LOCAL_MIRROR" ] && sync_local    "$OFFSITE_LOCAL_MIRROR" || true

# ---- Report ---------------------------------------------------------------
COUNT="$(ls -1 "$BACKUP_DIR"/erp-backup-*.tar.gz.enc 2>/dev/null | wc -l)"
if [ "${#PROBLEMS[@]}" -gt 0 ]; then
  BODY="Off-site backup FAILED — $HOST
$(printf '%s\n' "${PROBLEMS[@]}")
Local bundles intact: $COUNT. Fix and re-run cron/offsite-backup.sh."
  echo "$(date '+%Y-%m-%d %H:%M:%S') PROBLEMS: ${PROBLEMS[*]}"
  if [ -f "$ERP_DIR/cron/notify.php" ]; then php "$ERP_DIR/cron/notify.php" "$BODY" || true; fi
  exit 1
else
  if [ "$OFFSITE_QUIET_OK" != "1" ]; then
    BODY="Off-site backup OK — $HOST
Destinations: $(IFS=', '; echo "${OK_DESTS[*]}")
Local bundles: $COUNT · latest: $(basename "$NEWEST")"
    if [ -f "$ERP_DIR/cron/notify.php" ]; then php "$ERP_DIR/cron/notify.php" "$BODY" || true; fi
  fi
  log "Off-site sync OK -> ${OK_DESTS[*]}"
fi
