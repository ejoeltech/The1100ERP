# Bluedots ERP (1100erp-ai) — Disaster Recovery & Off-site Backup

Self-hosted, zero recurring cost. No data leaves the building unencrypted.

## What is backed up

`cron/backup.sh` runs nightly and produces **encrypted** bundles:

- MySQL dump (`--single-transaction --routines --events`)
- Full web root + uploads (the entire ERP directory)

Each bundle is `erp-backup-YYYYMMDD-HHMMSS.tar.gz.enc`
(AES-256-CBC, PBKDF2 100k iterations). Encryption happens
**before** anything leaves the server, so off-site copies are safe
even on third-party storage.

The DB credentials come from the repo's `.env` file (the same
`getenv()` keys `config.php` reads: `DB_HOST`, `DB_NAME`, `DB_USER`,
`DB_PASS`). No credentials are hardcoded.

## Credentials & secrets (READ THIS)

- **Bundle passphrase**: `BLUEDOTS_BACKUP_PASS` env var (falls back to
  a placeholder you must override). Store it in a password manager and
  ALSO keep a printed copy off-site. **If you lose this passphrase,
  encrypted backups are unrecoverable.** This is the single most
  important secret in the system.
- **Off-site destinations** are configured via env vars in the crontab
  (see below) — no secrets in the repo.
- **Telegram alerts** use `telegram_bot_token` + `telegram_chat_id`
  (Settings → Leads & Follow-up tab).

## Schedule (crontab on the ERP host)

```cron
# 02:30 nightly encrypted local backup
30 2 * * *  BLUEDOTS_BACKUP_PASS='***' /var/www/erp/cron/backup.sh >> /var/log/bluedots-backup.log 2>&1
# 02:45 push to off-site (also auto-runs inside backup.sh; this is a standalone fallback)
45 2 * * *  /var/www/erp/cron/offsite-backup.sh >> /var/log/bluedots-offsite.log 2>&1
# every 15 min health check
*/15 * * * *  /var/www/erp/cron/healthcheck.sh >> /var/log/bluedots-health.log 2>&1
# daily lead follow-up nudges + morning digest
30 9 * * *  php /var/www/erp/cron/followup.php >> /var/log/bluedots-followup.log 2>&1
0  8 * * *  php /var/www/erp/cron/daily-digest.php >> /var/log/bluedots-digest.log 2>&1
```

## Configure an off-site destination (pick ONE or more)

Set these as env vars for the offsite job (edit the crontab line). Any
non-empty destination auto-enables the sync.

```bash
# Preferred: rsync over SSH to a second server you control
OFFSITE_RSYNC_DEST="backup@nas.bluedots.local:/srv/bluedots-backups"

# Object storage via rclone (Wasabi / Backblaze B2 / AWS S3 / GCS) — ~$0.004/GB-mo
OFFSITE_RCLONE="wasabi:bluedots-erp/"

# Fallback if rsync unavailable
OFFSITE_SCP_DEST="backup@otherhost:/backups/erp"

# Or a directly-attached USB / second disk
OFFSITE_LOCAL_MIRROR="/mnt/usb/bluedots"
```

Options:
- `OFFSITE_KEEP=14` — newest N bundles retained remotely.
- `OFFSITE_QUIET_OK=1` — suppress the daily "OK" Telegram ping
  (failures always alert).

### rclone one-time setup (example: Wasabi, $5.99/TB-mo)

```bash
apt install rclone
rclone config            # choose S3-compatible, us-east-1, enter Wasabi key/secret
rclone mkdir wasabi:bluedots-erp
OFFSITE_RCLONE="wasabi:bluedots-erp/"   # add to crontab env
```

SSH destinations need passwordless key auth:
```bash
ssh-keygen -t ed25519 -N '' -f ~/.ssh/id_ed25519
ssh-copy-id backup@nas.bluedots.local
```

## Restoring

### From a local or off-site bundle

```bash
# 1. Decrypt
openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 \
  -in erp-backup-YYYYMMDD-HHMMSS.tar.gz.enc \
  -out erp-backup.tar.gz -pass pass:"$BLUEDOTS_BACKUP_PASS"

# 2. Extract web root
tar -xzf erp-backup.tar.gz -C /var/www/erp --strip-components=0

# 3. Restore the database (load .env first so DB_* match)
set -a; . /var/www/erp/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4;"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < erp-backup-YYYYMMDD-HHMMSS.sql
```

### Off-site retrieval

```bash
rsync -az backup@nas.bluedots.local:/srv/bluedots-backups/ /tmp/restore/   # rsync
rclone copy wasabi:bluedots-erp/ /tmp/restore/                                        # rclone
```

## Leads & Follow-up module

- Schema + settings seed are applied automatically: fresh installs via
  `database/install-schema.sql`, upgrades via System Update (schema patcher).
  (The old `database/run-leads-migration.php` runner was removed in WP0-E;
  reference SQL kept at `docs/reference/leads-schema.sql`.)
- Public capture page: `lead-form.php` (link it from your site / share it).
- Staff UI: Settings nav → **Leads & Follow-up** tab (Telegram + WhatsApp
  tokens, follow-up interval/attempts, daily digest toggle).
- WhatsApp: in Meta App → WhatsApp → Configuration, set the callback URL to
  `https://your-erp/api/leads/whatsapp-webhook.php` and the verify token to
  match `whatsapp_verify_token`. Capture-only (no auto-reply) to respect
  Meta's 24h window; the follow-up engine handles outreach.

## Verification

- Manually run `bash /var/www/erp/cron/offsite-backup.sh` after setup —
  you should get a Telegram "Off-site backup OK" and see the canary
  bundle present at the destination.
- Quarterly: actually decrypt + restore a bundle into a throwaway VM to
  prove the passphrase and process still work. **A backup you have not
  tested is a hope, not a backup.**

## Failure behaviour

- Off-site failure **does not** break the local backup. You get a Telegram
  "Off-site backup FAILED" with the reason; local bundles stay intact.
- One bad destination does not abort the others.
- `healthcheck.sh` separately watches site/db/disk/TLS and alerts.
