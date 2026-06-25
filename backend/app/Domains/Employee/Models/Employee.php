<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Models\User;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned employee record. Drives seat economics (active count vs seat
 * limit) and is the anchor for Attendance, Leave, and Payroll.
 */
class Employee extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STATUSES = ['active', 'on_leave', 'terminated'];

    protected $fillable = [
        'uuid', 'company_id', 'user_id', 'employee_code', 'first_name', 'last_name',
        'email', 'phone', 'department_id', 'designation_id', 'manager_id',
        'date_of_joining', 'status',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.(string) $this->last_name);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
