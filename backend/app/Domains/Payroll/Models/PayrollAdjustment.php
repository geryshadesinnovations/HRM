<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit line attached to a payroll run (req #7): bonuses, incentives,
 * penalties, and lifecycle events (lock / reopen / recalculate). Amounts are in
 * minor units and may be negative (penalties/deductions).
 */
class PayrollAdjustment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPES = ['bonus', 'incentive', 'penalty', 'other', 'lock', 'reopen', 'recalculate'];

    protected $fillable = [
        'uuid', 'company_id', 'payroll_run_id', 'employee_id',
        'type', 'label', 'amount', 'note', 'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
