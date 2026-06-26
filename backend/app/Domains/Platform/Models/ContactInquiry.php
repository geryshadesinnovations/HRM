<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Platform\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * A "Contact Us" submission from the public marketing site. Platform-level
 * (not tenant-scoped); managed by the Super Admin. See req #1/#2.
 */
class ContactInquiry extends Model
{
    use HasUuid;

    public const STATUSES = ['new', 'contacted', 'closed'];

    protected $fillable = [
        'uuid', 'name', 'company_name', 'email', 'phone', 'subject', 'message', 'status', 'assigned_to',
    ];
}
