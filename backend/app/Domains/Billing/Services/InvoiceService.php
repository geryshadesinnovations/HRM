<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Subscription\Models\Subscription;
use App\Platform\Support\Money;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds invoices for a subscription period: base plan + extra seats + add-on
 * modules, with GST applied. Invoice numbers are sequential per financial year
 * (INV-2026-000123). Money in minor units.
 *
 * See docs/04-BILLING.md.
 */
final class InvoiceService
{
    public function __construct(
        private readonly TaxCalculator $tax,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Create an `open` invoice for the subscription's current period.
     */
    public function createForSubscription(Subscription $subscription): Invoice
    {
        $plan = $subscription->plan;
        $currency = $plan?->currency ?? 'INR';

        return DB::transaction(function () use ($subscription, $plan, $currency) {
            $invoice = Invoice::create([
                'company_id' => $subscription->company_id,
                'subscription_id' => $subscription->id,
                'number' => $this->nextNumber(),
                'status' => 'open',
                'currency' => $currency,
                'subtotal' => 0,
                'tax_total' => 0,
                'total' => 0,
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
            ]);

            $subtotal = Money::of(0, $currency);

            // Base plan line.
            $basePrice = (int) ($plan?->base_price ?? 0);
            $this->addLine($invoice, $plan?->name.' plan ('.$plan?->billing_cycle.')', 1, $basePrice);
            $subtotal = $subtotal->add(Money::of($basePrice, $currency));

            // Extra seats beyond the included allowance.
            $included = (int) ($plan?->included_seats ?? 0);
            $extraSeats = max(0, (int) $subscription->seats - $included);
            if ($extraSeats > 0) {
                $perSeat = (int) ($plan?->per_seat_price ?? 0);
                $this->addLine($invoice, "Additional seats ({$extraSeats})", $extraSeats, $perSeat);
                $subtotal = $subtotal->add(Money::of($perSeat * $extraSeats, $currency));
            }

            // Add-on modules from per-tenant overrides (priced via meta if present).
            foreach (($subscription->overrides['modules']['add'] ?? []) as $moduleCode) {
                $addonPrice = (int) ($subscription->overrides['addon_prices'][$moduleCode] ?? 0);
                if ($addonPrice > 0) {
                    $this->addLine($invoice, "Add-on module: {$moduleCode}", 1, $addonPrice);
                    $subtotal = $subtotal->add(Money::of($addonPrice, $currency));
                }
            }

            $taxAmount = $this->tax->taxFor($subtotal);
            $total = $subtotal->add($taxAmount);

            $invoice->update([
                'subtotal' => $subtotal->minor,
                'tax_total' => $taxAmount->minor,
                'total' => $total->minor,
            ]);

            return $invoice->load('lines');
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return $invoice;
    }

    private function addLine(Invoice $invoice, string $description, int $quantity, int $unitAmount): void
    {
        $invoice->lines()->create([
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'tax_rate' => $this->tax->rate(),
            'line_total' => $unitAmount * $quantity,
        ]);
    }

    /**
     * Sequential per financial-year invoice number: INV-<year>-<6 digits>.
     * Uses the platform (tenant-bypassed) count so numbers are globally unique.
     */
    private function nextNumber(): string
    {
        $year = Carbon::now()->year;

        return $this->tenant->bypass(function () use ($year): string {
            $seq = Invoice::where('number', 'like', "INV-{$year}-%")->count() + 1;

            return sprintf('INV-%d-%06d', $year, $seq);
        });
    }
}
