<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Employee\Models\Employee;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Leave\Services\LeaveService;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private LeaveType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);

        // Growth plan licenses both leave and attendance (for integration test).
        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', 'admin@acme.test', 'password123', 'growth');
        app(TenantContext::class)->setCompanyId($result['company']->id);

        $this->employee = Employee::create(['employee_code' => 'E1', 'first_name' => 'Asha', 'status' => 'active']);
        $this->type = LeaveType::create([
            'code' => 'casual', 'name' => 'Casual Leave', 'is_paid' => true, 'annual_quota' => 12,
        ]);
    }

    public function test_request_then_approve_deducts_balance_and_marks_attendance(): void
    {
        $service = app(LeaveService::class);

        $request = $service->request($this->employee, $this->type, '2026-06-01', '2026-06-03'); // 3 days
        $this->assertSame(3.0, $request->days);
        $this->assertSame('pending', $request->status);

        $service->approve($request, null, 'ok');

        $balance = $service->balanceFor($this->employee, $this->type, 2026);
        $this->assertSame(12.0, (float) $balance->allocated);
        $this->assertSame(3.0, (float) $balance->used);
        $this->assertSame(9.0, $balance->remaining);

        // Attendance integration: 3 dates marked as 'leave'.
        $this->assertSame(3, AttendanceRecord::where('status', 'leave')->count());
    }

    public function test_request_rejected_when_balance_insufficient(): void
    {
        $service = app(LeaveService::class);

        // Quota is 12; request 20 days.
        $this->expectException(ApiException::class);
        $service->request($this->employee, $this->type, '2026-06-01', '2026-06-20');
    }

    public function test_cannot_approve_an_already_decided_request(): void
    {
        $service = app(LeaveService::class);
        $request = $service->request($this->employee, $this->type, '2026-07-01', '2026-07-01');
        $service->approve($request, null);

        $this->expectException(ApiException::class);
        $service->approve($request, null);
    }
}
