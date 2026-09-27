# SECURITY AUDIT — Eleven100 ERP

Branch: `security-hardening`. One commit per work package.
Rule: a fix is only marked **verified** after test/scan/reproduction. Otherwise **not verified**.

## Needs decision (product calls, not guesses)
- (WP0-C/WP14) Pre-auth restore endpoint: REMOVED from web wizard (WP14:
  deleted `maintenance/setup/api/restore_during_setup.php` + wizard restore
  UI). Restore via CLI only (`mysql < backup.sql`, see runbook).
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
    API key in a deleted dump. File gone from tree; key REVOKED at provider
    2026-09-26 (owner-confirmed).
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
- Third-party editor key (deleted dump): REVOKED 2026-09-26. Test encryption key: replace if reused anywhere.
- `api_tokens` table rows, if any were issued
- Admin passwords on dev/staging/prod (unknown sharing): reset at go-live
- Per-session CSRF/session secrets: random per session, nothing to rotate

### History purge (RUN 2026-09-26 — approved)
```sh
# Ran against a local mirror (network clone kept disconnecting; local refs
# verified byte-identical to origin first). filter-repo needed --force on the
# local mirror + a second pass for the residual root install-schema.sql seed:
git clone --mirror . 1100erp-ai-mirror.git
git filter-repo --force --path config.php --path maintenance/bluedots_1100erp.sql --path tests/security_test.php --invert-paths
git filter-repo --force --path install-schema.sql --invert-paths
# Verified: git log --all -- <each path> prints nothing;
# gitleaks detect --log-opts=--all reports no leaks found (was 3 + 1 residual).
# Force-pushed explicit refs only (main, security-hardening,
# feature/hr-nigeria-payroll, fix/ai-auth-header-and-pdf-export) + tags;
# local-only feature/archive-system-complete NOT published. Working copy
# re-synced via fetch + reset --hard; uncommitted feature work restored
# from backup (UTF-16 patch round-trip corrupted ₦/— in 3 files; repaired
# by hand, verified by lint + node --check).
```
Caveats stand: every hash rewritten; all clones/forks must re-clone;
anything copied out stays compromised — rotation below is mandatory
regardless of purging.

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

## WP5: XSS output-escaping sweep (verified 2026-09-26)

- Escaped ~70 stored/reflected sinks: HR self-service `signup-form.php`
  (all repopulated values, img srcs, banner), `onboarding-admin.php`
  (username built from applicant name, codes, passport src, DOB/gender,
  import error text), message banners (`leave/voting/payroll/attendance/
  employee-form/recruitment`), DB-backed selects/IDs (`(int)` casts),
  `record-payment.php` customer option, `settings.php` logo src/provider
  value + `CURRENT_PROVIDER` via `json_encode`, `edit-invoice.php`,
  `view-customer.php`, `manage-products.php`, footer company constants,
  header `<title>`, `email-document.php` (int-cast id, type allow-list
  fallback, escaped hidden inputs/subject).
- AI job-ad output now rendered as text (`nl2br`+escape): prompt-injection
  can no longer become script execution (formatting trade-off documented).
- Verified: `php -l` on all 19 files; login/signup render 200; grep shows
  0 raw `$entry/$e/$c` echoes in top HR files (was ~30).

## WP6: SQL hardening (verified 2026-09-26)

- Strict `validatedDbName()`/`validatedDbHost()` on every installer read
  (`install.php` ×7 + `USE`); same allow-list in `restore_during_setup.php`.
  Closes identifier injection in `CREATE DATABASE`/`USE` (backtick-strip was
  insufficient) and DSN smuggling.
- `selective-import.php`: table pattern + sensitive-table blocklist
  (users/invites/MFA/sessions/throttle/settings), column existence check
  against `SHOW COLUMNS`. Also fixed latent MariaDB bug (`SHOW TABLES
  LIKE ?` takes no placeholders — endpoint 500'd on every import).
- `api/ai/chat.php`: AI-SQL guard now blocks UNION/INTO/LOAD/
  INFORMATION_SCHEMA/system schemas/SLEEP/BENCHMARK/comments in addition
  to must-start-SELECT/no-semicolon; DB errors logged, generic to user.
- `SchemaPatcher::$addCol` refuses non-identifier table/column (defense in
  depth; callers are literals). `INTERVAL $windowSecs` verified int-safe
  (WP4 `max(60,(int))`, MFA helper hardcoded).
- Verified: `php -l` ×5; patcher 0 errors; HTTP import of crafted file —
  `users` skipped, no rogue user, legitimate table imports; guard-audit
  REVIEW 0; throttle/session rows cleaned.

## WP7: upload hardening (verified 2026-09-26)

- Central `validateImageUpload()` + `ensureUploadDir()` in
  `includes/security.php`: finfo-MIME + getimagesize content checks (never
  client extension), canonical extension from MIME, 3 MB cap,
  `is_uploaded_file()`, 0755 dirs, auto-written `.htaccess` (`php_flag
  engine off` + deny php/phtml/phar) so uploads can never execute.
- Wired into HR `signup-form.php` + `employee-form.php` (were ext-only,
  0777, unchecked moves), `id-cards.php saveUpload()` (+MIME on top of its
  size/getimagesize checks), `upload-logo.php` dir guard.
- `save-signature.php` (base64 path): 3 MB decoded cap +
  `getimagesizefromstring()` + guarded dir. `selective-import.php`: .json
  ext + 5 MB cap.
- Verified: CLI matrix ALL-PASS (real PNG ok; `shell.jpg` PHP and GIF
  polyglot rejected; oversize rejected; guard file content); `php -l` ×7;
  guard-audit REVIEW 0.

## WP8: error disclosure (verified 2026-09-26)

- 16 leak sites now log detail server-side (`error_log`) and return generic
  text: restore.php (+mysql stderr no longer echoed), system
  restore/optimize/factory-reset/apply-patch/analyze-logs, store
  items/categories, void/delete receipt, bulk-download-pdf, payslip-pdf,
  AI PDF export, setup restore (both catches).
- Out of scope (noted, not changed): `includes/simple-mailer.php` echoes
  live in uncommitted feature work — owner to fix on merge.
- Verified: `php -l` ×14; HTTP error-path probe returns generic text with
  no SQLSTATE/syntax leakage; guard-audit REVIEW 0.

## WP9: HR/payroll schema repair (verified 2026-09-26)

- `update_schema_v10_payroll.sql` used MariaDB-invalid `ADD COLUMN IF
  NOT EXISTS`: every v10 ALTER 1064'd, so fresh installs missed all 18
  columns (`paye/nhf/pension_exempt`, `hourly_rate_override`, 14 payroll
  breakdown cols) while payroll code reads/writes them. Rewrote as
  single-column ALTERs (runner ignores Duplicate-column on re-run).
- Found + fixed a second 1064: `;` inside two COMMENT strings broke the
  `;`-splitting runner (install.php shares the pattern) — reworded.
- Web path parity: all 18 columns (`$addCol`), `hr_payroll_items` +
  `hr_loans` tables, 9 `payroll_*` settings seeds folded into
  SchemaPatcher (0 errors; created the 4 missing `hr_employees` cols on
  dev). `HR_Payroll` override read is now `??`-guarded for stale DBs.
- Verified: v10 re-run yields zero 1064 (only Duplicate skips); all
  columns present; PAYE known-answer 300k→21,000; class loads under
  E_ALL warning-free; guard-audit REVIEW 0.

## WP10: headers & CSP (verified 2026-09-26)

- CSP gains `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
  `frame-ancestors 'self'`, `upgrade-insecure-requests` (unsafe-inline/eval
  stay: Tailwind CDN requirement, documented). `.htaccess` fallback aligned
  to DENY + conditional HSTS + backup-file deny (`.bak/.orig/~`).
- `logout()` redirects relatively (no Host-header influence).
  `getEmailTemplate()` verified allow-listed already — no change.
- Verified: `php -l` ×2; live headers show full CSP + DENY; logout 302 to
  relative login; login renders 200; guard-audit REVIEW 0.

## WP11: destructive-endpoint hardening (verified 2026-09-26)

- Central `inspectZipArchive()` (traversal/absolute/drive rejection +
  forbidden basenames) + `removeDirRecursive()` (temp-confined) in
  `includes/security.php`.
- `restore.php` + `api/system/restore.php`: 128 MB cap, inspected zips,
  `escapeshellarg()` on the SQL path, real temp cleanup. Fixed root
  `restore.php` writing uploads to `htdocs/uploads` (`/../../uploads`)
  instead of the app dir; per-entry containment + guarded target.
  API twin also gets the WP8-generic failure it missed.
- `apply-patch.php`: 25 MB cap, inspected zips, archives can never
  overwrite `.env`/`config.php`/`config.sample.php`/`.htaccess`;
  `update_script.php` RCE-by-design documented as contained-then-deleted.
- Verified: CLI matrix ALL-PASS (good/traversal/secrets/delete/guard);
  HTTP traversal patch rejected generic with nothing written; `php -l`
  ×4; guard-audit REVIEW 0.

## WP12: deploy hardening (verified 2026-09-26)

- Root `.htaccess` now denies `.git/`, `composer.json/lock`, `cron/`,
  `vendor/` (mod_rewrite `[F]` — this stack rejects RedirectMatch in
  `.htaccess`, documented inline), backup-file patterns, conditional HSTS.
  Cron PHP files were already CLI-guarded; backup/design already lived in
  `cron/` + per-dir denies for storage/logs/tmp/exports.
- `deploy/verify-deployment.sh` covers the new denies; runbook's dangling
  "WP12 backup script" pointers now reference `cron/backup.sh`.
- Verified per-URL over HTTP: cron/vendor/git/composer → 403, login → 200;
  guard-audit REVIEW 0.

## WP13: final verification & checklist (2026-09-26)

- Full-tree `php -l`: 228 files, 0 errors. `guard-audit --strict`: REVIEW 0.
  Gitleaks tree scan: no leaks (history-only items + rotation list unchanged,
  purge still awaiting approval). WP3 CSRF-aware regression re-run: 10/10
  (MFA fixtures enrolled for the run, then reset; temp ENCRYPTION_KEY
  removed from `.env` after).
- New `SECURITY_CHECKLIST.md`: operator runbook (install, secrets incl.
  mandatory ENCRYPTION_KEY, accounts, access control, server denies,
  backups, ongoing scans) with open decisions referenced.

## WP15: session-store unification (verified 2026-09-27)

- WP4's `configureSessionCookies()` moved sessions to a hardened path, but
  ~24 manual-`session_start()` endpoints (all of `api/*` custom bootstrap,
  `logout.php`, root `index.php`, `ai-settings.php`, `public-init.php`)
  kept reading the default store: authenticated POSTs bounced to login
  (and logout could not kill the real session). Every web session bootstrap
  now runs `configureSessionCookies()` first. Installer flows untouched
  (isolated, mutually consistent).
- Verified: previously-failing guard probes (group/user writes as admin)
  pass; WP3 regression 10/10; lint clean; guard-audit REVIEW 0.

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
| WP0-11 | Medium | root `index.php` | Auto-redirected visitors to `maintenance/setup/` when config missing | fixed (WP0-C: static 503, no link) → REVISED post-v1 by owner: first-visit redirect to `maintenance/setup/` restored (claim gate still guards the wizard) |
| WP0-12 | Medium | wizard restore endpoint | Pre-auth backup restore; decision pending | fixed (WP14: endpoint + wizard UI deleted; CLI restore documented) |
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
| WP1-02 | High | historical secret seeds (editor key, test key, provider key) | In git history only; tree clean per gitleaks | editor key revoked 2026-09-26; provider key + test key rotate; purge RUN |
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
| WP5-01 | High | HR self-service + review queue echoed applicant data raw (values, img src, username-from-name) | Stored XSS via signup form | fixed (WP5: escaped/cast throughout) |
| WP5-02 | Medium | message banners + status/category echoes raw across HR/core pages | Reflected/stored XSS via exception text, DB strings | fixed (WP5) |
| WP5-03 | Medium | AI job-ad HTML rendered raw | Prompt-injection XSS | fixed (WP5: text rendering) |
| WP5-04 | Low | footer constants, page title, settings JS string, customer option raw | Stored XSS if settings compromised; JS breakout | fixed (WP5) |
| WP6-01 | High | `$_POST['db_name']` interpolated into CREATE/USE (backtick-strip only) | Identifier injection pre-auth in setup | fixed (WP6: strict allow-list) |
| WP6-02 | High | selective import wrote any existing table/column from file | Crafted file → users/settings overwrite | fixed (WP6: blocklist + column check) |
| WP6-03 | High | AI-generated SQL guard allowed UNION/subquery/info_schema SELECTs | Prompt-injection data exfiltration | fixed (WP6: keyword blocklist) |
| WP6-04 | Low | `SHOW TABLES LIKE ?` placeholder (MariaDB 1064) | Import endpoint always 500'd | fixed (WP6: information_schema) |
| WP7-01 | High | HR photo/signature uploads ext-only, 0777, unchecked moves (incl. pre-auth self-service) | Polyglot webshell upload | fixed (WP7: content validation + guarded dirs) |
| WP7-02 | Medium | signature base64 + import JSON with no size/content checks | DoS / malformed writes | fixed (WP7: caps + image check) |
| WP7-03 | Medium | all upload dirs web-accessible, no `.htaccess`, no dirs in repo | Executable uploads on misconfig | fixed (WP7: auto-written no-exec guard) |
| WP8-01 | Low | PDO/exception text echoed to users across 16 endpoints | SQL/table/path disclosure | fixed (WP8: error_log + generic) |
| WP9-01 | High | v10 `ADD COLUMN IF NOT EXISTS` 1064'd on MariaDB — 18 payroll columns never created on fresh installs | Payroll generate fatals; override/exempt flags dead | fixed (WP9: valid ALTERs + patcher parity + seeds) |
| WP9-02 | Low | `;` inside COMMENT strings vs `;`-splitting runner | 2 more 1064s on CLI installs | fixed (WP9: reworded) |
| WP10-01 | Low | CSP missing object/base/form/frame lockdown; htaccess SAMEORIGIN vs DENY; logout absolute Host-based redirect | Plugin/frame/form-action abuse; header duplication | fixed (WP10) |
| WP15-01 | High | session-store split-brain (hardened path vs default) on ~24 manual endpoints | Authed POSTs bounced; logout missed | fixed (WP15: unified bootstrap) |
| WP16-01 | High | `!requirePermission()` void pattern bricked git/autopopulate endpoints for everyone | Dead admin tools | fixed (maintenance commit) |
| WP16-02 | High | maintenance state-changes via GET (pull/autopopulate); patch zips bundled `.env` | CSRF code deploy; secret exfil across installs | fixed (POST-only; exclusions) |
| WP16-03 | Medium | audit chain raced under concurrency (30 forked rows on dev); login rows unchained; LIMIT bind broken; logout/password changes unwired | Tamper evidence unreliable; silent gaps | fixed (audit commit: sentinel-serialized appends, chained login, wired events, chain badge) |
| WP11-01 | High | restore/patch zips extracted uninspected (zip-slip) | Arbitrary file overwrite as web user | fixed (WP11: pre-extract inspection) |
| WP11-02 | High | root restore.php uploads target escaped to `htdocs/uploads` | Media written outside app / copy fails | fixed (WP11: app uploads + containment) |
| WP11-03 | Medium | patch archives could overwrite `.env`/config; hand-quoted shell path; temp dirs never cleaned | Secret theft; shell breakout; disk fill | fixed (WP11) |
| WP12-01 | Medium | `.git`/composer/cron/vendor servable; runbook pointed at nonexistent backup script | Source/config disclosure | fixed (WP12: denies + pointers) |
