# 1100ERP Setup Guide

Install the app on any cPanel shared host. No command line needed.
Time: about 15 minutes.

## What you need first

- A cPanel hosting account with PHP 8.1+ and MySQL/MariaDB.
- The install package (`1100erp-prod` folder contents in a `.zip`).
- An empty MySQL database (create one in cPanel → MySQL Databases;
  note the database name, username, and password).

## 1. Upload the files

1. In cPanel → File Manager, open `public_html` (or your subdomain folder).
2. Upload the `.zip` and Extract it there, so `dashboard.php` and `login.php`
   sit directly in that folder.
3. Delete the `.zip` from the server afterwards.

## 2. Unlock the installer

1. In File Manager, open the `maintenance/setup/` folder.
2. Create an empty file named exactly `ALLOW_INSTALL` (File → + File).
   This proves you own the server. The installer deletes it when done.

## 3. Run the wizard

1. Visit your site (e.g. `https://yourdomain.com/`). It redirects to the installer.
2. Go through each step:
   - **Database** — enter host (usually `localhost`), database name, username,
     password. Use *Test Connection*, then *Create Tables*.
   - **Admin account** — create your administrator login. This first account
     is the super admin.
   - **Company** — names, contact info, logo can come later in Settings.
   - **Install/Finalize** — writes the configuration and logs you in.
3. On the final screen, click **Run Database Final Check**.
   Scroll the report: every line should be green/blue.
   If any line is red, run it again — the Delete button stays locked
   until the check is completely clean.
4. Enter your admin password and click **Delete Installer Now**.
   This removes the whole `maintenance/` folder.

## 4. First login

1. Go to the login page and sign in as the admin you created.
2. Open **Admin → System Update → Run Schema Patcher** once, to be safe.
3. Open **Admin → 🔐 Security Settings** (super admin only) and review:
   - Two-factor auth: on (optional per user) or off.
   - Invite links: off means you set temporary passwords yourself.
   - Password length (8–12) and complexity level.

## 5. Stay safe afterwards

- Never re-upload `maintenance/` to a working site.
- Set up the automatic backup (`cron/backup.sh`, see `deploy/INSTALL_RUNBOOK.md`).
- After every update: log in → System Update → Run Schema Patcher.
- To verify a live install exposes nothing it shouldn't, run
  `deploy/verify-deployment.sh https://yourdomain.com/` from any machine
  with bash and curl.

## If something goes wrong

- **Wizard says "Already Installed"** but you haven't installed: delete
  `config.php`, `storage/installed`, and `maintenance/setup/lock` if present,
  then reload.
- **A step shows an error**: read it, fix the cause (usually wrong DB
  credentials), and retry that step — nothing half-done breaks anything.
- **Locked out completely**: see "Manual SSH recovery" in
  `deploy/INSTALL_RUNBOOK.md`.
