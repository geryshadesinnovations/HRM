<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A priced bundle of modules + feature values + seat economics.
 * Platform-level. See docs/03-SUBSCRIPTION.md.
 *
 * Money fields are stored in minor units (paise).
 */
class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'billing_cycle', 'base_price', 'currency',
        'included_seats', 'per_seat_price', 'trial_days', 'is_public', 'is_active',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'per_seat_price' => 'integer',
        'included_seats' => 'integer',
        'trial_days' => 'integer',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')
            ->withPivot('value');
    }
}
