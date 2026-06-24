<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned employee record. Demonstrates tenant scoping + seat economics.
 * Expanded in Phase 3 (documents, history, bulk import).
 */
class Employee extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'user_id', 'employee_code', 'first_name', 'last_name',
        'email', 'phone', 'department_id', 'designation_id', 'manager_id',
        'date_of_joining', 'status',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }
}
