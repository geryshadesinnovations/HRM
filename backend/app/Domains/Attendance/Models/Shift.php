<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned working-shift definition. Used to derive full-day minutes and
 * overtime when computing worked time.
 */
class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'name', 'start_time', 'end_time', 'full_day_minutes'];

    protected $casts = [
        'full_day_minutes' => 'integer',
    ];
}
