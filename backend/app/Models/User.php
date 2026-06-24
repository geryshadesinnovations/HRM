<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Company\Models\Company;
use App\Platform\Auth\Contracts\JwtSubject;
use App\Platform\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Platform + tenant user. Super Admins have a null company_id; all other users
 * belong to exactly one company.
 *
 * Roles/permissions come from Spatie; access is JWT-based.
 */
class User extends Authenticatable implements JwtSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuid;
    use Notifiable;

    protected $guard_name = 'api';

    protected $fillable = [
        'uuid', 'company_id', 'name', 'email', 'password', 'is_super_admin',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    // --- JwtSubject ---

    public function getJwtIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getJwtCustomClaims(): array
    {
        return [
            'company_id' => $this->company_id,
            'sa' => $this->isSuperAdmin(),
        ];
    }
}
