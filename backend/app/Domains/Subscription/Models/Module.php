<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dynamically-defined, sellable capability (attendance, payroll, ...).
 * Platform-level (not tenant-scoped). See docs/06-MODULES.md.
 */
class Module extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_module');
    }
}
