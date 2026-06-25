<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Domains\Subscription\Services\SubscriptionService;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);

        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', 'admin@acme.test', 'password123', 'growth');
        app(TenantContext::class)->setCompanyId($result['company']->id);
        $this->subscription = $result['subscription']->load('plan');
    }

    public function test_invoice_is_built_with_lines_and_gst(): void
    {
        $invoice = app(InvoiceService::class)->createForSubscription($this->subscription);

        // Growth base 2,99,900 paise; 100 seats = included, so no extra-seat line.
        $this->assertSame(299900, $invoice->subtotal);
        $this->assertSame(53982, $invoice->tax_total); // round(299900 * 18 / 100)
        $this->assertSame(353882, $invoice->total);
        $this->assertSame('open', $invoice->status);
        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_upgrade_applies_immediately(): void
    {
        // Start on starter so payroll is NOT licensed.
        $result = app(CompanyRegistrationService::class)->register('Beta', 'Admin', 'admin@beta.test', 'password123', 'starter');
        app(TenantContext::class)->setCompanyId($result['company']->id);
        $sub = $result['subscription']->load('plan');

        $this->assertFalse(app(FeatureAccess::class)->canAccessModule('payroll'));

        $growth = app(TenantContext::class)->bypass(fn () => Plan::where('code', 'growth')->first());
        app(SubscriptionService::class)->upgrade($sub, $growth);

        $this->assertTrue(app(FeatureAccess::class)->canAccessModule('payroll'));
    }

    public function test_cancel_then_reactivate(): void
    {
        $service = app(SubscriptionService::class);

        $cancelled = $service->cancel($this->subscription);
        $this->assertSame(SubscriptionStatus::Cancelled, $cancelled->status);
        $this->assertFalse($cancelled->auto_renew);

        $reactivated = $service->reactivate($cancelled);
        $this->assertSame(SubscriptionStatus::Active, $reactivated->status);
        $this->assertTrue($reactivated->auto_renew);
    }

    public function test_webhook_marks_invoice_paid_and_is_idempotent(): void
    {
        $invoice = app(InvoiceService::class)->createForSubscription($this->subscription);

        // Simulate a blocked subscription that a successful payment should restore.
        $this->subscription->update(['status' => SubscriptionStatus::Expired]);

        $payload = [
            'event_id' => 'evt_123',
            'type' => 'payment.captured',
            'payment_id' => 'pay_abc',
            'amount' => $invoice->total,
            'invoice_number' => $invoice->number,
        ];

        $first = $this->postJson('/api/v1/webhooks/manual', $payload);
        $first->assertOk()->assertJsonPath('data.status', 'processed');

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(1, Payment::where('status', 'captured')->count());
        $this->assertSame(SubscriptionStatus::Active, $this->subscription->refresh()->status);

        // Replaying the same event is a no-op.
        $second = $this->postJson('/api/v1/webhooks/manual', $payload);
        $second->assertOk()->assertJsonPath('data.status', 'already_processed');
        $this->assertSame(1, Payment::where('status', 'captured')->count());
    }
}
