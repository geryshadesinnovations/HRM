<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Reporting\Services\ReportService;
use App\Models\User;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);

        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', 'admin@acme.test', 'password123', 'growth');
        $this->admin = $result['user'];
        app(TenantContext::class)->setCompanyId($result['company']->id);

        $this->employee = Employee::create(['employee_code' => 'E1', 'first_name' => 'Asha', 'last_name' => 'Rao', 'status' => 'active']);
    }

    public function test_attendance_summary_aggregates_statuses(): void
    {
        $att = app(AttendanceService::class);
        $att->mark($this->employee, '2026-06-01', 'present');
        $att->mark($this->employee, '2026-06-02', 'present');
        $att->mark($this->employee, '2026-06-03', 'absent');

        $rows = app(ReportService::class)->attendanceSummary('2026-06-01', '2026-06-30');

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['present']);
        $this->assertSame(1, $rows[0]['absent']);
    }

    public function test_employee_directory_report(): void
    {
        $rows = app(ReportService::class)->employeeDirectory();
        $this->assertCount(1, $rows);
        $this->assertSame('E1', $rows[0]['employee_code']);
        $this->assertSame('Asha Rao', $rows[0]['name']);
    }

    public function test_report_endpoint_returns_json_and_csv(): void
    {
        $this->actingAs($this->admin, 'api');

        $this->getJson('/api/v1/reports/employees')
            ->assertOk()
            ->assertJsonPath('meta.count', 1);

        $csv = $this->get('/api/v1/reports/employees?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('E1', $csv->streamedContent());
    }
}
