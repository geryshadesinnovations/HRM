<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's leave request moving through pending → approved/rejected.
 * See docs/06-MODULES.md (Leave Module).
 */
class LeaveRequest extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'uuid', 'company_id', 'employee_id', 'leave_type_id',
        'start_date', 'end_date', 'days', 'status', 'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }
}
