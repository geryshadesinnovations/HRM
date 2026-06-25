<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component amount within a salary structure (monthly, minor units).
 */
class SalaryStructureLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'salary_structure_id', 'salary_component_id', 'amount'];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
