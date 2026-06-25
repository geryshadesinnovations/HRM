<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\Employee\Models\Employee;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's salary structure: a set of component lines (monthly amounts).
 */
class SalaryStructure extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'employee_id', 'ctc_monthly'];

    protected $casts = [
        'ctc_monthly' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureLine::class);
    }
}
