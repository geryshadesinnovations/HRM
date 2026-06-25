<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row recording each approval decision on a leave request.
 */
class LeaveRequestApproval extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'leave_request_id', 'actor_user_id', 'decision', 'note',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
