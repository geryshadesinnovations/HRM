<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\WebhookEvent;
use App\Domains\Billing\Services\GatewayManager;
use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Billing\Support\NormalizedEvent;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Subscription;
use App\Domains\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public, signature-verified gateway webhooks. Idempotent on (gateway, event_id).
 * Runs with no tenant context, so all tenant-scoped work is bypassed and the
 * company is derived from the target invoice.
 *
 * Flow: verify → persist raw (dedupe) → process in a transaction → 200.
 * See docs/04-BILLING.md (Webhook Flow).
 */
final class WebhookController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        $adapter = $this->gateways->forWebhook($gateway);

        if (! $adapter->verifyWebhook($request)) {
            return ApiResponse::error(ErrorCode::Unauthenticated->value, 'Invalid webhook signature.', [], 401);
        }

        $event = $adapter->parseEvent($request->all());

        // Idempotency: a duplicate (gateway, event_id) is a no-op.
        $record = WebhookEvent::firstOrNew(['gateway' => $gateway, 'event_id' => $event->eventId]);
        if ($record->exists && $record->processed_at !== null) {
            return ApiResponse::success(['status' => 'already_processed']);
        }
        $record->fill(['type' => $event->type, 'payload' => $event->raw])->save();

        $this->tenant->bypass(function () use ($event, $gateway): void {
            if (in_array($event->type, ['payment.captured', 'subscription.charged'], true)) {
                $this->applyPaymentCaptured($event, $gateway);
            } elseif ($event->type === 'payment.failed') {
                $this->applyPaymentFailed($event);
            }
        });

        $record->update(['processed_at' => now()]);

        return ApiResponse::success(['status' => 'processed']);
    }

    private function applyPaymentCaptured(NormalizedEvent $event, string $gateway): void
    {
        $invoice = $event->invoiceNumber !== null
            ? Invoice::where('number', $event->invoiceNumber)->first()
            : null;

        if ($invoice === null) {
            return;
        }

        DB::transaction(function () use ($invoice, $event, $gateway): void {
            $this->invoices->markPaid($invoice);

            Payment::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'gateway' => $gateway,
                'gateway_payment_id' => $event->gatewayPaymentId,
                'status' => 'captured',
                'amount' => $event->amountMinor ?: $invoice->total,
                'currency' => $invoice->currency,
                'raw_payload' => $event->raw,
            ]);

            // A successful payment reactivates a blocked subscription.
            $subscription = Subscription::where('company_id', $invoice->company_id)->latest('id')->first();
            if ($subscription !== null && $subscription->status->isBlocked()) {
                $this->subscriptions->reactivate($subscription);
            }
        });
    }

    private function applyPaymentFailed(NormalizedEvent $event): void
    {
        $invoice = $event->invoiceNumber !== null
            ? Invoice::where('number', $event->invoiceNumber)->first()
            : null;

        if ($invoice === null) {
            return;
        }

        Payment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'gateway' => 'manual',
            'gateway_payment_id' => $event->gatewayPaymentId,
            'status' => 'failed',
            'amount' => $event->amountMinor,
            'currency' => $invoice->currency,
            'raw_payload' => $event->raw,
        ]);

        // Enter grace so dunning can run; access continues briefly.
        $subscription = Subscription::where('company_id', $invoice->company_id)->latest('id')->first();
        if ($subscription !== null) {
            $subscription->update([
                'status' => SubscriptionStatus::Grace,
                'grace_ends_at' => now()->addDays(5),
            ]);
        }
    }
}
