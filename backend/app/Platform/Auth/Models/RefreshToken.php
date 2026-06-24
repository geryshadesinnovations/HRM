<?php

declare(strict_types=1);

namespace App\Platform\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted refresh-token record enabling rotation + revocation + reuse
 * detection. Tokens are stored hashed; only the jti is indexed.
 *
 * See docs/08-SECURITY.md (Authentication).
 */
class RefreshToken extends Model
{
    protected $fillable = [
        'user_id', 'jti', 'token_hash', 'expires_at', 'revoked_at', 'replaced_by',
        'user_agent', 'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
