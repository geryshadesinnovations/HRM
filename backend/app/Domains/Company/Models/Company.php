<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Subscription\Models\Subscription;
use App\Models\User;
use App\Platform\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant. The root of the multi-tenant model graph. NOT tenant-scoped itself.
 */
class Company extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'slug', 'status', 'timezone', 'currency',
        'country', 'gstin', 'settings', 'phone', 'industry', 'employees_estimate', 'address',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
