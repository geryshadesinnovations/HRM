<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned organisational unit. See docs/06-MODULES.md (Employee Module).
 */
class Department extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['company_id', 'name'];

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
