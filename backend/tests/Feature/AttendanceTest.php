<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);

        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', 'admin@acme.test', 'password123', 'growth');
        app(TenantContext::class)->setCompanyId($result['company']->id);

        $this->employee = Employee::create(['employee_code' => 'E1', 'first_name' => 'Asha', 'status' => 'active']);
    }

    public function test_hr_can_mark_attendance_idempotently(): void
    {
        $service = app(AttendanceService::class);

        $service->mark($this->employee, '2026-06-01', 'present');
        $service->mark($this->employee, '2026-06-01', 'absent'); // same date overwrites

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame('absent', AttendanceRecord::first()->status);
    }

    public function test_self_check_in_and_out_computes_worked_and_overtime(): void
    {
        $service = app(AttendanceService::class);

        $in = Carbon::parse('2026-06-02 09:00:00');
        $out = Carbon::parse('2026-06-02 19:00:00'); // 10 hours = 600 minutes

        $service->checkIn($this->employee, $in);
        $record = $service->checkOut($this->employee, $out);

        $this->assertSame(600, $record->worked_minutes);
        $this->assertSame(120, $record->overtime_minutes); // 600 - 480
        $this->assertSame('present', $record->status);
    }

    public function test_double_check_in_is_rejected(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::parse('2026-06-03 09:00:00'));

        $this->expectException(ApiException::class);
        $service->checkIn($this->employee, Carbon::parse('2026-06-03 09:05:00'));
    }
}
