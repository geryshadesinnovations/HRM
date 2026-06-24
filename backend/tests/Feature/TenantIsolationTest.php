<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Company\Models\Company;
use App\Domains\Employee\Models\Employee;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the single most important invariant: no tenant can ever see another
 * tenant's data. See docs/08-SECURITY.md.
 */
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $name): Company
    {
        return Company::create(['name' => $name, 'slug' => str()->slug($name), 'status' => 'active']);
    }

    public function test_global_scope_hides_other_tenants_rows(): void
    {
        $context = app(TenantContext::class);

        $a = $this->makeCompany('Company A');
        $b = $this->makeCompany('Company B');

        $context->forCompany($a->id, function () {
            Employee::create(['company_id' => app(TenantContext::class)->companyId(), 'employee_code' => 'A1', 'first_name' => 'Anna']);
        });
        $context->forCompany($b->id, function () {
            Employee::create(['company_id' => app(TenantContext::class)->companyId(), 'employee_code' => 'B1', 'first_name' => 'Bob']);
        });

        // Within tenant A only A's employee is visible.
        $context->setCompanyId($a->id);
        $this->assertSame(1, Employee::count());
        $this->assertSame('Anna', Employee::first()->first_name);

        // Within tenant B only B's employee is visible.
        $context->setCompanyId($b->id);
        $this->assertSame(1, Employee::count());
        $this->assertSame('Bob', Employee::first()->first_name);
    }

    public function test_company_id_is_auto_filled_on_create(): void
    {
        $context = app(TenantContext::class);
        $a = $this->makeCompany('Company A');

        $context->setCompanyId($a->id);
        $employee = Employee::create(['employee_code' => 'X1', 'first_name' => 'Zoe']);

        $this->assertSame($a->id, $employee->company_id);
    }

    public function test_fails_closed_when_no_tenant_resolved(): void
    {
        $a = $this->makeCompany('Company A');
        app(TenantContext::class)->forCompany($a->id, function () {
            Employee::create(['company_id' => app(TenantContext::class)->companyId(), 'employee_code' => 'A1', 'first_name' => 'Anna']);
        });

        // No tenant set and not bypassed => empty result set.
        app(TenantContext::class)->setCompanyId(null);
        $this->assertSame(0, Employee::count());
    }
}
