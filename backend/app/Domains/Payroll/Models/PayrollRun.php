<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payroll run for one (company, year, month): draft → processing →
 * completed → locked. Totals in minor units. See docs/06-MODULES.md (Payroll).
 */
class PayrollRun extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['draft', 'processing', 'completed', 'locked'];

    protected $fillable = [
        'uuid', 'company_id', 'period_year', 'period_month', 'status',
        'total_gross', 'total_deductions', 'total_net', 'processed_at',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_gross' => 'integer',
        'total_deductions' => 'integer',
        'total_net' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
