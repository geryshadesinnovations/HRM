<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

use App\Domains\Company\Models\Company;
use App\Platform\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait applied to every tenant-owned model.
 *
 *  - Adds the TenantScope global scope (auto `WHERE company_id = ?`).
 *  - Auto-fills `company_id` on create from the active TenantContext.
 *
 * See docs/01-ARCHITECTURE.md and docs/08-SECURITY.md.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->getAttribute($model->getTenantColumn()) === null) {
                $context = app(TenantContext::class);

                if ($context->hasTenant()) {
                    $model->setAttribute($model->getTenantColumn(), $context->companyId());
                }
            }
        });
    }

    public function getTenantColumn(): string
    {
        return defined(static::class.'::TENANT_COLUMN') ? static::TENANT_COLUMN : 'company_id';
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable().'.'.$this->getTenantColumn();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, $this->getTenantColumn());
    }
}
