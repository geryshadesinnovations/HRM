<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\PayrollSetting;
use App\Platform\Tenancy\TenantContext;

/**
 * Computes Indian statutory deductions — Provident Fund (PF), Employees' State
 * Insurance (ESI), and income tax (TDS) — from a company's configurable
 * PayrollSetting. All money is integer minor units (paise).
 *
 * This is a transparent, rule-based estimator (not tax advice): PF on basic up
 * to a ceiling, ESI on gross under a ceiling, and a simplified annualised slab
 * for TDS. Every rate/ceiling is editable per company. See docs/06-MODULES.md.
 */
final class StatutoryCalculator
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Resolve (and lazily create) the tenant's statutory settings. */
    public function settings(): PayrollSetting
    {
        // refresh() ensures DB-default column values (rates, ceilings, toggles)
        // are hydrated even on the just-created row.
        return PayrollSetting::firstOrCreate(
            ['company_id' => $this->tenant->companyId()],
        )->refresh();
    }

    /**
     * Compute statutory deduction lines for one payslip.
     *
     * @param  int  $basic  monthly basic salary (paise)
     * @param  int  $gross  monthly gross earnings (paise)
     * @param  list<string>  $existingCodes  deduction codes already on the slip (manual lines win)
     * @return array<int,array{code:string,name:string,amount:int}>
     */
    public function deductions(int $basic, int $gross, array $existingCodes = []): array
    {
        $s = $this->settings();
        $lines = [];
        $present = array_map('strtoupper', $existingCodes);

        if ($s->pf_enabled && ! in_array('PF', $present, true)) {
            $pfWage = min($basic, (int) $s->pf_wage_ceiling);
            $pf = $this->pct($pfWage, (float) $s->pf_employee_rate);
            if ($pf > 0) {
                $lines[] = ['code' => 'PF', 'name' => 'Provident Fund (employee)', 'amount' => $pf];
            }
        }

        if ($s->esi_enabled && ! in_array('ESI', $present, true) && $gross > 0 && $gross <= (int) $s->esi_wage_ceiling) {
            $esi = $this->pct($gross, (float) $s->esi_employee_rate);
            if ($esi > 0) {
                $lines[] = ['code' => 'ESI', 'name' => 'ESI (employee)', 'amount' => $esi];
            }
        }

        if ($s->tds_enabled && ! in_array('TDS', $present, true)) {
            $tds = $this->monthlyTds($gross, $s);
            if ($tds > 0) {
                $lines[] = ['code' => 'TDS', 'name' => 'Income Tax (TDS)', 'amount' => $tds];
            }
        }

        return $lines;
    }

    /** Round a percentage of a minor-unit amount to the nearest paisa. */
    private function pct(int $amountMinor, float $rate): int
    {
        return (int) round($amountMinor * $rate / 100);
    }

    /**
     * Simplified monthly TDS: annualise gross, subtract the standard deduction,
     * apply slab tax, add 4% health & education cess, then divide by 12.
     */
    private function monthlyTds(int $monthlyGross, PayrollSetting $s): int
    {
        $annualTaxable = max(0, ($monthlyGross * 12) - (int) $s->tds_standard_deduction);
        $annualTax = $s->tds_regime === 'old'
            ? $this->oldRegimeTax($annualTaxable)
            : $this->newRegimeTax($annualTaxable);

        $withCess = (int) round($annualTax * 1.04); // 4% cess

        return intdiv($withCess, 12);
    }

    /**
     * FY24-25 new-regime slabs (₹, minor units). 87A rebate makes income up to
     * ₹7,00,000 effectively tax-free.
     */
    private function newRegimeTax(int $taxable): int
    {
        if ($taxable <= 70000000) { // ≤ ₹7,00,000 → rebate u/s 87A
            return 0;
        }

        $slabs = [
            [0, 30000000, 0.00],          // 0 – 3L
            [30000000, 70000000, 0.05],   // 3L – 7L
            [70000000, 100000000, 0.10],  // 7L – 10L
            [100000000, 120000000, 0.15], // 10L – 12L
            [120000000, 150000000, 0.20], // 12L – 15L
            [150000000, PHP_INT_MAX, 0.30],
        ];

        return $this->applySlabs($taxable, $slabs);
    }

    /** Old-regime slabs (without exemptions, basic estimate). */
    private function oldRegimeTax(int $taxable): int
    {
        if ($taxable <= 50000000) { // ≤ ₹5,00,000 → rebate u/s 87A
            return 0;
        }

        $slabs = [
            [0, 25000000, 0.00],          // 0 – 2.5L
            [25000000, 50000000, 0.05],   // 2.5L – 5L
            [50000000, 100000000, 0.20],  // 5L – 10L
            [100000000, PHP_INT_MAX, 0.30],
        ];

        return $this->applySlabs($taxable, $slabs);
    }

    /**
     * @param  array<int,array{0:int,1:int,2:float}>  $slabs  [from, to, rate]
     */
    private function applySlabs(int $taxable, array $slabs): int
    {
        $tax = 0.0;
        foreach ($slabs as [$from, $to, $rate]) {
            if ($taxable <= $from) {
                break;
            }
            $slabAmount = min($taxable, $to) - $from;
            $tax += $slabAmount * $rate;
        }

        return (int) round($tax);
    }
}
