# SECURITY AUDIT — Eleven100 ERP

Branch: `security-hardening`. One commit per work package.
Rule: a fix is only marked **verified** after test/scan/reproduction. Otherwise **not verified**.

## Needs decision (product calls, not guesses)
- (WP0-C) Pre-auth restore endpoint `maintenance/setup/api/restore_during_setup.php`: default recommendation is REMOVE from web wizard + document CLI restore (`mysql < backup.sql`). Kept token-gated for now pending decision.
- (Note, non-security) `modules/hr/update_schema_v10_payroll.sql:34` uses `ADD COLUMN IF NOT EXISTS`, which MariaDB rejects (1064) — the `hr_payroll.hourly_rate_override` column was never created. Pre-existing; flagged for the HR/payroll work (WP9).

## WP0 verification: fresh-install E2E (2026-09-25, throwaway instance)

Staged `C:\xampp\htdocs\1100-throwaway` (working-tree copy, no config.php)
with empty DB `1100throwaway` and drove the whole lifecycle over HTTP:
- Wizard UI without/wrong token → 403; with token → 200.
- All 6 actions (`test_connection`, `create_database`, `import_schema`,
  `create_admin`, `init_settings`, `finalize`) returned success; artifacts
  verified: `config.php`, `setup/lock`, `storage/installed`,
  `settings.installed_at`, admin user, 24 tables, install.token spent.
- Login as new admin → dashboard 200. Wizard UI/API refuse post-install
  ("Already Installed"). `final_check` as admin: 73 entries, 0 errors
  (also promoted first admin to super_admin per the always-one-super-admin
  rule); viewer gets 403.
- Cleanup (CSRF + password re-entry): 9 items deleted, `maintenance/`
  gone (404s), `storage/installed` kept, app login/dashboard unaffected.

## WP0-A: Remove spent and hazardous setup scripts — coverage record

Verified that `includes/SchemaPatcher.php` (`SchemaPatcher::run`) and/or
`database/install-schema.sql` (fresh installs) create the same objects as each
deleted "superseded" migration. Checked 2026-09-24, verified by running
SchemaPatcher against the dev database (`1100erp`).

| Deleted script | Covered by | How verified |
|---|---|---|
| `setup/add-column.php` (readymade.payment_terms) | Patcher `$addCol('readymade_quote_templates','payment_terms',…)` | lint + patcher run, `info` (exists) |
| `setup/add_created_by_column.php` | **Folded into patcher 2026-09-24** (`8a`: addCol + FK w/ existence checks) | patcher run: `info` (cols + FKs exist) |
| `setup/add_deleted_at_column.php` | Patcher `$addCol(…,'deleted_at',…)` ×6 tables | patcher run |
| `setup/update_products_table.php` (product_code+unique, category) | **Folded into patcher 2026-09-24** (addCol + backfill `PRD-%04d` + UNIQUE `idx_product_code`) | patcher run: `info` (exists) |
| `tools/add_signature_column.php` | **Folded into patcher 2026-09-24** (`users.signature_file`) + install-schema.sql:47 | patcher run: `info` (exists) |
| `tools/apply_schema_v2.php` (HR PII cols) | `modules/hr/update_schema_v2.sql` (canonical source, applied by `modules/hr/install.php`) | grep: cols present in v2 file |
| `tools/run_store_migration.php` | Patcher `item_categories` + `items` CREATEs | patcher run |
| `tools/restore_ai_tables.php` | Patcher `ai_usage_logs` + `ai_request_cache` + `ai_recommendations` CREATEs | patcher run |
| `setup/fix_functions.php` (get_market_data fn) | **Folded into patcher 2026-09-24** (`CREATE FUNCTION IF NOT EXISTS`) + install-schema.sql:564 | verified: dropped fn on dev, patcher recreated it |
| `setup/populate-solar-template.php` | **Folded into patcher 2026-09-24** (seed `3b`: idempotent category + template + 5 items, totals computed) | verified: patcher seeded missing template on dev |
| `tools/apply-patch.php` | Live twin `api/system/apply-patch.php` (kept) | filename grep after delete |
| `tools/check_db_integrity.php` | Reads install-schema.sql (kept) | n/a — diagnostic, superseded by patcher report |

Fresh-install loss check: the only seed a fresh install would have lost is the
`Solar Installation` / `8kVA Hybrid Solar System` template — now seeded by the
patcher (which the wizard runs via `run-schema-update.php` at Step 7, and
admins can re-run via System Update). No other deleted script seeded data.

Hardcoded credentials grep (`admin`/`password` seeds in `database/*.sql`):
none found in any kept file — the only `admin`/`password` seeds were inside
`tools/restore_full_schema.php` and `tools/recreate_users_table.php` (both
deleted in WP0-A). Demo-seed removal comments in phase2/phase3a SQL confirm
earlier cleanup.

## WP1: secrets and credentials

Gitleaks 8.30.1 (winget, scanner DB default ruleset):
- Working tree: **no leaks found** (16 MB scanned).
- Full history (`--all`, 49 commits): **3 leaks, all history-only**:
  - `maintenance/bluedots_1100erp.sql:940` @40ce4c8 — third-party editor
    API key in a deleted dump. File gone from tree; key must be revoked
    at the provider.
  - `tests/security_test.php:45` @7f73e30 — test-only encryption key.
    File deleted in WP0-A.
  - `install-schema.sql` settings seed @0740a4b — provider API key seed.
    Removed from the file since; assume compromised.
- `config.php` has committed history (old DB credentials assumed compromised).

### Rotation list (type + location, no values)
- MariaDB app user password: `config.php` history, live `.env`, deployed copies
- SMTP username/password: `settings` table (`smtp_username`, `smtp_password`)
- Telegram bot token / chat id: `settings` table
- WhatsApp verify token + app secret: `settings` table
- AI provider keys (`groq_api_key`, `ai_api_key`, `ai_custom_api_key`,
  per-provider): `settings` table + `GROQ_API_KEY` env if set + history seed
- Third-party editor key (deleted dump) and test encryption key: revoke/replace
- `api_tokens` table rows, if any were issued
- Admin passwords on dev/staging/prod (unknown sharing): reset at go-live
- Per-session CSRF/session secrets: random per session, nothing to rotate

### History purge (PREPARED, NOT RUN — awaiting approval)
```sh
# Fresh mirror clone; never run inside a working copy:
git clone --mirror https://github.com/ejoeltech/1100erp-ai.git 1100erp-ai-mirror.git
cd 1100erp-ai-mirror.git
# Preview exposure first:
git log --all --oneline -- config.php
# Single rewrite pass (combining paths avoids a second rewrite):
git filter-repo --path config.php --path maintenance/bluedots_1100erp.sql --path tests/security_test.php --invert-paths
# Verify, then force-push branches+tags and re-clone everywhere:
gitleaks detect --log-opts=--all
git log --all --oneline -- config.php   # must print nothing
```
Caveats: rewrites every commit hash; invalidates all clones, forks, milestone
tags and CI refs; requires force-push + full team re-clone; any secret already
copied out of the repo (forks, backups, the deleted dump files) stays
compromised — rotation above is mandatory regardless of purging.

## WP3-A: default-deny foundation (in progress)

- Deleted dead token auth (`includes/api-auth.php`: no issuer, no table);
  `api/save-settings.php` is session-only now.
- `public-init.php` only loads for 3 allow-listed scripts; fixed its
  super_admin-blind `isAdmin()`.
- Direct-execution guards on all of `includes/`, HR classes, config files.
- `pages/create-receipt.php` session-guarded; guard-audit extended
  (session-inline/cli-only detection, installer exceptions) — REVIEW 0.
- INCIDENT 2026-09-26: a PowerShell bulk-edit script corrupted 35 files
  (appended concatenated content; `php -l` caught it before any commit).
  Recovered exactly: git-checkout for committed files, verbatim replay
  from change history for uncommitted work, safe per-file edits for the
  guards. Verified by lint-all + all three behavior suites + page renders.
  Rule adopted: file writes only via edit/write tools, never scripts.

## WP4: authentication & session hardening (verified 2026-09-26)

- TOTP MFA (`includes/totp.php`, zero dependencies): RFC 6238 SHA1/30s/6-digit
  ±1-step; secrets AES-256-GCM at rest (ENCRYPTION_KEY); 10 single-use
  Argon2 recovery codes shown once. Mandatory for super_admin/admin/
  accountant + hr_manage/payroll_run holders (confinement to
  `pages/users/security-mfa.php`); enrollment/verify/disable/regen over
  session-check POST + CSRF; login pauses with zero privileges in
  `mfa_pending` (10-min) until `pages/login-mfa.php` verifies (TOTP or
  recovery, per-IP 10/h throttle, single-use enforced).
- Login rate limits (`login.php` + `throttleCheck()` fix): global 300/h,
  per-IP 30/h, per-user 10/15min, DB-backed (cookie-clear proof); generic
  messages + dummy verify (no oracle/timing leak); outcomes to audit_log.
  Fixed `throttleCheck()` floored window key (old NOW() PK never
  accumulated — limits never engaged).
- Sessions: strict/cookies-only/httponly/SameSite=Strict (+Secure on HTTPS),
  files outside web root, 5-min ID rotation, idle 30m / absolute 8h,
  server-side registry (`user_sessions`, hashed IDs) with revocation on
  password change (others), role/group/status change, deactivation (all),
  logout (current); fail-open only when tables missing (pre-migration).
- CSRF default-deny: central POST gate in `session-check.php` (field or
  X-CSRF-TOKEN header auto-injected by `helpers.js` fetch wrapper + meta
  tag); explicit `requireCsrf()` on manual-session APIs; tokens on all
  forms; 7 state-changing GETs converted to POST (convert/duplicate quote,
  duplicate/use-readymade, delete-quote/user/group, toggle user/product).
- Fail-closed fallbacks in session-check (old stubs returned true/allowed
  everything on stale DBs). Fresh-install coverage: WP4 objects folded into
  SchemaPatcher + install-schema.sql (patcher run: 0 errors, idempotent).
- guard-audit `--strict`: REVIEW 0 (`pages/login-mfa.php` allow-listed with
  its mfa_pending+CSRF+throttle gate documented).
- Verified: TOTP unit (3 RFC vectors + crypto + codes) ALL-PASS; MFA HTTP
  13/13; rate-limit 2/2; revocation+CSRF 7/7; WP3 CSRF-aware regression
  10/10; `php -l` clean; test users/rows deleted (`wp*`), throttle cleared,
  temp ENCRYPTION_KEY removed from `.env`.

## Findings
| ID | Severity | Location | Description | Status |
|---|---|---|---|---|
| WP0-01 | Critical | `maintenance/setup/factory-reset.php` | No auth; drops all tables, deletes config.php + lock on POST confirm | fixed (deleted WP0-A) |
| WP0-02 | Critical | `maintenance/setup/tools/clear-users.php` | No auth; wipes users on `?confirm=yes` | fixed (deleted WP0-A) |
| WP0-03 | Critical | `maintenance/setup/tools/restore_full_schema.php`, `recreate_users_table.php` | No auth; reset admin to published `admin`/`password`; recreate uses stale role ENUM corrupting auth | fixed (deleted WP0-A) |
| WP0-04 | High | `maintenance/setup/tools/clear-company-data.php` | No auth; blanks company settings | fixed (deleted WP0-A) |
| WP0-05 | High | `maintenance/setup/*`, `tools/*` (rest) | No gate; schema/data mutation by any visitor | fixed (deleted WP0-A) except entries under WP0-C/D/E |
| WP0-06 | High | `database/run-leads-migration.php` | Comment claims "admin only", code checks nothing | fixed (WP0-E: schema + INSERT IGNORE seed folded into patcher + install-schema.sql; runner deleted; refs updated) |
| WP0-07 | High | `modules/hr/install.php` | Config-only gate, swallows errors | fixed (WP0-E: CLI-only 403 over HTTP, audit-log entry; loud warnings kept) |
| WP0-08 | Medium | installer `create_admin` | `DELETE FROM users` before insert; no install token; first-come claim on fresh copies | fixed (WP0-C rev2: proof-of-write claim file `ALLOW_INSTALL` instead of token — equal strength, usable via file manager/FTP; create_admin aborts on non-empty users; storage/installed marker; root index.php no longer redirects to wizard) |
| WP0-09 | Medium | `tests/security_test.php`, `updates/` | Web-accessible dev artifacts | fixed (deleted WP0-A) |
| WP0-10 | Low | stale seed SQL in `database/` | `DELETE FROM` reseeds, never referenced | fixed (deleted WP0-A) |
| WP0-11 | Medium | root `index.php` | Auto-redirected visitors to `maintenance/setup/` when config missing | fixed (WP0-C: static 503, no link) |
| WP0-12 | Medium | wizard restore endpoint | Pre-auth backup restore; kept pending decision with token gate | needs decision (WP0-C: token-gated for now; default recommendation remains removal) |
| WP0-13 | High | `maintenance/setup/run-schema-update.php` | No gate; linked from Step 7 | fixed (WP0-D: replaced by token/admin-gated `final_check` action in install.php; file deleted) |
| WP0-14 | Medium | `maintenance/setup/cleanup.php` | Needed only fresh session + manage_settings | fixed (WP0-D: admin + POST + CSRF + password re-entry, realpath guard, token cleanup, leftover verification) |
| WP0-15 | High | `modules/hr/api/generate-document.php` | Login-only; any role can generate docs for any employee_id (IDOR) | open → WP3 (needs hr-scoped permission + ownership check) |
| WP2-01 | High | `HR_Employee::createEmployee` fixed `password123`; onboarding import used phone-as-password | Guessable credentials on new accounts | fixed (WP2: random unknowable password + 48h single-use invite, shown once) |
| WP2-02 | High | onboarding codes `md5(uniqid())` 6-hex, plaintext compare, no expiry/rate-limit | Brute-forceable public signup | fixed (WP2: 52-bit crypto codes, SHA-256 verify, 30d expiry, per-IP + per-code throttle; verified incl. HTTP lockout) |
| WP2-03 | Medium | 6-char policy (forms, API, installer 8-char hint) | Weak passwords accepted | fixed (WP2: 12+ chars, blocklist, numeric/username checks, new≠old; verified) |
| WP2-04 | Medium | mixed bcrypt/argon2 calls; duplicate policy fn in security.php | Divergent hashing, collision fatal | fixed (WP2: single `includes/passwords.php`, Argon2id tuned, rehash kept; old fn removed) |
| WP2-05 | Medium | no forced-reset mechanism; admin-set known passwords | No clean recovery path | fixed (WP2: `must_change_password` + invite resets; login blocks flagged accounts) |
| WP2-06 | Low | plaintext `code` column retained for admin distribution UX | DB read exposes unused codes (verification is hash-only; expiry + single-use + throttle still apply) | accepted risk (documented; revisit if desired) |
| WP2-07 | Low | `api/ai/update-settings.php` crude `role !== 'admin'` check (locks out super_admin) | Coarse/wrong gate | fixed (WP3-E: manage_settings) |
| WP3-07 | Medium | coarse/broken gates: `clear-cache` compared against `'Admin'` (never true — open to any login), `emergency-toggle`/`export-usage`/`update-settings`/`audit-log`/`ai-settings` locked out super_admin, dashboard widgets + upload-logo + manage-users fallbacks raw-compared roles | Open admin functions; locked-out super_admin | fixed (WP3-E: requirePermission manage_settings/view_audit_log/view_system_dashboard; isAdmin() fallbacks) |
| WP3-08 | Medium | dashboard recent/counts + archives dead `view_reports` perm | Cross-user data leak on dashboard; archives denied everyone | fixed (WP3-E: sales_rep scoping on aggregates; archives mapped to view_all_documents) |
| WP3-09 | Medium | `pages/audit-log.php` fatal (redeclared helper) — page 500 for everyone | Broken audit UI | fixed (WP3-E: function_exists guard; pre-existing) |
| WP3-10 | Low | `api/system/factory-reset.php` + `maintenance/setup/cleanup.php` raw verify calls | Divergent hashing | fixed (WP3-E: verifyPassword() helper) |
| WP3-02 | High | sales IDOR: detail pages, all export variants, bulk download, convert/duplicate/generate/finalize/void/delete flows, update-quote/invoice POSTs (one with no login check at all) lacked ownership checks | Any login could read/edit others' financial docs | fixed (WP3-C: canViewDocument/canEditDocument + per-type perms + sales_rep scoping on lists/bulk/dashboard/recent/allocations) |
| WP3-03 | High | dead permissions (`manage_customers/products/leads`, `view_reports`, `view/create_payment`, `manage_quotes`) denied entire modules to everyone but super_admin | Broken modules / fail-closed accidents | fixed (WP3-C: defined in catalog + legacy + seeds; archives mapped to view_all_documents) |
| WP3-04 | High | client-supplied money (line/total/vat/grand/amount_paid/balance, any status string) stored verbatim | Forged totals and balances | fixed (WP3-D: server recalculation via recalcDocumentTotals + sanitizeDocumentStatus; invoice paid-state recomputed, never posted) |
| WP3-05 | Medium | store/accessories/inventory APIs + manage pages open to any login | Unauthorized stock/price changes | fixed (WP3-D: manage_store/manage_accessories on writes + manage pages; reads stay open for pickers) |
| WP3-06 | Medium | proposals table missing on fresh installs; no ownership, no creator attribution | Broken feature + cross-user edits | fixed (WP3-D: CREATE in patcher + schema, created_by + ownership gates) |
| WP3-01 | High | HR pages/APIs open to any login (viewer/sales_rep could open employees, payroll, recruitment, voting, ID cards, run payroll, pull staff PII feed) | Missing object-level gates | fixed (WP3-B: hr_view/hr_manage/leave_manage/payroll_view/payroll_run/recruitment_manage enforced on 11 pages + 4 APIs; self-service leave/attendance/payslip stay ownership-scoped; verified 36/36 page + 7/7 API matrix) |
| WP1-01 | High | `config.php` committed history | Old DB credentials assumed compromised | rotation list above; purge commands prepared, awaiting approval |
| WP1-02 | High | historical secret seeds (editor key, test key, provider key) | In git history only; tree clean per gitleaks | revoke at providers + rotate; purge awaiting approval |
| WP1-03 | Medium | secret values rendered into settings HTML (`smtp_password`, `ai_api_key`, `groq_api_key` hidden, `telegram_bot_token`, `whatsapp_app_secret`) | Any admin session/XSS read them; blank saves wiped them | fixed (WP1: fields render blank + saved indicators; save path skips empty secrets; verified keep + rotate) |
| WP1-04 | Medium | `config.php` disclosed PDO errors (host/db/user); permissive root/empty defaults | Info disclosure + weak-default encouragement | fixed (WP1: env-first loader, fail-fast 503, no error details; verified both paths) |
| WP1-05 | Medium | installer `generateConfig()` embedded secrets in defines | Every installed config.php carried creds in code | fixed (WP1: wizard writes `.env`, copies secret-free loader) |
| WP1-06 | Low | `.git/` deployability | Must never be web-accessible | open → WP12 server configs (deny rules) |
| WP4-01 | High | login rate limit (`throttleCheck()` NOW() PK + session-based `checkLoginAttempts`) | Counters never accumulated across requests; cookie-clear reset | fixed (WP4: floored DB window key; global+IP+user limits; verified lockout) |
| WP4-02 | High | no MFA; password-only for privileged roles | Credential theft = full takeover | fixed (WP4: mandatory TOTP + recovery for admin/accountant/HR-payroll; verified) |
| WP4-03 | High | state-changing GETs (convert/duplicate/delete/toggle links) | CSRF-able one-click actions | fixed (WP4: POST+CSRF forms; GET rejected; verified) |
| WP4-04 | High | missing CSRF on ~all POST APIs/forms | Session-riding forged writes | fixed (WP4: central gate + header wrapper + tokens; verified incl. 403 negatives) |
| WP4-05 | Medium | sessions: no strict mode, webroot files, no idle/absolute timeout, no revocation | Fixation/theft persistence; ex-staff sessions survive | fixed (WP4: hardened cookies, 30m/8h, registry + revocation; verified) |
| WP4-06 | Medium | fail-open permission stubs on stale DBs | Every check passed pre-migration | fixed (WP4: fail-closed; verified) |
| WP4-07 | Low | `wp4-auth-migration.sql` used MariaDB-invalid `ADD COLUMN IF NOT EXISTS` | Manual migration 1064s | fixed (plain ADD COLUMN + patcher idempotency note) |
