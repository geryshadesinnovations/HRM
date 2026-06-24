<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Centralized entitlement enforcement. Every gated action funnels through here.
 * See docs/03-SUBSCRIPTION.md.
 */
final class FeatureAccessService implements FeatureAccess
{
    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly TenantContext $tenant,
    ) {}

    public function canAccessModule(string $moduleCode): bool
    {
        return $this->engine->forCurrentTenant()->hasModule($moduleCode);
    }

    public function canUseFeature(string $featureCode): bool
    {
        return $this->engine->forCurrentTenant()->booleanFeature($featureCode);
    }

    public function featureLimit(string $featureCode): ?int
    {
        return $this->engine->forCurrentTenant()->featureLimit($featureCode);
    }

    public function canAddEmployee(int $count = 1): bool
    {
        $entitlement = $this->engine->forCurrentTenant();

        if (! $entitlement->accessGranted) {
            return false;
        }

        $limit = $entitlement->seatLimit();

        if ($limit === null) {
            return true; // unlimited
        }

        return ($this->currentEmployeeCount() + $count) <= $limit;
    }

    public function canRunPayroll(): bool
    {
        return $this->canAccessModule('payroll');
    }

    public function assert(string $ability, mixed $context = null): void
    {
        if (Str::startsWith($ability, 'module:')) {
            $module = Str::after($ability, 'module:');
            if (! $this->canAccessModule($module)) {
                throw ApiException::moduleNotLicensed($module);
            }

            return;
        }

        if (Str::startsWith($ability, 'feature:')) {
            $feature = Str::after($ability, 'feature:');
            if (! $this->canUseFeature($feature)) {
                throw ApiException::featureNotAvailable($feature);
            }

            return;
        }

        if ($ability === 'payroll') {
            if (! $this->canRunPayroll()) {
                throw ApiException::moduleNotLicensed('payroll');
            }

            return;
        }

        if ($ability === 'seat') {
            $count = is_int($context) ? $context : 1;
            if (! $this->canAddEmployee($count)) {
                $entitlement = $this->engine->forCurrentTenant();
                throw ApiException::seatLimitReached(
                    $entitlement->seatLimit() ?? 0,
                    $this->currentEmployeeCount(),
                );
            }

            return;
        }
    }

    private function currentEmployeeCount(): int
    {
        $companyId = $this->tenant->companyId();

        if ($companyId === null || ! Schema::hasTable('employees')) {
            return 0;
        }

        // Counted via raw query to avoid a hard dependency on the Employee model
        // during early phases; switches to Employee::count() once that domain lands.
        return (int) DB::table('employees')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->count();
    }
}
