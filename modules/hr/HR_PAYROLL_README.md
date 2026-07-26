# HR Payroll — Nigeria-Compliant (deploy & usage)

## What this adds
Extends the **existing** `modules/hr` module (no duplicated HR system). Adds a
standard Nigeria payroll engine: progressive PAYE, NHF 2.5%, pension
employee 8% / employer 10% (with ₦3,000 floor), overtime from attendance,
salary advances (loans), per-period bonuses/commissions/deductions, a payslip
PDF, and an approval/paid workflow.

## Files
- `modules/hr/update_schema_v10_payroll.sql` — migration (additive; keeps all
  existing columns). Config-driven rates in the `settings` table.
- `modules/hr/classes/HR_Payroll.php` — `computeMonthly()` + pure
  `computePayeAnnual()`.
- `modules/hr/api/payroll-generate.php` — admin-only; computes & UPSERTs.
- `modules/hr/api/payslip-pdf.php` — mPDF payslip (own vs admin guarded).
- `modules/hr/pages/payroll.php` — Generate delegates to engine; Payslip live.

## Deploy steps (new instance)
1. Install base HR module first: run `modules/hr/hr_schema.sql` then
   `update_schema_v2.sql … update_schema_v9_*.sql` (in order).
2. Run this migration: `modules/hr/update_schema_v10_payroll.sql`.
   It is additive and safe to re-run (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
3. Ensure employee records have `basic_salary`, `housing_allowance`,
   `transport_allowance`, `other_allowances` populated.
4. Tune rates in the `settings` table if needed (keys prefixed `payroll_*`).
   Defaults: PAYE relief 20% (₦200k floor), NHF 2.5%, pension 8%/10%,
   overtime ×1.5, standard 173 h/month.

## How to run payroll
- Admin → HR → Payroll → pick month/year → **Generate Payroll**.
  This calls `api/payroll-generate.php`, which computes every active employee
  via `HR_Payroll::computeMonthly` and UPSERTs `hr_payroll`.
- Click **Payslip** on any row to download the mPDF payslip.

## Salary advances
- Insert into `hr_loans` (or build a small UI): `principal`, `interest_pct`,
  `term_months`, `monthly_repayment`, `start_month/year`, `status='active'`.
  The engine auto-deducts `monthly_repayment` (capped at `balance`) each period.

## Per-period adjustments
- Insert into `hr_payroll_items` with `type` in
  (bonus, commission, overtime, allowance, deduction, loan_repayment).

## Testing status
- **Payroll math: TESTED.** A runnable Python port (`test_payroll.py`, not
  committed) validates PAYE bracket boundaries, relief, NHF, pension,
  overtime, loans, and net/employer-cost against hand-computed values — all pass.
- **PHP/SQL runtime: NOT executed** in the build environment (no PHP runtime).
  Before going live, smoke-test on a PHP+MySQL host:
  1. Apply the SQL migration; confirm tables/columns exist.
  2. Create one test employee, Generate Payroll for a month, open the payslip PDF.
  3. Spot-check net = gross + OT + bonus − (PAYE + NHF + pension + loan + other).
  4. Verify an employee can open only their own payslip; admin can open any.

## Notes / caveats
- PAYE uses the standard consolidated relief (20% of gross or ₦200k, +₦1) and
  the published progressive brackets. Confirm against the current FIRS rates for
  the deployment year — brackets/relief can change; only the constants in
  `HR_Payroll::$PAYE_BRACKETS` and the `settings` rows need editing.
- Pension minimum (₦3,000) and NHF are applied on gross; employer pension is
  tracked as cost, not deducted from net.
- Overtime requires `hr_attendance` clock-in/out; if absent, overtime = 0.
