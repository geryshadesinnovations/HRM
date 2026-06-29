<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Notification\Services\NotificationService;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Subscription;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Billing cycle worker (req #3): auto-renews due subscriptions and runs dunning
 * on overdue invoices. Designed to run daily. Idempotent. Platform-wide
 * (tenant-bypassed). See docs/04-BILLING.md.
 *
 *  - Auto-renew: active + auto_renew subscriptions whose period has ended get a
 *    fresh period + a new open invoice.
 *  - Dunning: open invoices past due are retried up to MAX_ATTEMPTS; on the
 *    final failure the invoice is marked uncollectible and the subscription is
 *    moved into a short grace window.
 */
final class RunBillingCycle extends Command
{
    private const MAX_ATTEMPTS = 3;

    private const RETRY_DAYS = 2;

    protected $signature = 'billing:cycle {--now= : Override "now" (ISO 8601) for testing}';

    protected $description = 'Auto-renew due subscriptions and run dunning on overdue invoices.';

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(TenantContext $tenant): int
    {
        $now = $this->option('now') ? Carbon::parse($this->option('now')) : now();

        $result = $tenant->bypass(fn () => [
            'renewed' => $this->autoRenew($now),
            'dunned' => $this->runDunning($now),
        ]);

        $this->info(sprintf(
            'Billing cycle @ %s — renewed: %d, dunning actions: %d',
            $now->toDateTimeString(),
            $result['renewed'],
            $result['dunned'],
        ));

        return self::SUCCESS;
    }

    private function autoRenew(Carbon $now): int
    {
        $due = Subscription::with('plan')
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->get();

        foreach ($due as $subscription) {
            $cycle = $subscription->plan?->billing_cycle ?? 'monthly';
            $start = $now->copy();
            $end = $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();

            $subscription->update([
                'current_period_start' => $start,
                'current_period_end' => $end,
                'dunning_attempts' => 0,
            ]);

            $this->invoices->createForSubscription($subscription);
            $this->notifications->toCompanyAdmins(
                (int) $subscription->company_id,
                'billing.renewed',
                'Subscription renewed',
                'A new invoice has been generated for your upcoming billing period.',
            );
        }

        return $due->count();
    }

    private function runDunning(Carbon $now): int
    {
        $overdue = Invoice::where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
            })
            ->get();

        $actions = 0;

        foreach ($overdue as $invoice) {
            $attempts = (int) $invoice->attempts + 1;
            $actions++;

            if ($attempts >= self::MAX_ATTEMPTS) {
                $invoice->update([
                    'status' => 'uncollectible',
                    'attempts' => $attempts,
                    'next_attempt_at' => null,
                ]);

                $this->escalateToGrace((int) $invoice->subscription_id, $now);

                $this->notifications->toCompanyAdmins(
                    (int) $invoice->company_id,
                    'billing.dunning.final',
                    'Payment failed — action required',
                    "We couldn't collect payment for invoice {$invoice->number}. Your subscription will be suspended unless payment is made.",
                    ['invoice' => $invoice->number],
                );

                continue;
            }

            $invoice->update([
                'attempts' => $attempts,
                'next_attempt_at' => $now->copy()->addDays(self::RETRY_DAYS),
            ]);

            $this->notifications->toCompanyAdmins(
                (int) $invoice->company_id,
                'billing.dunning.retry',
                'Payment reminder',
                "Invoice {$invoice->number} is overdue. We'll retry in ".self::RETRY_DAYS.' days (attempt '.$attempts.' of '.self::MAX_ATTEMPTS.').',
                ['invoice' => $invoice->number],
            );
        }

        return $actions;
    }

    private function escalateToGrace(int $subscriptionId, Carbon $now): void
    {
        $subscription = Subscription::find($subscriptionId);
        if ($subscription === null || $subscription->status !== SubscriptionStatus::Active) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Grace,
            'grace_ends_at' => $now->copy()->addDays(7),
            'dunning_attempts' => (int) $subscription->dunning_attempts + 1,
        ]);
    }
}
