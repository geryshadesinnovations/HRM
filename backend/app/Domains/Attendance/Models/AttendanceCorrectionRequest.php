<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Employee\Models\Employee;
use App\Models\User;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to amend a day's attendance (req #6). Raised by an employee or HR,
 * reviewed by a user with `attendance.correction.approve`. On approval the
 * underlying AttendanceRecord is updated; this row is the audit trail.
 */
class AttendanceCorrectionRequest extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'uuid', 'company_id', 'employee_id', 'work_date',
        'requested_check_in', 'requested_check_out', 'requested_status',
        'reason', 'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'requested_check_in' => 'datetime',
        'requested_check_out' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
