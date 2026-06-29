<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Billing\Models\Coupon;
use App\Domains\Company\Models\Company;
use App\Domains\Employee\Models\Department;
use App\Domains\Employee\Models\Designation;
use App\Domains\Employee\Models\Employee;
use App\Domains\Payroll\Models\SalaryComponent;
use App\Domains\Payroll\Models\SalaryStructure;
use App\Domains\Payroll\Models\SalaryStructureLine;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Models\User;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a ready-to-explore demo tenant with logins for every role, an active
 * subscription, employees, salary structures, and statutory settings.
 *
 * Credentials (all password: "password"):
 *   - Company Admin : admin@demo.test
 *   - HR Manager    : hr@demo.test
 *   - Manager       : manager@demo.test
 *   - Employee      : employee@demo.test
 *
 * Idempotent — safe to run repeatedly. See docs site → Test Credentials.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['slug' => 'demo-co'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo Company Pvt Ltd',
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'country' => 'IN',
                'gstin' => '29ABCDE1234F1Z5',
                'phone' => '+91 80 4000 0000',
                'industry' => 'Information Technology',
                'employees_estimate' => 50,
                'address' => '4th Floor, Tech Park, Bengaluru 560103',
            ],
        );

        $this->seedSubscription($company);

        // A demo coupon any company can try at checkout.
        Coupon::firstOrCreate(
            ['code' => 'WELCOME20'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => '20% off your first invoice',
                'type' => 'percent',
                'value' => 20,
                'currency' => 'INR',
                'is_active' => true,
            ],
        );

        app(TenantContext::class)->forCompany((int) $company->id, function () use ($company): void {
            $this->seedTenant($company);
        });
    }

    private function seedSubscription(Company $company): void
    {
        $plan = Plan::where('code', 'growth')->first() ?? Plan::first();
        if ($plan === null) {
            return;
        }

        app(TenantContext::class)->forCompany((int) $company->id, function () use ($company, $plan): void {
            Subscription::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'seats' => max(50, (int) $plan->included_seats),
                    'trial_ends_at' => now()->subDays(2),
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->endOfMonth(),
                    'auto_renew' => true,
                    'overrides' => [],
                ],
            );
        });
    }

    private function seedTenant(Company $company): void
    {
        // --- Users / logins (one per role) ---
        $admin = $this->user($company, 'Aarav Sharma', 'admin@demo.test', 'Company Admin');
        $hr = $this->user($company, 'Priya Nair', 'hr@demo.test', 'HR Manager');
        $managerUser = $this->user($company, 'Rohan Gupta', 'manager@demo.test', 'Manager');
        $employeeUser = $this->user($company, 'Sneha Patil', 'employee@demo.test', 'Employee');

        // --- Departments & designations ---
        $eng = Department::firstOrCreate(['name' => 'Engineering']);
        $hrDept = Department::firstOrCreate(['name' => 'Human Resources']);

        $seniorEng = Designation::firstOrCreate(['name' => 'Senior Engineer'], ['department_id' => $eng->id]);
        $engManager = Designation::firstOrCreate(['name' => 'Engineering Manager'], ['department_id' => $eng->id]);
        $hrManagerDesig = Designation::firstOrCreate(['name' => 'HR Manager'], ['department_id' => $hrDept->id]);

        // --- Employees (link the manager + employee logins) ---
        $manager = $this->employee($company, 'EMP-0001', 'Rohan', 'Gupta', $eng, $engManager, null, $managerUser->id);
        $hrEmp = $this->employee($company, 'EMP-0002', 'Priya', 'Nair', $hrDept, $hrManagerDesig, $manager, $hr->id);
        $employee = $this->employee($company, 'EMP-0003', 'Sneha', 'Patil', $eng, $seniorEng, $manager, $employeeUser->id);

        // A few extra unlinked employees so lists/reports look real.
        $this->employee($company, 'EMP-0004', 'Vikram', 'Rao', $eng, $seniorEng, $manager, null);
        $this->employee($company, 'EMP-0005', 'Ananya', 'Iyer', $eng, $seniorEng, $manager, null);

        // --- Salary components & structures (for payroll demo) ---
        $this->seedPayroll($company);
    }

    private function user(Company $company, string $name, string $email, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'company_id' => $company->id,
                'is_super_admin' => false,
                'password' => Hash::make('password'),
            ],
        );

        $user->syncRoles([$role]);

        return $user;
    }

    private function employee(
        Company $company,
        string $code,
        string $first,
        string $last,
        Department $dept,
        Designation $desig,
        ?Employee $manager,
        ?int $userId,
    ): Employee {
        return Employee::firstOrCreate(
            ['employee_code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'.'.strtolower($last).'@demo.test',
                'department_id' => $dept->id,
                'designation_id' => $desig->id,
                'manager_id' => $manager?->id,
                'date_of_joining' => Carbon::now()->subMonths(14)->toDateString(),
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'Bengaluru HQ',
                'gender' => 'unspecified',
            ],
        );
    }

    private function seedPayroll(Company $company): void
    {
        $basic = SalaryComponent::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic', 'type' => 'earning', 'is_taxable' => true]);
        $hra = SalaryComponent::firstOrCreate(['code' => 'HRA'], ['name' => 'House Rent Allowance', 'type' => 'earning', 'is_taxable' => true]);
        $special = SalaryComponent::firstOrCreate(['code' => 'SPECIAL'], ['name' => 'Special Allowance', 'type' => 'earning', 'is_taxable' => true]);
        $pt = SalaryComponent::firstOrCreate(['code' => 'PT'], ['name' => 'Professional Tax', 'type' => 'deduction', 'is_taxable' => false]);

        // Give every active employee a simple structure (amounts in paise).
        foreach (Employee::where('status', 'active')->get() as $emp) {
            $structure = SalaryStructure::firstOrCreate(
                ['employee_id' => $emp->id],
                ['ctc_monthly' => 6000000],
            );

            $lines = [
                [$basic->id, 3000000],
                [$hra->id, 1500000],
                [$special->id, 1300000],
                [$pt->id, 20000],
            ];
            foreach ($lines as [$componentId, $amount]) {
                SalaryStructureLine::firstOrCreate(
                    ['salary_structure_id' => $structure->id, 'salary_component_id' => $componentId],
                    ['amount' => $amount],
                );
            }
        }
    }
}
