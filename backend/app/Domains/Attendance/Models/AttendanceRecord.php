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
        'check_in', 'check_out', 'break_in', 'break_out', 'break_minutes',
        'worked_minutes', 'overtime_minutes', 'late_minutes', 'early_minutes',
        'source', 'capture_method', 'biometric_device_id',
        'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng',
        'locked', 'notes',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'break_in' => 'datetime',
        'break_out' => 'datetime',
        'break_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
        'locked' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
