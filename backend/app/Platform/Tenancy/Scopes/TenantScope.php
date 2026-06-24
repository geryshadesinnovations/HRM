<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Scopes;

use App\Platform\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query on a tenant-aware model to the
 * active company. Applied automatically by the BelongsToTenant trait.
 *
 * When the TenantContext is bypassed (super-admin/platform), no constraint is
 * added. When there is no active tenant and no bypass, the scope forces an
 * empty result set as a safety measure (fail closed).
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $column = $model->getQualifiedTenantColumn();

        if ($context->hasTenant()) {
            $builder->where($column, $context->companyId());

            return;
        }

        // Fail closed: no tenant resolved and not explicitly bypassed.
        $builder->whereRaw('1 = 0');
    }
}
