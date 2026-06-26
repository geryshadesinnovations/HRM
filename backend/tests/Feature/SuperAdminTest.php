<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Platform\Models\ContactInquiry;
use App\Domains\Subscription\Models\Plan;
use App\Models\User;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class, SuperAdminSeeder::class]);

        $this->superAdmin = User::where('is_super_admin', true)->firstOrFail();

        // Seed two companies for analytics/management.
        $reg = app(CompanyRegistrationService::class);
        $reg->register('Acme', 'Asha', 'asha@acme.test', 'password123', 'growth');
        $reg->register('Beta', 'Bimal', 'bimal@beta.test', 'password123', 'starter');
        app(TenantContext::class)->setCompanyId(null);
    }

    public function test_dashboard_returns_platform_metrics(): void
    {
        $this->actingAs($this->superAdmin, 'api');

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.companies.total', 2)
            ->assertJsonStructure(['data' => ['revenue' => ['mrr', 'arr'], 'subscriptions', 'module_usage', 'growth']]);
    }

    public function test_company_listing_and_suspend_activate(): void
    {
        $this->actingAs($this->superAdmin, 'api');

        $list = $this->getJson('/api/v1/admin/companies')->assertOk()->json('data');
        $this->assertCount(2, $list);
        $uuid = $list[0]['uuid'];

        $this->postJson("/api/v1/admin/companies/{$uuid}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/v1/admin/companies/{$uuid}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_impersonation_issues_tokens(): void
    {
        $this->actingAs($this->superAdmin, 'api');
        $uuid = $this->getJson('/api/v1/admin/companies')->json('data.0.uuid');

        $this->postJson("/api/v1/admin/companies/{$uuid}/impersonate")
            ->assertOk()
            ->assertJsonStructure(['data' => ['impersonating', 'tokens' => ['access_token', 'refresh_token']]]);
    }

    public function test_plan_management(): void
    {
        $this->actingAs($this->superAdmin, 'api');

        $this->postJson('/api/v1/admin/plans', [
            'code' => 'pro',
            'name' => 'Pro',
            'billing_cycle' => 'monthly',
            'base_price' => 500000,
            'included_seats' => 200,
            'per_seat_price' => 1500,
            'trial_days' => 14,
            'is_public' => true,
            'is_active' => true,
            'modules' => ['attendance', 'leave', 'payroll'],
        ])->assertCreated();

        $this->assertDatabaseHas('plans', ['code' => 'pro']);

        $plan = Plan::where('code', 'pro')->first();
        $this->postJson("/api/v1/admin/plans/{$plan->id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $companyAdmin = User::where('email', 'asha@acme.test')->firstOrFail();
        $this->actingAs($companyAdmin, 'api');

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_contact_inquiry_management(): void
    {
        // Public submit (no auth)
        $this->postJson('/api/v1/public/contact', [
            'name' => 'Lead',
            'email' => 'lead@x.test',
            'message' => 'Interested in a demo',
        ])->assertCreated();

        $this->assertSame(1, ContactInquiry::count());

        $this->actingAs($this->superAdmin, 'api');
        $list = $this->getJson('/api/v1/admin/contact-inquiries')->assertOk()->json('data');
        $this->assertCount(1, $list);

        $uuid = $list[0]['uuid'];
        $this->patchJson("/api/v1/admin/contact-inquiries/{$uuid}", ['status' => 'contacted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted');
    }
}
