<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Console;

use App\Domains\Notification\Services\NotificationService;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Subscription;
use App\Domains\Subscription\Services\SubscriptionEngine;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Advances subscription lifecycle states based on dates. Designed to run on a
 * schedule (hourly/daily). Idempotent — safe to run repeatedly.
 *
 *  - trial   → expired  when trial_ends_at has passed and no active period
 *  - active  → grace    when current_period_end has passed (auto-renew failed/none)
 *  - grace   → expired  when grace_ends_at has passed
 *
 * Access is gated by status (see SubscriptionStatus::grantsAccess); this command
 * never deletes data. Affected companies' admins are notified. See docs/03-SUBSCRIPTION.md.
 */
final class SweepSubscriptions extends Command
{
    protected $signature = 'subscriptions:sweep {--now= : Override "now" (ISO 8601) for testing}';

    protected $description = 'Advance subscription lifecycle states (trial/active/grace/expired) by date.';

    public function __construct(
        private readonly SubscriptionEngine $engine,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(TenantContext $tenant): int
    {
        $now = $this->option('now') ? Carbon::parse($this->option('now')) : now();

        // Platform-wide operation: bypass tenant scoping.
        $counts = $tenant->bypass(fn () => [
            'trial_expired' => $this->expireTrials($now),
            'active_to_grace' => $this->activeToGrace($now),
            'grace_expired' => $this->expireGrace($now),
        ]);

        $this->info(sprintf(
            'Swept subscriptions @ %s — trial→expired: %d, active→grace: %d, grace→expired: %d',
            $now->toDateTimeString(),
            $counts['trial_expired'],
            $counts['active_to_grace'],
            $counts['grace_expired'],
        ));

        return self::SUCCESS;
    }

    private function expireTrials(Carbon $now): int
    {
        $query = Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->where(function ($q) use ($now): void {
                $q->whereNull('current_period_end')->orWhere('current_period_end', '<', $now);
            });

        return $this->transition($query, ['status' => SubscriptionStatus::Expired], 'subscription.expired', 'Your trial has ended', 'Your free trial has ended. Subscribe to keep access to your modules.');
    }

    private function activeToGrace(Carbon $now): int
    {
        $query = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now);

        return $this->transition($query, [
            'status' => SubscriptionStatus::Grace,
            'grace_ends_at' => $now->copy()->addDays(5),
        ], 'subscription.grace', 'Payment due', 'Your subscription period ended. You are in a short grace period — please renew to avoid interruption.');
    }

    private function expireGrace(Carbon $now): int
    {
        $query = Subscription::query()
            ->where('status', SubscriptionStatus::Grace)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', $now);

        return $this->transition($query, ['status' => SubscriptionStatus::Expired], 'subscription.expired', 'Subscription expired', 'Your subscription has expired and module access is paused. Renew anytime to restore it — your data is safe.');
    }

    /**
     * Apply an update to all matching subscriptions, flush their entitlement
     * caches so access changes take effect immediately, and notify each
     * affected company's admins.
     *
     * @param  array<string,mixed>  $attributes
     */
    private function transition(Builder $query, array $attributes, string $type, string $title, string $body): int
    {
        $companyIds = (clone $query)->pluck('company_id')->all();

        $affected = $query->update($attributes);

        foreach (array_unique($companyIds) as $companyId) {
            $this->engine->flush((int) $companyId);
            $this->notifications->toCompanyAdmins((int) $companyId, $type, $title, $body);
        }

        return $affected;
    }
}
