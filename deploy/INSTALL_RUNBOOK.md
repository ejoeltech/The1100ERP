# INSTALL RUNBOOK — Eleven100 ERP

Authoritative install/upgrade procedure. The in-repo 7-step wizard at
`maintenance/setup/` is only one part of it: the token, the network
allow-list and the verification below are mandatory, not optional.

## 0. Prereqs

- Apache or Nginx + PHP 8.2+ with `pdo_mysql`, `mbstring`, `json`, `openssl`
- MariaDB/MySQL 10.4+, empty database + a dedicated DB user
- Deploy the code **without** `config.php` (it is gitignored and generated
  by the wizard). Never deploy `.git/`, `docs/`, or `*.sql` backups.

## 1. Lock down the installer (BEFORE opening it)

```sh
cd /path/to/1100erp

# 1a. One-time install token (only readable by the deployer).
#     The wizard refuses every request without it.
openssl rand -hex 32 > maintenance/setup/install.token
chmod 600 maintenance/setup/install.token

# 1b. IP allow-list for /maintenance/setup/ while installing.
#     Apache (.htaccess inside maintenance/setup/):
cat > maintenance/setup/.htaccess <<'EOF'
Require ip 203.0.113.7
EOF
#     Nginx (server block):
#     location ^~ /maintenance/setup/ { allow 203.0.113.7; deny all; }
```

PowerShell equivalent for the token on Windows/XAMPP:

```powershell
-join ((1..32) | ForEach-Object { '{0:x2}' -f (Get-Random -Max 256) }) |
  Set-Content maintenance/setup/install.token -NoNewline
```

## 2. Run the wizard

1. Open `https://your-host/maintenance/setup/?token=PASTE_TOKEN_HERE`
   (or send header `X-Install-Token`). Wrong tokens are rate-limited.
2. Steps 1–6 as normal: requirements, DB, admin account, company, install.
   `create_admin` aborts if the users table is not empty — it never deletes.
3. Step 7: **Run Database Final Check** (runs SchemaPatcher behind the
   installer gate), then **Delete Installer Now** (needs the current admin
   password + CSRF; same bar as System Update → Delete Installer).
4. The token file is destroyed at finalize; the `storage/installed` marker
   and `settings.installed_at` row survive cleanup permanently.

## 3. Verify, then open up

1. Confirm `https://your-host/maintenance/setup/` is gone (404) — or run
   `deploy/verify-deployment.sh` (WP0-F) which checks this plus the other
   denied paths.
2. Log in, open **System Update**, confirm "Installer still present" is gone.
3. Remove the IP allow-list from step 1b.
4. Rotate the admin password (the one typed into the wizard).
5. Enable MFA for all admin accounts (available after WP4 lands; until then
   this step is: use a 20+ character generated password and a private browser).
6. Confirm `storage/installed` exists on the server (never delete it).

## 4. Manual SSH recovery (replaces the deleted factory-reset.php)

There is no web factory reset anymore. To start over via SSH:

```sh
cd /path/to/1100erp
# 1. Full backup first (see deploy/ backup script, WP12).
# 2. Drop the database objects (never the whole server):
mysql -u root -p -e "DROP DATABASE erpdb; CREATE DATABASE erpdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# 3. Remove install signals so the wizard unlocks:
rm -f config.php storage/installed
# 4. Re-upload maintenance/setup/ from the deployment package, create a fresh
#    install.token (step 1a), re-apply the IP allow-list (step 1b), run the
#    wizard from step 2 above.
```

## 5. Upgrades (no wizard)

1. Backup (DB + uploads) — see WP12 backup script.
2. Deploy new code over SSH (`git pull --ff-only` from the pinned remote).
3. Log in as admin → **System Update** → run the schema patch.
4. Never re-upload `maintenance/setup/` on an installed system.
