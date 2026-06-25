<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Company\Models\Company;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the subscriptions:sweep command's date-driven state transitions.
 */
final class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class]);
    }

    private function makeSubscription(array $attrs): Subscription
    {
        $plan = Plan::where('code', 'growth')->firstOrFail();

        // Created outside any tenant scope (platform op) via bypass.
        return app(TenantContext::class)->bypass(fn () => Subscription::create(array_merge([
            'company_id' => Company::create([
                'name' => 'C'.uniqid(), 'slug' => 's'.uniqid(), 'status' => 'active',
            ])->id,
            'plan_id' => $plan->id,
            'seats' => 100,
            'overrides' => [],
        ], $attrs)));
    }

    public function test_expired_trial_active_to_grace_and_grace_to_expired(): void
    {
        $now = '2026-06-25 12:00:00';

        // Trial that ended yesterday, no future period → should expire.
        $trial = $this->makeSubscription([
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => '2026-06-24 12:00:00',
            'current_period_end' => '2026-06-24 12:00:00',
        ]);

        // Active whose period ended → should enter grace.
        $active = $this->makeSubscription([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => '2026-06-20 12:00:00',
        ]);

        // Grace whose grace window ended → should expire.
        $grace = $this->makeSubscription([
            'status' => SubscriptionStatus::Grace,
            'grace_ends_at' => '2026-06-22 12:00:00',
        ]);

        $this->artisan('subscriptions:sweep', ['--now' => $now])->assertSuccessful();

        app(TenantContext::class)->bypass(function () use ($trial, $active, $grace): void {
            $this->assertSame(SubscriptionStatus::Expired, $trial->refresh()->status);
            $this->assertSame(SubscriptionStatus::Grace, $active->refresh()->status);
            $this->assertNotNull($active->refresh()->grace_ends_at);
            $this->assertSame(SubscriptionStatus::Expired, $grace->refresh()->status);
        });
    }

    public function test_sweep_is_idempotent(): void
    {
        $now = '2026-06-25 12:00:00';
        $active = $this->makeSubscription([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => '2026-06-20 12:00:00',
        ]);

        $this->artisan('subscriptions:sweep', ['--now' => $now])->assertSuccessful();
        $this->artisan('subscriptions:sweep', ['--now' => $now])->assertSuccessful();

        // Still in grace (not double-advanced) and grace window unchanged-ish.
        $this->assertSame(SubscriptionStatus::Grace, app(TenantContext::class)->bypass(fn () => $active->refresh()->status));
    }
}
