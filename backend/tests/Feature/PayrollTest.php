<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Payroll\Models\Payslip;
use App\Domains\Payroll\Models\SalaryComponent;
use App\Domains\Payroll\Models\SalaryStructure;
use App\Domains\Payroll\Services\PayrollService;
use App\Platform\Exceptions\ApiException;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PayrollTest extends TestCase
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

        // Earnings: Basic 50,000 + HRA 20,000 = gross 70,000 (in paise). Deduction: PF 6,000.
        $basic = SalaryComponent::create(['code' => 'BASIC', 'name' => 'Basic', 'type' => 'earning']);
        $hra = SalaryComponent::create(['code' => 'HRA', 'name' => 'HRA', 'type' => 'earning']);
        $pf = SalaryComponent::create(['code' => 'PF', 'name' => 'Provident Fund', 'type' => 'deduction']);

        $structure = SalaryStructure::create(['employee_id' => $this->employee->id, 'ctc_monthly' => 7000000]);
        $structure->lines()->createMany([
            ['salary_component_id' => $basic->id, 'amount' => 5000000],
            ['salary_component_id' => $hra->id, 'amount' => 2000000],
            ['salary_component_id' => $pf->id, 'amount' => 600000],
        ]);
    }

    public function test_payroll_run_computes_gross_deductions_and_net(): void
    {
        $service = app(PayrollService::class);

        $run = $service->createRun(2026, 6); // June 2026, 30 days, no absences
        $service->process($run);

        $slip = Payslip::where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertSame(7000000, $slip->gross);
        $this->assertSame(600000, $slip->deductions);     // PF only; no LOP
        $this->assertSame(6400000, $slip->net);
        $this->assertSame(0.0, $slip->lop_days);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(6400000, $run->total_net);
    }

    public function test_loss_of_pay_is_prorated_from_absences(): void
    {
        // Mark 3 absent days in June 2026.
        $attendance = app(AttendanceService::class);
        foreach (['2026-06-10', '2026-06-11', '2026-06-12'] as $day) {
            $attendance->mark($this->employee, $day, 'absent');
        }

        $service = app(PayrollService::class);
        $run = $service->process($service->createRun(2026, 6));

        $slip = Payslip::where('employee_id', $this->employee->id)->firstOrFail();

        // LOP = round(7,000,000 * 3 / 30) = 700,000 paise.
        $this->assertSame(3.0, $slip->lop_days);
        $this->assertSame(600000 + 700000, $slip->deductions);
        $this->assertSame(7000000 - 1300000, $slip->net);
    }

    public function test_locked_run_cannot_be_reprocessed(): void
    {
        $service = app(PayrollService::class);
        $run = $service->process($service->createRun(2026, 5));
        $service->publish($run);

        $this->expectException(ApiException::class);
        $service->process($run);
    }
}
