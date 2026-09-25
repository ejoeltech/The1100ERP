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
