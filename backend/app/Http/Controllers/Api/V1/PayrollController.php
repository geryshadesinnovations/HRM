<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\Payslip;
use App\Domains\Payroll\Models\SalaryComponent;
use App\Domains\Payroll\Models\SalaryStructure;
use App\Domains\Payroll\Services\PayrollService;
use App\Domains\Payroll\Services\StatutoryCalculator;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Payroll API. Gated by `module:payroll`. See docs/07-API.md (Payroll).
 */
final class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    // --- Statutory settings (PF / ESI / TDS) ---

    /** Read the tenant's statutory configuration. */
    public function settings(StatutoryCalculator $calc): JsonResponse
    {
        return ApiResponse::success($calc->settings());
    }

    /** Update the tenant's statutory configuration. */
    public function updateSettings(Request $request, StatutoryCalculator $calc): JsonResponse
    {
        $data = $request->validate([
            'pf_enabled' => ['boolean'],
            'pf_employee_rate' => ['numeric', 'between:0,100'],
            'pf_employer_rate' => ['numeric', 'between:0,100'],
            'pf_wage_ceiling' => ['integer', 'min:0'],
            'esi_enabled' => ['boolean'],
            'esi_employee_rate' => ['numeric', 'between:0,100'],
            'esi_employer_rate' => ['numeric', 'between:0,100'],
            'esi_wage_ceiling' => ['integer', 'min:0'],
            'tds_enabled' => ['boolean'],
            'tds_regime' => [Rule::in(['new', 'old'])],
            'tds_standard_deduction' => ['integer', 'min:0'],
        ]);

        $settings = $calc->settings();
        $settings->update($data);

        return ApiResponse::success($settings->refresh());
    }

    // --- Salary components ---

    public function components(): JsonResponse
    {
        return ApiResponse::success(SalaryComponent::orderBy('type')->orderBy('name')->get());
    }

    public function storeComponent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(SalaryComponent::TYPES)],
            'is_taxable' => ['boolean'],
        ]);

        return ApiResponse::success(SalaryComponent::create($data), status: 201);
    }

    // --- Salary structures ---

    public function showStructure(Employee $employee): JsonResponse
    {
        $structure = SalaryStructure::with('lines.component')
            ->where('employee_id', $employee->id)
            ->first();

        return ApiResponse::success($structure);
    }

    public function setStructure(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'ctc_monthly' => ['nullable', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_code' => ['required', 'string', Rule::exists('salary_components', 'code')],
            'lines.*.amount' => ['required', 'integer', 'min:0'],
        ]);

        $structure = DB::transaction(function () use ($employee, $data) {
            $structure = SalaryStructure::updateOrCreate(
                ['employee_id' => $employee->id],
                ['ctc_monthly' => $data['ctc_monthly'] ?? 0],
            );

            $structure->lines()->delete();

            foreach ($data['lines'] as $line) {
                $component = SalaryComponent::where('code', $line['component_code'])->first();
                if ($component !== null) {
                    $structure->lines()->create([
                        'salary_component_id' => $component->id,
                        'amount' => $line['amount'],
                    ]);
                }
            }

            return $structure->load('lines.component');
        });

        return ApiResponse::success($structure);
    }

    // --- Runs ---

    public function createRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'mode' => ['nullable', Rule::in(PayrollRun::MODES)],
        ]);

        $run = $this->service->createRun($data['year'], $data['month'], $data['mode'] ?? 'attendance_payroll');

        return ApiResponse::success($run, status: 201);
    }

    public function process(PayrollRun $run): JsonResponse
    {
        return ApiResponse::success($this->service->process($run));
    }

    public function publish(PayrollRun $run): JsonResponse
    {
        return ApiResponse::success($this->service->publish($run));
    }

    /** Reopen a locked run (unlocks the attendance period). */
    public function reopen(Request $request, PayrollRun $run): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return ApiResponse::success($this->service->reopen($run, $request->user()?->getKey(), $note));
    }

    /** List the audit trail of adjustments for a run. */
    public function adjustments(PayrollRun $run): JsonResponse
    {
        return ApiResponse::success(
            $run->adjustments()->with('employee:id,uuid,first_name,last_name')->orderByDesc('id')->get(),
        );
    }

    /** Record a bonus / incentive / penalty / other adjustment for a run. */
    public function storeAdjustment(Request $request, PayrollRun $run): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['bonus', 'incentive', 'penalty', 'other'])],
            'label' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'integer'],
            'employee' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($data['employee'])) {
            $data['employee_id'] = Employee::where('uuid', $data['employee'])->value('id');
        }

        $adjustment = $this->service->addAdjustment($run, $data, $request->user()?->getKey());

        return ApiResponse::success($adjustment, status: 201);
    }

    public function payslips(PayrollRun $run): JsonResponse
    {
        return ApiResponse::success(
            $run->payslips()->with('employee:id,uuid,first_name,last_name')->get(),
        );
    }

    public function showPayslip(Payslip $payslip): JsonResponse
    {
        return ApiResponse::success(
            $payslip->load(['employee:id,uuid,first_name,last_name', 'payrollRun:id,uuid,period_year,period_month']),
        );
    }
}
