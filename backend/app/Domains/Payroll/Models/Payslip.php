<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-employee result of a payroll run. `breakdown` holds every component line.
 * Money in minor units. See docs/06-MODULES.md (Payroll Module).
 */
class Payslip extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'uuid', 'company_id', 'payroll_run_id', 'employee_id',
        'gross', 'deductions', 'net', 'worked_days', 'lop_days', 'breakdown',
    ];

    protected $casts = [
        'gross' => 'integer',
        'deductions' => 'integer',
        'net' => 'integer',
        'worked_days' => 'float',
        'lop_days' => 'float',
        'breakdown' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
