<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\GatewayManager;
use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Domains\Subscription\Services\SubscriptionEngine;
use App\Domains\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Platform\Exceptions\ApiException;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use App\Platform\Support\Money;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company-facing subscription + billing. Requires `company.subscription.manage`
 * / `company.billing.manage`. See docs/07-API.md (Subscription & Billing).
 */
final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
        private readonly InvoiceService $invoices,
        private readonly TenantContext $tenant,
    ) {}

    public function show(SubscriptionEngine $engine): JsonResponse
    {
        $subscription = $this->currentSubscription();

        return ApiResponse::success([
            'subscription' => [
                'uuid' => $subscription->uuid,
                'status' => $subscription->status->value,
                'seats' => $subscription->seats,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'auto_renew' => $subscription->auto_renew,
            ],
            'plan' => $subscription->plan?->only(['code', 'name', 'base_price', 'included_seats', 'per_seat_price']),
            'entitlement' => $engine->forCurrentTenant()->toArray(),
        ]);
    }

    public function plans(): JsonResponse
    {
        return ApiResponse::success(
            $this->tenant->bypass(fn () => Plan::where('is_active', true)->where('is_public', true)
                ->orderBy('base_price')
                ->get(['code', 'name', 'billing_cycle', 'base_price', 'included_seats', 'per_seat_price', 'trial_days'])),
        );
    }

    public function upgrade(Request $request): JsonResponse
    {
        $plan = $this->resolvePlan($request);
        $subscription = $this->service->upgrade($this->currentSubscription(), $plan);
        $invoice = $this->invoices->createForSubscription($subscription);

        return ApiResponse::success(['subscription' => $subscription->only(['uuid', 'status', 'seats']), 'invoice' => $invoice]);
    }

    public function downgrade(Request $request): JsonResponse
    {
        $plan = $this->resolvePlan($request);
        $subscription = $this->service->downgrade($this->currentSubscription(), $plan);

        return ApiResponse::success($subscription->only(['uuid', 'status', 'overrides']));
    }

    public function cancel(): JsonResponse
    {
        return ApiResponse::success($this->service->cancel($this->currentSubscription())->only(['uuid', 'status', 'cancelled_at']));
    }

    public function reactivate(): JsonResponse
    {
        $subscription = $this->service->reactivate($this->currentSubscription());
        $invoice = $this->invoices->createForSubscription($subscription);

        return ApiResponse::success(['subscription' => $subscription->only(['uuid', 'status']), 'invoice' => $invoice]);
    }

    public function invoices(): JsonResponse
    {
        return ApiResponse::success(
            Invoice::orderByDesc('id')->get(['uuid', 'number', 'status', 'subtotal', 'tax_total', 'total', 'currency', 'issued_at', 'due_at', 'paid_at']),
        );
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return ApiResponse::success($invoice->load('lines'));
    }

    /** Create a gateway order for an open invoice (hosted checkout). */
    public function checkout(Request $request, GatewayManager $gateways): JsonResponse
    {
        $data = $request->validate(['invoice' => ['required', 'string']]);

        $invoice = Invoice::where('uuid', $data['invoice'])->firstOrFail();

        if ($invoice->status !== 'open') {
            throw new ApiException(ErrorCode::ValidationFailed, 'Invoice is not open for payment.', ['status' => $invoice->status], 422);
        }

        $order = $gateways->gateway()->createOrder(
            Money::of($invoice->total, $invoice->currency),
            ['invoice_number' => $invoice->number, 'company_id' => $invoice->company_id],
        );

        return ApiResponse::success(['order' => $order, 'invoice' => $invoice->only(['uuid', 'number', 'total', 'currency'])]);
    }

    private function currentSubscription(): Subscription
    {
        $subscription = Subscription::with('plan')->latest('id')->first();

        if ($subscription === null) {
            throw new ApiException(ErrorCode::NotFound, 'No subscription found for this company.', [], 404);
        }

        return $subscription;
    }

    private function resolvePlan(Request $request): Plan
    {
        $data = $request->validate(['plan_code' => ['required', 'string', 'exists:plans,code']]);

        return $this->tenant->bypass(fn () => Plan::where('code', $data['plan_code'])->where('is_active', true)->firstOrFail());
    }
}
