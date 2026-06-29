<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company's live instance of a plan. Tenant-owned.
 * See docs/03-SUBSCRIPTION.md.
 */
class Subscription extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'plan_id', 'status', 'seats',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'grace_ends_at', 'cancelled_at', 'auto_renew', 'dunning_attempts', 'overrides',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'seats' => 'integer',
        'auto_renew' => 'boolean',
        'dunning_attempts' => 'integer',
        'overrides' => 'array',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
