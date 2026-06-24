<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the Subscription Engine + Feature Access Service gate modules,
 * features, and seats per the company's plan. See docs/03-SUBSCRIPTION.md.
 */
final class SubscriptionGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);
    }

    private function registerOn(string $planCode): int
    {
        $result = app(CompanyRegistrationService::class)->register(
            'Acme', 'Admin', 'admin+'.$planCode.'@acme.test', 'password123', $planCode,
        );

        return $result['company']->id;
    }

    public function test_starter_plan_grants_attendance_but_not_payroll(): void
    {
        $companyId = $this->registerOn('starter');
        app(TenantContext::class)->setCompanyId($companyId);

        $access = app(FeatureAccess::class);

        $this->assertTrue($access->canAccessModule('attendance'));
        $this->assertFalse($access->canAccessModule('payroll'));
        $this->assertFalse($access->canRunPayroll());

        $this->expectException(ApiException::class);
        $access->assert('module:payroll');
    }

    public function test_growth_plan_grants_payroll(): void
    {
        $companyId = $this->registerOn('growth');
        app(TenantContext::class)->setCompanyId($companyId);

        $access = app(FeatureAccess::class);

        $this->assertTrue($access->canAccessModule('payroll'));
        $this->assertTrue($access->canRunPayroll());
        $this->assertTrue($access->canUseFeature('payroll.bulk'));
    }

    public function test_seat_limit_is_enforced_from_plan(): void
    {
        $companyId = $this->registerOn('starter'); // 25 included seats
        app(TenantContext::class)->setCompanyId($companyId);

        $access = app(FeatureAccess::class);

        // No employees yet; adding within limit is allowed.
        $this->assertTrue($access->canAddEmployee(25));
        $this->assertFalse($access->canAddEmployee(26));
    }
}
