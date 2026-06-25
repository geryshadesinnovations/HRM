<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Platform\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * A single in-app notification for one user. Not tenant-scoped: it is always
 * queried by user_id, so cross-tenant leakage is impossible.
 */
class Notification extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'user_id', 'company_id', 'type', 'title', 'body', 'data', 'channel', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
