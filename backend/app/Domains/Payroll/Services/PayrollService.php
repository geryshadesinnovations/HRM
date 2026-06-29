<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Notification\Services\NotificationService;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\Payslip;
use App\Domains\Payroll\Models\SalaryStructure;
use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll engine. Creates a run per (company, year, month) and computes a
 * payslip per employee from their salary structure, prorated for loss-of-pay
 * (LOP) days when the Attendance module is licensed.
 *
 * All money is integer minor units (paise); no floats in money math.
 * See docs/06-MODULES.md (Payroll Module).
 */
final class PayrollService
{
    public function __construct(
        private readonly FeatureAccess $access,
        private readonly NotificationService $notifications,
    ) {}

    public function createRun(int $year, int $month, string $mode = 'attendance_payroll'): PayrollRun
    {
        // Module access is asserted at the route; assert again defensively.
        $this->access->assert('payroll');

        if ($month < 1 || $month > 12) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Month must be 1-12.', [], 422);
        }
        if (! in_array($mode, PayrollRun::MODES, true)) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Invalid payroll mode.', ['mode' => PayrollRun::MODES], 422);
        }

        $existing = PayrollRun::where('period_year', $year)->where('period_month', $month)->first();
        if ($existing !== null) {
            return $existing;
        }

        return PayrollRun::create([
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'draft',
            'mode' => $mode,
        ]);
    }

    /**
     * Compute payslips for every active employee with a salary structure.
     * Idempotent: re-processing a draft/completed run recomputes payslips.
     */
    public function process(PayrollRun $run): PayrollRun
    {
        if ($run->status === 'locked') {
            throw new ApiException(ErrorCode::ValidationFailed, 'A locked payroll run cannot be reprocessed.', [], 422);
        }

        $daysInMonth = Carbon::create($run->period_year, $run->period_month, 1)->daysInMonth;
        // Attendance only drives loss-of-pay in "attendance_payroll" mode.
        $attendanceLicensed = $run->mode === 'attendance_payroll'
            && $this->access->canAccessModule('attendance')
            && Schema::hasTable('attendance_records');

        return DB::transaction(function () use ($run, $daysInMonth, $attendanceLicensed) {
            $run->update(['status' => 'processing']);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            $structures = SalaryStructure::with('lines.component')
                ->whereHas('employee', fn ($q) => $q->where('status', 'active'))
                ->get();

            // Monetary adjustments (bonus/incentive/penalty/other) per employee.
            $adjustmentsByEmployee = $run->adjustments()
                ->whereIn('type', ['bonus', 'incentive', 'penalty', 'other'])
                ->whereNotNull('employee_id')
                ->get()
                ->groupBy('employee_id');

            foreach ($structures as $structure) {
                $slip = $this->computePayslip($structure, $run, $daysInMonth, $attendanceLicensed);

                // Apply ad-hoc adjustments (positive = earning, negative = deduction).
                $adjLines = [];
                $adjTotal = 0;
                foreach ($adjustmentsByEmployee->get($structure->employee_id, collect()) as $adj) {
                    $adjTotal += (int) $adj->amount;
                    $adjLines[] = ['code' => strtoupper($adj->type), 'name' => $adj->label, 'amount' => (int) $adj->amount];
                }
                if ($adjLines !== []) {
                    $slip['net'] += $adjTotal;
                    $slip['breakdown']['adjustments'] = $adjLines;
                }

                $totalGross += $slip['gross'];
                $totalDeductions += $slip['deductions'];
                $totalNet += $slip['net'];

                Payslip::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'employee_id' => $structure->employee_id],
                    [
                        'gross' => $slip['gross'],
                        'deductions' => $slip['deductions'],
                        'net' => $slip['net'],
                        'worked_days' => $slip['worked_days'],
                        'lop_days' => $slip['lop_days'],
                        'breakdown' => $slip['breakdown'],
                    ],
                );
            }

            $run->update([
                'status' => 'completed',
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
                'processed_at' => now(),
            ]);

            return $run->refresh();
        });
    }

    public function publish(PayrollRun $run): PayrollRun
    {
        if ($run->status !== 'completed') {
            throw new ApiException(ErrorCode::ValidationFailed, 'Only a completed run can be published/locked.', [], 422);
        }

        DB::transaction(function () use ($run): void {
            $run->update(['status' => 'locked', 'locked_at' => now()]);

            // Lock the attendance period so finalized days can't be edited (req #7).
            $start = Carbon::create($run->period_year, $run->period_month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            if (Schema::hasTable('attendance_records')) {
                DB::table('attendance_records')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->update(['locked' => true, 'updated_at' => now()]);
            }

            $this->audit($run, 'lock', 'Payroll period locked', null, null);
        });

        // Notify each employee that their payslip is available.
        $period = Carbon::create($run->period_year, $run->period_month, 1)->format('F Y');
        $run->payslips()->with('employee:id,user_id,company_id')->get()->each(function (Payslip $slip) use ($period, $run): void {
            $this->notifications->toUser(
                $slip->employee?->user_id,
                (int) $run->company_id,
                'payroll.payslip',
                'Payslip available',
                "Your payslip for {$period} is ready to view.",
                ['payslip_uuid' => $slip->uuid],
            );
        });

        return $run->refresh();
    }

    /**
     * Reopen a locked run (req #7): unlock the attendance period and return the
     * run to "completed" so it can be recomputed. Audited. Gated by
     * `payroll.run.reopen` at the route.
     */
    public function reopen(PayrollRun $run, ?int $userId, ?string $note): PayrollRun
    {
        if ($run->status !== 'locked') {
            throw new ApiException(ErrorCode::ValidationFailed, 'Only a locked run can be reopened.', [], 422);
        }

        DB::transaction(function () use ($run, $userId, $note): void {
            $run->update([
                'status' => 'completed',
                'reopened_at' => now(),
                'reopened_by' => $userId,
            ]);

            $start = Carbon::create($run->period_year, $run->period_month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            if (Schema::hasTable('attendance_records')) {
                DB::table('attendance_records')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->update(['locked' => false, 'updated_at' => now()]);
            }

            $this->audit($run, 'reopen', 'Payroll period reopened', null, $note, $userId);
        });

        return $run->refresh();
    }

    /**
     * Record a payroll adjustment (bonus/incentive/penalty/other) for a run,
     * optionally targeting one employee. Recompute the run to apply it.
     *
     * @param  array<string,mixed>  $data
     */
    public function addAdjustment(PayrollRun $run, array $data, ?int $userId): PayrollAdjustment
    {
        if ($run->status === 'locked') {
            throw new ApiException(ErrorCode::ValidationFailed, 'Reopen the run before adding adjustments.', [], 422);
        }

        return $this->audit(
            $run,
            $data['type'],
            $data['label'],
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['note'] ?? null,
            $userId,
            (int) ($data['amount'] ?? 0),
        );
    }

    private function audit(PayrollRun $run, string $type, string $label, ?int $employeeId, ?string $note, ?int $userId = null, int $amount = 0): PayrollAdjustment
    {
        return PayrollAdjustment::create([
            'company_id' => $run->company_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employeeId,
            'type' => $type,
            'label' => $label,
            'amount' => $amount,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    /**
     * @return array{gross:int,deductions:int,net:int,worked_days:float,lop_days:float,breakdown:array}
     */
    private function computePayslip(SalaryStructure $structure, PayrollRun $run, int $daysInMonth, bool $attendanceLicensed): array
    {
        $earnings = [];
        $deductions = [];
        $grossFull = 0;
        $deductionTotal = 0;

        foreach ($structure->lines as $line) {
            $component = $line->component;
            if ($component === null) {
                continue;
            }

            if ($component->isEarning()) {
                $earnings[] = ['code' => $component->code, 'name' => $component->name, 'amount' => $line->amount];
                $grossFull += $line->amount;
            } else {
                $deductions[] = ['code' => $component->code, 'name' => $component->name, 'amount' => $line->amount];
                $deductionTotal += $line->amount;
            }
        }

        // Loss-of-pay proration from attendance (absent days), if licensed.
        $lopDays = $attendanceLicensed ? $this->lopDays($structure->employee_id, $run) : 0.0;
        $lopDeduction = $daysInMonth > 0 ? (int) round($grossFull * $lopDays / $daysInMonth) : 0;

        if ($lopDeduction > 0) {
            $deductions[] = ['code' => 'LOP', 'name' => 'Loss of Pay', 'amount' => $lopDeduction];
            $deductionTotal += $lopDeduction;
        }

        $workedDays = max(0.0, $daysInMonth - $lopDays);
        $net = $grossFull - $deductionTotal;

        return [
            'gross' => $grossFull,
            'deductions' => $deductionTotal,
            'net' => $net,
            'worked_days' => $workedDays,
            'lop_days' => $lopDays,
            'breakdown' => [
                'earnings' => $earnings,
                'deductions' => $deductions,
                'days_in_month' => $daysInMonth,
            ],
        ];
    }

    private function lopDays(int $employeeId, PayrollRun $run): float
    {
        $start = Carbon::create($run->period_year, $run->period_month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Unpaid absence days drive LOP. Approved paid leave does not.
        return (float) DB::table('attendance_records')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'absent')
            ->count();
    }
}
