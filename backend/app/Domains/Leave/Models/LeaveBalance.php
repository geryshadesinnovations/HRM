<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per employee/type/year leave balance. `remaining = allocated - used`.
 */
class LeaveBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'year', 'allocated', 'used',
    ];

    protected $casts = [
        'year' => 'integer',
        'allocated' => 'float',
        'used' => 'float',
    ];

    public function getRemainingAttribute(): float
    {
        return round((float) $this->allocated - (float) $this->used, 1);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
