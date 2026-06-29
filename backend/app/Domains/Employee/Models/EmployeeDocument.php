<?php

declare(strict_types=1);

namespace App\Domains\Employee\Models;

use App\Models\User;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single versioned document in an employee's secure vault (req #5).
 *
 * Binary content lives on a private filesystem disk; this row stores only the
 * metadata + storage path. Re-uploading the same `type` creates a new row with
 * an incremented `version`, so history is preserved. Tenant-scoped.
 */
class EmployeeDocument extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const TYPES = [
        'resume', 'offer_letter', 'contract', 'aadhaar', 'pan', 'passport',
        'license', 'education', 'experience', 'payslip', 'bank_passbook',
        'photo', 'other',
    ];

    protected $fillable = [
        'uuid', 'company_id', 'employee_id', 'type', 'title', 'original_name',
        'disk', 'path', 'mime', 'size', 'version', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'version' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
