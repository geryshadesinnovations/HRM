<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee CRUD + seat-limit enforcement on the active employee count.
 */
final class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);
    }

    private function bootCompany(string $plan = 'starter'): int
    {
        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', "admin@{$plan}.test", 'password123', $plan);
        $id = $result['company']->id;
        app(TenantContext::class)->setCompanyId($id);

        return $id;
    }

    public function test_employee_is_created_and_tenant_scoped(): void
    {
        $this->bootCompany();

        $employee = app(EmployeeService::class)->create([
            'employee_code' => 'E1',
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'status' => 'active',
        ]);

        $this->assertNotNull($employee->uuid);
        $this->assertSame('Asha Rao', $employee->full_name);
        $this->assertSame(1, Employee::count());
    }

    public function test_seat_limit_blocks_creation_beyond_plan(): void
    {
        $this->bootCompany('starter'); // 25 included seats
        $service = app(EmployeeService::class);

        for ($i = 1; $i <= 25; $i++) {
            $service->create(['employee_code' => "E{$i}", 'first_name' => "Emp{$i}", 'status' => 'active']);
        }

        $this->assertSame(25, Employee::count());

        $this->expectException(ApiException::class);
        $service->create(['employee_code' => 'E26', 'first_name' => 'Overflow', 'status' => 'active']);
    }

    public function test_inactive_employees_do_not_consume_a_seat(): void
    {
        $this->bootCompany('starter');
        $service = app(EmployeeService::class);

        // Fill all 25 seats with active employees.
        for ($i = 1; $i <= 25; $i++) {
            $service->create(['employee_code' => "E{$i}", 'first_name' => "Emp{$i}", 'status' => 'active']);
        }

        // A terminated employee can still be added (does not consume a seat).
        $terminated = $service->create(['employee_code' => 'T1', 'first_name' => 'Past', 'status' => 'terminated']);
        $this->assertSame('terminated', $terminated->status);
        $this->assertSame(26, Employee::count());
    }
}
