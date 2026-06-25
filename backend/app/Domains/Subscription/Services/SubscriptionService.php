<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Subscription lifecycle changes initiated by a company:
 *   - upgrade   : applies immediately (more modules/seats available now)
 *   - downgrade : scheduled for the next period (no mid-cycle loss of paid access)
 *   - cancel    : stops auto-renew; access continues until period end, then blocks
 *   - reactivate: restores a cancelled/expired subscription
 *
 * Entitlement cache invalidation is automatic: every change bumps
 * `subscriptions.updated_at`, which is part of the cache key in SubscriptionEngine.
 *
 * See docs/03-SUBSCRIPTION.md and docs/04-BILLING.md.
 */
final class SubscriptionService
{
    public function __construct(private readonly SubscriptionEngine $engine) {}

    public function upgrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        if ($newPlan->base_price < ($subscription->plan?->base_price ?? 0)) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'Use downgrade for a lower-priced plan.',
                [],
                422,
            );
        }

        return DB::transaction(function () use ($subscription, $newPlan) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'seats' => max((int) $subscription->seats, (int) $newPlan->included_seats),
                'status' => SubscriptionStatus::Active,
                // Clear any previously scheduled downgrade.
                'overrides' => collect($subscription->overrides ?? [])->forget('pending_downgrade')->all(),
            ]);

            $this->engine->flush((int) $subscription->company_id);

            return $subscription->refresh();
        });
    }

    /**
     * Schedule a downgrade to take effect at the end of the current period.
     */
    public function downgrade(Subscription $subscription, Plan $newPlan): Subscription
    {
        $overrides = $subscription->overrides ?? [];
        $overrides['pending_downgrade'] = [
            'plan_code' => $newPlan->code,
            'effective_at' => optional($subscription->current_period_end)->toIso8601String()
                ?? Carbon::now()->endOfMonth()->toIso8601String(),
        ];

        $subscription->update(['overrides' => $overrides]);

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'auto_renew' => false,
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->engine->flush((int) $subscription->company_id);

        return $subscription->refresh();
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        $now = now();
        $cycle = $subscription->plan?->billing_cycle ?? 'monthly';

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'auto_renew' => true,
            'current_period_start' => $now,
            'current_period_end' => $cycle === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth(),
            'grace_ends_at' => null,
        ]);

        $this->engine->flush((int) $subscription->company_id);

        return $subscription->refresh();
    }
}
