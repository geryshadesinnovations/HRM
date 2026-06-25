<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendance row per (company, employee, work_date). Source tracks how it
 * was captured so future methods (gps/qr/biometric) need no schema change.
 * See docs/06-MODULES.md (Attendance Module).
 */
class AttendanceRecord extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['present', 'absent', 'half_day', 'leave', 'holiday'];

    public const SOURCES = ['manual', 'self', 'gps', 'qr', 'biometric'];

    protected $fillable = [
        'company_id', 'employee_id', 'shift_id', 'work_date', 'status',
        'check_in', 'check_out', 'worked_minutes', 'overtime_minutes', 'source',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'worked_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
