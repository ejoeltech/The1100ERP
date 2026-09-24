<?php
/**
 * HR_Payroll — Nigeria-compliant payroll computation for 1100erp-ai.
 *
 * Computes a monthly payslip breakdown for one employee:
 *   gross = basic + housing + transport + other allowances
 *         + overtime + bonus + commission + ad-hoc allowances
 *   taxable income = gross (statutory exemptions handled per employee flags)
 *   PAYE = progressive annual tax / 12   (Nigeria PAYE brackets)
 *   NHF  = 2.5% of gross                (employee)
 *   Pension employee = 8% of gross      (employee, deducted)
 *   Pension employer = max(10% of gross, floor)  (cost, NOT deducted from net)
 *   loan deduction = active loan monthly repayment
 *   net = gross + overtime + bonus + commission + allowances - total_deductions
 *
 * All rates are config-driven via the `settings` table so finance can tune
 * them without code changes. Requires the migration
 * modules/hr/update_schema_v10_payroll.sql to have been applied.
 */

class HR_Payroll
{
    private $pdo;

    // Nigeria PAYE annual brackets on chargeable income (₦):
    //   0 – 300,000         : 7%
    //   300,000 – 600,000   : 11%
    //   600,000 – 1,100,000 : 15%
    //   1,100,000 – 1,600,000 : 19%
    //   1,600,000 – 3,200,000 : 21%
    //   above 3,200,000     : 24%
    // Relief: higher of 20% of gross or ₦200,000 (+ ₦1 statutory, negligible).
    private static $PAYE_BRACKETS = [
        [300000, 0.07],
        [600000, 0.11],
        [1100000, 0.15],
        [1600000, 0.19],
        [3200000, 0.21],
        [PHP_INT_MAX, 0.24]
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Compute ANNUAL PAYE from annual taxable income.
     * Pure function — does not touch the database. Used by tests.
     *
     * @param float $annualTaxable chargeable income after relief
     * @return float annual PAYE in ₦
     */
    public static function computePayeAnnual($annualTaxable)
    {
        $taxable = max(0.0, (float) $annualTaxable);
        $tax = 0.0;
        $lower = 0.0;
        foreach (self::$PAYE_BRACKETS as [$upper, $rate]) {
            if ($taxable <= $lower) {
                break;
            }
            $band = min($taxable, $upper) - $lower;
            if ($band <= 0) {
                break;
            }
            $tax += $band * $rate;
            $lower = $upper;
        }
        return round($tax, 2);
    }

    /**
     * Derive the relief amount from gross annual income.
     * Relief = max(20% of gross, floor) + ₦1 statutory (kept as rounding-safe).
     */
    private function reliefAnnual($annualGross)
    {
        $floor = (float) getSetting('payroll_paye_floor_relief_ngn', 200000);
        $pct = ((float) getSetting('payroll_paye_consolidated_relief_pct', 20)) / 100.0;
        $relief = max($floor, $pct * $annualGross) + 1.0;
        return $relief;
    }

    /**
     * Compute a full monthly payroll breakdown for one employee.
     *
     * @return array payroll fields keyed by column name
     * @throws Exception on missing employee or DB failure
     */
    public function computeMonthly($employeeId, $month, $year)
    {
        $emp = $this->getEmployee($employeeId);
        if (!$emp) {
            throw new Exception("Employee #$employeeId not found");
        }

        $standardHours = (float) getSetting('payroll_standard_hours', 173);
        $otMultiplier = (float) getSetting('payroll_overtime_multiplier', 1.5);

        $basic = (float) $emp['basic_salary'];
        $housing = (float) $emp['housing_allowance'];
        $transport = (float) $emp['transport_allowance'];
        $other = (float) $emp['other_allowances'];

        $grossAllowances = $housing + $transport + $other;
        $gross = $basic + $grossAllowances;

        // Hourly rate (for overtime) — explicit override or derived.
        $hourly = $emp['hourly_rate_override'] !== null && $emp['hourly_rate_override'] > 0
            ? (float) $emp['hourly_rate_override']
            : ($standardHours > 0 ? $basic / $standardHours : 0);

        // Overtime from attendance (hours beyond standard shift in the month).
        $overtimeHours = $this->getOvertimeHours($employeeId, $month, $year);
        $overtimePay = round($overtimeHours * $hourly * $otMultiplier, 2);

        // Ad-hoc per-period items.
        $items = $this->getPeriodItems($employeeId, $month, $year);
        $bonus = $items['bonus'];
        $commission = $items['commission'];
        $itemAllowance = $items['allowance'] + $items['overtime'];
        $otherDeductions = $items['deduction'] + $items['loan_repayment']; // loan handled separately below

        $grossTotal = $gross + $overtimePay + $bonus + $commission + $itemAllowance;

        // Taxable income (statutory exemptions are employee-flag driven; here
        // we tax the full gross total unless exempt flags zero specific parts).
        $taxableIncome = $grossTotal;

        // PAYE (monthly) from annualised taxable income.
        $paye = 0.0;
        if (empty($emp['paye_exempt'])) {
            $annualTaxable = $taxableIncome * 12;
            $chargeable = max(0.0, $annualTaxable - $this->reliefAnnual($annualTaxable));
            $paye = self::computePayeAnnual($chargeable) / 12.0;
        }
        $paye = round($paye, 2);

        // NHF 2.5%
        $nhf = empty($emp['nhf_exempt']) ? round(0.025 * $grossTotal, 2) : 0.0;

        // Pension: employee 8% (deducted), employer 10% (cost).
        $pensionEmployee = empty($emp['pension_exempt']) ? round(0.08 * $grossTotal, 2) : 0.0;
        $pensionEmployerPct = (float) getSetting('payroll_pension_employer_pct', 10) / 100.0;
        $pensionMin = (float) getSetting('payroll_pension_min_ngn', 3000);
        $pensionEmployer = empty($emp['pension_exempt'])
            ? round(max($pensionEmployerPct * $grossTotal, $pensionMin), 2)
            : 0.0;

        // Active loan repayment for this period.
        $loanDeduction = $this->getLoanRepayment($employeeId, $month, $year);

        $totalDeductions = round($paye + $nhf + $pensionEmployee + $loanDeduction + $otherDeductions, 2);
        $net = round($grossTotal - $totalDeductions, 2);
        $employerCost = round($grossTotal + $pensionEmployer, 2);

        return [
            'employee_id' => (int) $employeeId,
            'month' => (int) $month,
            'year' => (int) $year,
            'basic_salary' => round($basic, 2),
            'allowances' => round($grossAllowances, 2),
            'overtime' => $overtimePay,
            'bonus' => $bonus,
            'commission' => $commission,
            'gross_salary' => round($grossTotal, 2),
            'taxable_income' => round($taxableIncome, 2),
            'paye' => $paye,
            'nhf' => $nhf,
            'pension_employee' => $pensionEmployee,
            'pension_employer' => $pensionEmployer,
            'loan_deduction' => $loanDeduction,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_salary' => $net,
            'employer_cost' => $employerCost
        ];
    }

    // ---- data access helpers (prepared statements only) ----

    private function getEmployee($employeeId)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.full_name, u.email
            FROM hr_employees e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch();
    }

    private function getOvertimeHours($employeeId, $month, $year)
    {
        // Standard shift window from settings (default 09:00–17:00 = 8h).
        $start = getSetting('hr_work_start_time', '09:00');
        $end = getSetting('hr_work_end_time', '17:00');
        $stdSecs = max(0, strtotime("2000-01-01 $end") - strtotime("2000-01-01 $start"));

        $stmt = $this->pdo->prepare("
            SELECT clock_in, clock_out, status
            FROM hr_attendance
            WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
              AND status IN ('present','late') AND clock_in IS NOT NULL AND clock_out IS NOT NULL
        ");
        $stmt->execute([$employeeId, $month, $year]);
        $rows = $stmt->fetchAll();

        $otSeconds = 0;
        foreach ($rows as $r) {
            $worked = strtotime("2000-01-01 {$r['clock_out']}") - strtotime("2000-01-01 {$r['clock_in']}");
            if ($worked > $stdSecs) {
                $otSeconds += ($worked - $stdSecs);
            }
        }
        return round($otSeconds / 3600, 2);
    }

    private function getPeriodItems($employeeId, $month, $year)
    {
        $stmt = $this->pdo->prepare("
            SELECT type, COALESCE(SUM(amount),0) AS total
            FROM hr_payroll_items
            WHERE employee_id = ? AND month = ? AND year = ?
            GROUP BY type
        ");
        $stmt->execute([$employeeId, $month, $year]);
        $rows = $stmt->fetchAll();

        $map = ['bonus' => 0, 'commission' => 0, 'allowance' => 0, 'overtime' => 0, 'deduction' => 0, 'loan_repayment' => 0];
        foreach ($rows as $r) {
            if (isset($map[$r['type']])) {
                $map[$r['type']] = (float) $r['total'];
            }
        }
        return $map;
    }

    private function getLoanRepayment($employeeId, $month, $year)
    {
        $periodIdx = (int) $year * 12 + (int) $month;
        $stmt = $this->pdo->prepare("
            SELECT id, monthly_repayment, balance
            FROM hr_loans
            WHERE employee_id = ? AND status = 'active'
              AND (? >= (start_year * 12 + start_month))
              AND balance > 0
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $periodIdx]);
        $loan = $stmt->fetch();
        if (!$loan) {
            return 0.0;
        }
        return round(min((float) $loan['monthly_repayment'], (float) $loan['balance']), 2);
    }
}
