<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned job title, optionally under a department.
 */
class Designation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['company_id', 'department_id', 'name'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
