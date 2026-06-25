<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned salary component (earning or deduction). See docs/06-MODULES.md.
 */
class SalaryComponent extends Model
{
    use BelongsToTenant;

    public const TYPES = ['earning', 'deduction'];

    protected $fillable = ['company_id', 'code', 'name', 'type', 'is_taxable'];

    protected $casts = [
        'is_taxable' => 'boolean',
    ];

    public function isEarning(): bool
    {
        return $this->type === 'earning';
    }
}
