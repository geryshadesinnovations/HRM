<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A granular flag/limit/quota within a module. See docs/03-SUBSCRIPTION.md.
 *
 * type: boolean | limit | quota
 */
class Feature extends Model
{
    protected $fillable = ['module_id', 'code', 'name', 'type'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
