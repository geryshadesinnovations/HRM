<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Subscription;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves a company's entitlement (modules + feature values + seat economics)
 * from its subscription + plan + per-tenant overrides, gated by status.
 *
 * Resolution is cached per company and invalidated when the subscription
 * changes. No hardcoded plan logic — everything comes from the database.
 *
 * See docs/03-SUBSCRIPTION.md.
 */
final class SubscriptionEngine
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function forCurrentTenant(): Entitlement
    {
        $companyId = $this->tenant->companyId();

        if ($companyId === null) {
            // Platform/super-admin context: no tenant entitlement.
            return $this->emptyEntitlement();
        }

        return $this->forCompany($companyId);
    }

    public function forCompany(int $companyId): Entitlement
    {
        $subscription = $this->tenant->bypass(
            fn () => Subscription::query()
                ->with(['plan.modules', 'plan.features'])
                ->where('company_id', $companyId)
                ->latest('id')
                ->first()
        );

        if ($subscription === null) {
            return $this->emptyEntitlement();
        }

        $cacheKey = sprintf('entitlement:%d:%d', $companyId, $subscription->updated_at?->timestamp ?? 0);

        return Cache::remember($cacheKey, now()->addHour(), fn () => $this->resolve($subscription));
    }

    private function resolve(Subscription $subscription): Entitlement
    {
        /** @var SubscriptionStatus $status */
        $status = $subscription->status;
        $plan = $subscription->plan;

        $modules = $plan?->modules->pluck('code')->all() ?? [];

        $features = [];
        foreach ($plan?->features ?? [] as $feature) {
            $features[$feature->code] = (string) $feature->pivot->value;
        }

        // Apply per-tenant overrides: { "modules": {"add":[],"remove":[]}, "features": {code:value} }
        $overrides = $subscription->overrides ?? [];

        foreach ($overrides['modules']['add'] ?? [] as $code) {
            if (! in_array($code, $modules, true)) {
                $modules[] = $code;
            }
        }
        $remove = $overrides['modules']['remove'] ?? [];
        $modules = array_values(array_filter($modules, fn ($c) => ! in_array($c, $remove, true)));

        foreach ($overrides['features'] ?? [] as $code => $value) {
            $features[$code] = (string) $value;
        }

        return new Entitlement(
            modules: $modules,
            features: $features,
            accessGranted: $status->grantsAccess(),
            includedSeats: $plan?->included_seats ?? 0,
            purchasedSeats: max(0, ($subscription->seats ?? 0) - ($plan?->included_seats ?? 0)),
            status: $status->value,
        );
    }

    private function emptyEntitlement(): Entitlement
    {
        return new Entitlement([], [], false, 0, 0, SubscriptionStatus::Expired->value);
    }
}
