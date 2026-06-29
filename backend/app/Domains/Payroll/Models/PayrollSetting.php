<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-company statutory configuration (PF / ESI / TDS). One row per tenant.
 * See App\Domains\Payroll\Services\StatutoryCalculator.
 */
class PayrollSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'pf_enabled', 'pf_employee_rate', 'pf_employer_rate', 'pf_wage_ceiling',
        'esi_enabled', 'esi_employee_rate', 'esi_employer_rate', 'esi_wage_ceiling',
        'tds_enabled', 'tds_regime', 'tds_standard_deduction',
    ];

    protected $casts = [
        'pf_enabled' => 'boolean',
        'pf_employee_rate' => 'float',
        'pf_employer_rate' => 'float',
        'pf_wage_ceiling' => 'integer',
        'esi_enabled' => 'boolean',
        'esi_employee_rate' => 'float',
        'esi_employer_rate' => 'float',
        'esi_wage_ceiling' => 'integer',
        'tds_enabled' => 'boolean',
        'tds_standard_deduction' => 'integer',
    ];
}
