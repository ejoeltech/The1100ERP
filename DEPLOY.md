# Deploying 1100erp-ai (fresh instance)

This guide covers a **new deployment** of `1100erp-ai` with all current
features: core ERP + AI (Groq), **HR module with Nigeria-compliant payroll**,
and the **Leads & Follow-up automation** (web/WhatsApp/phone capture, Telegram
alerts, daily digest, off-site backup).

> State of `main`: code-complete and conflict-free. PHP/SQL were **not** executed
> in the build environment (no runtime there) — do the smoke test in step 6
> before going live.

## 1. Prerequisites
- PHP 8.1+ with `pdo_mysql`, `curl`, `mbstring`, `gd`, `zip`.
- MySQL/MariaDB 5.7+ (utf8mb4).
- Composer (for `mpdf/mpdf`).
- A web server (Apache/Nginx) with `mod_rewrite` or equivalent.
- A Groq API key (AI features).
- (Optional) Telegram bot token + chat id, and a WhatsApp Business number
  (Meta Cloud API) for lead automation.

## 2. Install code
```bash
git clone https://github.com/ejoeltech/1100erp-ai.git
cd 1100erp-ai
composer install          # installs mPDF (the only composer dependency)
cp .env.example .env       # then fill in real DB credentials (never commit)
# config.php is the secret-free loader (config.sample.php); the wizard
# generates it automatically, or copy it manually. Never commit config.php.
```

## 3. Configure
Put secrets in the environment, never in code (WP1). Either export real
environment variables, set `APP_SECRETS_FILE` to a file outside the web
root, or fill in `.env` (gitignored — copy from `.env.example`):
```env
DB_HOST=localhost
DB_NAME=1100erp
DB_USER=your_db_user
DB_PASS=your_db_pass
```
`config.php` is only the secret-free loader (copied from
`config.sample.php` by you or the wizard). Missing or placeholder values
fail fast with a generic 503.
```php
define('DB_HOST', 'localhost');
define('DB_NAME', '1100erp');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');
// GROQ_API_KEY is read from env or the settings table (never committed).
```
Generate an encryption key for backups:
```bash
php -r "echo base64_encode(random_bytes(32));"   # put in .env as ENCRYPTION_KEY
```

## 4. Create the database & base schema (web wizard — preferred)
1. Point your web root at the repo (e.g. `DocumentRoot /var/www/1100erp-ai`).
2. **First, unlock the installer** (mandatory, WP0-C): create an empty file
   `maintenance/setup/ALLOW_INSTALL` via file manager, FTP or terminal —
   see `deploy/INSTALL_RUNBOOK.md` steps 1–2b. The wizard refuses every
   request without it.
3. Open `https://your-host/maintenance/setup/` in a browser.
4. The wizard tests the DB connection, imports `database/install-schema.sql`
   (full base schema), creates the admin user, and initializes settings.
5. Finish with wizard Step 7 (final check, then delete installer) and
   `deploy/INSTALL_RUNBOOK.md` steps 3–6 (verify, remove allow-list,
   rotate passwords).

Alternative (CLI): `mysql -u <user> -p <db> < database/install-schema.sql`
(only on a fresh/empty DB — it DROPS and recreates tables).

## 5. Apply feature migrations (HR payroll + leads)
Run the idempotent migrator:
```bash
DB_HOST=localhost DB_NAME=1100erp DB_USER=root DB_PASS= \
  ./scripts/apply-feature-migrations.sh
```
This installs: HR base schema, HR updates (incl. Nigeria payroll columns,
`hr_payroll_items`, `hr_loans`). Leads table + settings seeds are applied by
the schema patcher (System Update) and `database/install-schema.sql` — the
old `database/run-leads-migration.php` runner was removed.
Safe to re-run (all `IF NOT EXISTS` / guarded skips).

## 6. Smoke test (do this before go-live)
- Run `deploy/verify-deployment.sh https://your-host` — installer paths must
  be gone (404), sensitive files denied (403), login up (200).
- Log in as admin. Confirm **HR → Employees** lists/adds staff and
  **HR → Payroll** opens.
- Generate payroll for one test employee (set `basic_salary` etc. first):
  HR → Payroll → pick month → **Generate Payroll**. Open a **Payslip** PDF.
  Verify: `net = gross + overtime + bonus − (PAYE + NHF + pension + loan + other)`.
- Leads: open the public lead form (`/lead-form.php`) or send a WhatsApp msg
  to your business number; confirm a row appears in **Leads**, and the owner
  gets a Telegram alert. Set tokens in **Settings → Leads & Follow-up**.
- AI: open the System Designer, send a prompt, confirm a Groq-backed response
  and that **Export PDF** downloads (mPDF).

## 7. Background jobs (cron)
From `RECOVERY.md` (adapt paths/user):
```cron
# Leads follow-up nudges (every 15 min)
*/15 * * * *  php /var/www/1100erp-ai/cron/followup.php >> /var/log/1100/followup.log 2>&1
# Ops daily digest (08:00)
0 8 * * *     php /var/www/1100erp-ai/cron/daily-digest.php >> /var/log/1100/digest.log 2>&1
# Encrypted off-site backup (02:00)
0 2 * * *     /var/www/1100erp-ai/cron/backup.sh >> /var/log/1100/backup.log 2>&1
```

## 8. Notes / caveats
- **PAYE brackets & relief**: coded to the published progressive rates with
  consolidated relief (20% of gross or ₦200k). Confirm against current FIRS
  rates for the deployment year — only `HR_Payroll::$PAYE_BRACKETS` and the
  `payroll_*` settings rows need editing.
- **WhatsApp**: webhook is capture-only (no auto-reply) to respect Meta's 24h
  window; the follow-up engine handles outreach. Configure the webhook URL in
  the Meta app and set Verify Token + App Secret in Settings.
- **Secrets**: never commit `.env`/tokens. `config.php` and `.env` are
  git-ignored in practice — verify before committing.
