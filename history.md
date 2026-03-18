# 1100ERP-AI Project History

This document logs all significant actions, commands, corrections, and modifications made across development sessions. Sensitive information (API keys, passwords, credentials) has been intentionally omitted.

---

## Commit History (Git)

| Hash | Date | Description |
|---|---|---|
| `8bd2cc7` | 2026-01-16 | First commit |
| `3151978` | 2026-01-16 | Milestone: Navigation redesign and ready-made quotes complete |
| `10acaa0` | 2026-02-xx | Release 2.0.0 — 1100-ERP System with Mobile Responsive, Dynamic Bank Accounts, and Audit Logging |
| `7f73e30` | 2026-03-18 | Security hardening: CSRF, RBAC, IDOR, PII encryption, audit log hash-chain, htaccess, session security, rate limiting, Argon2id, secure file uploads |

---

## Session History

---

### 2026-01-16 — Initial Development

**Actions:**
- Created the initial project structure for the 1100ERP system
- Set up XAMPP local development environment (Apache, PHP 8.2, MySQL)
- Implemented core modules: Quotes, Invoices, Receipts, Payments, Customers (CRM)
- Created database schema and initial migration scripts
- Set up routing and page structure under `/pages/`

---

### 2026-01-16 — Navigation & Quotes Milestone

**Actions:**
- Redesigned navigation sidebar for better UX
- Completed ready-made quotes module
- Implemented basic CRUD operations for all document types
- Integrated Tailwind CSS via CDN for styling

---

### 2026-02-xx — Release 2.0.0

**Actions:**
- Implemented mobile-responsive layout
- Added dynamic bank accounts management
- Created audit logging system (`includes/audit.php`) — tracking user actions to the database
- Various bug fixes and UI polish

---

### 2026-03-18 — Security Hardening (Phase 1 — Planning)

**Agent Actions:**
- Performed full codebase security audit
- Identified vulnerabilities: weak password hashing, no CSRF protection, raw SQL queries, no output escaping, weak access control
- Created `implementation_plan.md` outlining all security improvements
- User reviewed and approved the plan

---

### 2026-03-18 — Security Hardening (Phase 2 — Implementation)

#### 1. Authentication & Session Security

**Files Modified:**
- `includes/auth.php` — Upgraded password hashing algorithm to `PASSWORD_ARGON2ID`. Added transparent legacy password re-hash on successful login.
- `login.php` — Integrated CSRF token validation. Added rate limiting (5 attempts per 15 minutes). Enforced strong password policy on login/register.
- `pages/profile.php` — Added CSRF token to signature save requests.

**Commands Run:**
```
php -l includes/auth.php
php -l login.php
```

---

#### 2. SQL Injection Elimination

**Files Modified:**
- `config.php` — Migrated to PDO with: `ATTR_EMULATE_PREPARES => false`, `ATTR_ERRMODE => ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`. Integrated Dotenv loader.
- All pages under `/pages/`, `/api/`, `/modules/` — Audited and replaced raw `$pdo->query()` calls containing user input with `$pdo->prepare()` + `$stmt->execute()` parametrised queries.
- `includes/audit.php` — Fixed potential second-order injection in audit log writes.

---

#### 3. XSS Prevention & Security Headers

**Files Modified:**
- `includes/helpers.php` — Added `h()` shorthand helper: `htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8')`
- `includes/security.php` (NEW) — Created a central security utility containing:
  - `generateCSRFToken()` / `validateCSRFToken()` / `csrfField()`
  - `escape()` / `h()` output escaping
  - `setSecurityHeaders()` — CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
  - `checkLoginAttempts()` / `recordFailedLogin()` / `clearLoginAttempts()` — rate limiting
  - `validatePasswordPolicy()` — strong password enforcement
  - `encryptPII()` / `decryptPII()` — AES-256-GCM field-level encryption
- `includes/header.php` — Called `setSecurityHeaders()` on every page load.
- `pages/settings.php` — Applied strict TinyMCE configuration with `valid_elements` and `verify_html` to prevent XSS via the rich text editor.

**Corrections:**
- Fixed: `h()` function redeclared in both `security.php` and `helpers.php` — resolved by wrapping in `if (!function_exists('h'))` guard.
- Fixed: `ini_set()` session warnings — moved session `ini_set` calls to top-level of `security.php` (inside `session_status() === PHP_SESSION_NONE` guard) so they run before `session_start()`.

---

#### 4. Access Control Hardening (RBAC & IDOR)

**Files Modified:**
- `includes/permissions.php` — Expanded role system from 3 to 5 roles: `admin`, `manager`, `sales_rep`, `accountant`, `viewer`. Added:
  - `isAccountant()`, `isViewer()` helpers
  - `canViewDocument()` — ownership-aware viewing
  - `canEditDocument()` — ownership and status-aware editing
  - `canDeleteDocument()` — admin-only
  - `getRoleFilter()` — SQL filter based on role for list queries
  - `getRoleDisplayName()` / `getRoleBadge()` — UI display helpers
- `includes/api-auth.php` (NEW) — Token-based API authentication. `requireApiAuth()` validates `Authorization: Bearer <token>` header.
- `api/save-settings.php` — Protected with `requireApiAuth()`.

---

#### 5. Configuration & File Security

**Files Modified:**
- `includes/dotenv.php` (NEW) — Simple Dotenv loader that reads `.env` file and sets environment variables via `putenv()`.
- `config.php` — Updated to load DB credentials from `.env` using `getenv()` with fallbacks.
- `.env.example` (NEW) — Template for environment file:
  ```
  DB_HOST=localhost
  DB_NAME=[your_database]
  DB_USER=[your_username]
  DB_PASS=[your_password]
  DB_PREFIX=erp_
  ENCRYPTION_KEY=[your-32-character-key]
  ```
- `.htaccess` — Hardened with:
  - `Options -Indexes` — disable directory listing
  - `<FilesMatch>` — block direct access to `.env`, `.log`, `.sql`, `composer.*` files
  - `<IfModule mod_headers.c>` — fallback security headers

**Corrections:**
- **Bug**: `.htaccess` contained `<Directory>` blocks which are invalid in `.htaccess` context (only valid in `httpd.conf`). This caused a **500 Internal Server Error** for every page on the site.
- **Fix**: Replaced `<Directory>` blocks with `<FilesMatch>` rules. Site restored.
- Fixed: Trailing literal string ` Linda` in `includes/dotenv.php` causing a PHP parse error — removed.

---

#### 6. File Upload Security

**Files Modified:**
- `api/upload-logo.php` — Replaced predictable filename with `bin2hex(random_bytes(16))`. Added MIME type validation (only `image/jpeg`, `image/png`, `image/gif`, `image/webp` allowed).
- `api/save-signature.php` — Added CSRF protection. Replaced predictable filename with `bin2hex(random_bytes(16))`.
- `modules/hr/pages/signup-form.php` — Randomized passport and signature upload filenames using `bin2hex(random_bytes(16))`. Added image type validation.
- `modules/hr/pages/employee-form.php` — Same filename randomization and image type validation. Added CSRF token to employee form.

---

#### 7. Cryptographic Improvements

**Files Modified:**
- `includes/security.php` — Added `encryptPII($data)` and `decryptPII($data)` functions using `openssl_encrypt()` / `openssl_decrypt()` with `aes-256-gcm` mode, random `IV`, and authentication tag.
- `modules/hr/classes/HR_Employee.php`:
  - `createEmployee()` — Wrapped `nin_number` and `bvn_number` fields in `encryptPII()` before DB insert.
  - `updateEmployee()` — Same encryption applied in update loop.
  - `getEmployeeById()` — Added `decryptPII()` call on retrieval to transparently decrypt NIN/BVN for display.
- `includes/audit.php` — Added hash-chain to audit log:
  - On each log insert, fetches the hash of the previous record.
  - Computes `SHA-256(previousHash + action + resourceType + resourceId + userId + ipAddress + detailsJson)`.
  - Stores computed hash in new `hash` column, chaining all records for tamper detection.

**Corrections:**
- Fixed: Missing closing `}` brace at end of `security.php` — caused PHP parse error and 500 errors.
- Fixed: Extra `}` brace accidentally added during fix attempt — caused "unmatched brace" parse error — removed.

---

#### 8. Numeric Display Refinement (Separate Session)

**Context:** In a separate agent session (Conversation: "Refining Numeric Display"), the user requested removal of `.00` from quantity and unit price display fields.

**Files Modified:**
- Document creation/edit pages — Updated number formatting to use `intval()` for whole numbers and conditionally show decimals only when needed.

---

### 2026-03-18 — Verification & Testing

**Actions:**
- Created `tests/security_test.php` with 4 automated test cases:
  - CSRF token generation and validation
  - HTML escaping via `h()` helper
  - Password policy enforcement
  - PII encryption/decryption round-trip

**Commands Run:**
```
php -l includes/security.php       # Syntax check
php -l includes/dotenv.php         # Syntax check
php -l config.php                  # Syntax check
php tests/security_test.php        # Run test suite
```

**Test Results:**
```
--- Security Hardening Verification Suite ---
Testing CSRF Token Generation... PASSED
Testing HTML Escaping (h helper)... PASSED
Testing Password Policy... PASSED
Testing PII Encryption... PASSED
--------------------------------------------
```

---

### 2026-03-18 — Git Commit & Push

**Commands Run:**
```
git status --short
git add -A
git commit -m "Security hardening: CSRF, RBAC, IDOR, PII encryption, audit log hash-chain, htaccess, session security, rate limiting, Argon2id, secure file uploads"
git push origin main
```

**Result:** 30 files changed, 590 insertions, 115 deletions — pushed to `main` branch on GitHub (`ejoeltech/1100erp-ai`).

---

## Files Created (New)

| File | Purpose |
|---|---|
| `includes/security.php` | Central security utility (CSRF, escaping, headers, rate limiting, password policy, PII encryption) |
| `includes/dotenv.php` | Simple `.env` file loader |
| `includes/api-auth.php` | API Bearer token authentication |
| `.env.example` | Template for environment variables (no secrets committed) |
| `tests/security_test.php` | Automated security verification test suite |
| `history.md` | This file — project action log |

---

## Files Modified (Key)

| File | Key Changes |
|---|---|
| `includes/auth.php` | Argon2id hashing, transparent re-hash |
| `includes/permissions.php` | Expanded RBAC (5 roles), IDOR ownership checks |
| `includes/audit.php` | Hash-chain integrity for tamper detection |
| `includes/header.php` | Security headers injected |
| `includes/helpers.php` | `h()` escaping helper |
| `config.php` | Dotenv integration, PDO hardening |
| `.htaccess` | Directory listing disabled, sensitive files blocked |
| `login.php` | CSRF, rate limiting, strong password policy |
| `pages/settings.php` | TinyMCE hardened against XSS |
| `pages/profile.php` | CSRF on signature save |
| `api/upload-logo.php` | Random filenames, MIME validation |
| `api/save-signature.php` | CSRF, random filenames |
| `api/save-settings.php` | API token auth required |
| `modules/hr/classes/HR_Employee.php` | PII encrypt on write, decrypt on read |
| `modules/hr/pages/signup-form.php` | Random filenames, image type validation, CSRF |
| `modules/hr/pages/employee-form.php` | Random filenames, image type validation, CSRF |
| `setup/install.php` | Reviewed and updated for compatibility |

---

## Known Remaining Items

- [ ] Add `hash` column to `audit_log` table via migration (required for hash-chain feature)
- [ ] Create `.env` from `.env.example` and set a real 32-character `ENCRYPTION_KEY`
- [ ] Existing NIN/BVN values in database are unencrypted — a one-time migration script is needed
- [ ] Production `php.ini` hardening (disable `display_errors`, enable `log_errors`, etc.)
- [ ] Consider replacing Tailwind CDN with local build (CDN is not recommended for production)

---

*Last updated: 2026-03-18*
