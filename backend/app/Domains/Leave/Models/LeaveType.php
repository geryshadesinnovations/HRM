<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned leave type with accrual/carry-forward policy.
 * See docs/06-MODULES.md (Leave Module).
 */
class LeaveType extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'code', 'name', 'is_paid',
        'annual_quota', 'carry_forward', 'carry_forward_cap',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'carry_forward' => 'boolean',
        'annual_quota' => 'float',
        'carry_forward_cap' => 'float',
    ];
}
