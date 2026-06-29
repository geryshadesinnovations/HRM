<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A registered biometric / kiosk punch device (req #6). Authenticates via a
 * hashed token; the plaintext token is shown only once at registration.
 */
class BiometricDevice extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'name', 'serial', 'location',
        'token_hash', 'is_active', 'last_seen_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
