# Security Checklist — Eleven100 ERP (post WP4–WP13)

Branch: `security-hardening`. Details + evidence: `SECURITY_AUDIT.md`.
Verify endpoint: `deploy/verify-deployment.sh https://your-host`.

## 1. Install (fresh box)
- [ ] Deploy code WITHOUT `config.php`, `.env`, `.git/`, `docs/`, `*.sql` dumps.
- [ ] Create `maintenance/setup/ALLOW_INSTALL` (proof-of-write claim), run wizard, delete `maintenance/` after.
- [ ] Confirm `config.php` + `setup/lock` + `storage/installed` exist; root shows static 503 otherwise.

## 2. Secrets (never in code, logs, or screenshots)
- [ ] Set via env / `APP_SECRETS_FILE` / `.env` (0600, outside web root in prod).
- [ ] `ENCRYPTION_KEY` set (32+ random bytes) — MFA enrollment refuses without it.
- [ ] Rotate everything in the audit rotation list (WP1): DB password, SMTP,
      Telegram, WhatsApp, AI provider keys, editor/test keys from history.
- [ ] Settings secret fields render blank; save skips empty (verify keep + rotate).

## 3. Accounts
- [ ] No default credentials: users come from 48h single-use invites only.
- [ ] 12+ char passwords (blocklist enforced); `must_change_password` honored.
- [ ] Privileged roles enrolled in TOTP MFA (super_admin/admin/accountant, HR/payroll).
- [ ] Recovery codes stored offline; regen after use.

## 4. Access control
- [ ] Least-privilege groups; HR (`hr_view/manage`, `leave_manage`,
      `payroll_view/run`, `recruitment_manage`) only where needed.
- [ ] Deactivation revokes sessions (automatic); audit log reviewed.

## 5. Server / deploy
- [ ] Apache serves denies: `.git`, `composer.*`, `cron/`, `vendor/`,
      `storage/`, `logs/`, `*.sql`, `*.bak` (run verify-deployment.sh).
- [ ] HTTPS enforced at proxy/host; HSTS active; `uploads/` never executes PHP
      (auto `.htaccess`, plus server-level deny as backup).
- [ ] `cron/backup.sh` scheduled (encrypted, rotated, Telegram report).

## 6. Ongoing
- [ ] System Update → schema patch after each deploy; review `audit_log`.
- [ ] Re-run: `php scripts/guard-audit.php --strict` (expect REVIEW 0),
      gitleaks on tree, this checklist after every release.
- [ ] Open decisions tracked in `SECURITY_AUDIT.md`: pre-auth restore endpoint
      (default: remove), history purge + rotations (awaiting approval).
