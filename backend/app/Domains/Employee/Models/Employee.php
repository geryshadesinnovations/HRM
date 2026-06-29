<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Domains\Attendance\Models\Shift;
use App\Models\User;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned employee record. Drives seat economics (active count vs seat
 * limit) and is the anchor for Attendance, Leave, and Payroll.
 *
 * Now a complete digital employee record (req #5): personal, contact,
 * employment, and salary/statutory information, plus a versioned document
 * vault. Sensitive statutory identifiers (PAN, Aadhaar, bank account number)
 * use Laravel `encrypted` casts so they are never stored in plaintext.
 */
class Employee extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STATUSES = ['active', 'on_leave', 'terminated'];

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern'];

    protected $fillable = [
        'uuid', 'company_id', 'user_id', 'employee_code', 'first_name', 'last_name',
        'email', 'phone', 'department_id', 'designation_id', 'manager_id',
        'date_of_joining', 'status',
        // Personal
        'profile_photo_path', 'gender', 'date_of_birth', 'blood_group',
        'marital_status', 'nationality',
        // Contact
        'emergency_contact_name', 'emergency_contact_phone', 'current_address', 'permanent_address',
        // Employment
        'employment_type', 'work_location', 'shift_id', 'confirmation_date', 'date_of_exit',
        // Salary / statutory
        'bank_account_name', 'bank_account_number', 'bank_ifsc', 'pan', 'aadhaar',
        'uan', 'pf_number', 'esi_number',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_birth' => 'date',
        'confirmation_date' => 'date',
        'date_of_exit' => 'date',
        // Encrypted at rest — sensitive PII / statutory identifiers.
        'bank_account_number' => 'encrypted',
        'pan' => 'encrypted',
        'aadhaar' => 'encrypted',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
