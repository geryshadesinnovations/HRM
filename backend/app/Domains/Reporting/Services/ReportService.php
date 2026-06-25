<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Payroll\Models\PayrollRun;
use App\Platform\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregations across modules. Every query is tenant-scoped (the
 * models use BelongsToTenant), so a report only ever covers the active company.
 *
 * Each method returns plain associative rows suitable for JSON or CSV export.
 *
 * See docs/09-REPORTING-NOTIFICATIONS.md.
 */
final class ReportService
{
    /**
     * Attendance summary per employee for a date range: counts by status plus
     * total worked / overtime minutes.
     *
     * @return array<int,array<string,mixed>>
     */
    public function attendanceSummary(string $from, string $to): array
    {
        $employees = Employee::query()->orderBy('first_name')->get(['id', 'employee_code', 'first_name', 'last_name']);

        $rows = [];
        foreach ($employees as $emp) {
            $agg = DB::table('attendance_records')
                ->where('employee_id', $emp->id)
                ->whereBetween('work_date', [$from, $to])
                ->selectRaw("
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS leave,
                    SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) AS half_day,
                    COALESCE(SUM(worked_minutes), 0) AS worked_minutes,
                    COALESCE(SUM(overtime_minutes), 0) AS overtime_minutes
                ")
                ->first();

            $rows[] = [
                'employee_code' => $emp->employee_code,
                'employee' => $emp->full_name,
                'present' => (int) ($agg->present ?? 0),
                'absent' => (int) ($agg->absent ?? 0),
                'leave' => (int) ($agg->leave ?? 0),
                'half_day' => (int) ($agg->half_day ?? 0),
                'worked_hours' => round(((int) ($agg->worked_minutes ?? 0)) / 60, 1),
                'overtime_hours' => round(((int) ($agg->overtime_minutes ?? 0)) / 60, 1),
            ];
        }

        return $rows;
    }

    /**
     * Leave balances per employee/type for a year.
     *
     * @return array<int,array<string,mixed>>
     */
    public function leaveBalances(int $year): array
    {
        $balances = LeaveBalance::with(['employee:id,employee_code,first_name,last_name', 'leaveType:id,name'])
            ->where('year', $year)
            ->get();

        return $balances->map(fn (LeaveBalance $b) => [
            'employee_code' => $b->employee?->employee_code,
            'employee' => $b->employee?->full_name,
            'leave_type' => $b->leaveType?->name,
            'year' => $b->year,
            'allocated' => (float) $b->allocated,
            'used' => (float) $b->used,
            'remaining' => $b->remaining,
        ])->all();
    }

    /**
     * Payroll register for a run: one row per payslip with money as decimals.
     *
     * @return array{rows:array<int,array<string,mixed>>,totals:array<string,mixed>}
     */
    public function payrollRegister(PayrollRun $run): array
    {
        $run->load(['payslips.employee:id,employee_code,first_name,last_name']);

        $rows = $run->payslips->map(fn ($s) => [
            'employee_code' => $s->employee?->employee_code,
            'employee' => $s->employee?->full_name,
            'gross' => Money::of((int) $s->gross)->format(),
            'deductions' => Money::of((int) $s->deductions)->format(),
            'lop_days' => (float) $s->lop_days,
            'net' => Money::of((int) $s->net)->format(),
        ])->all();

        return [
            'rows' => $rows,
            'totals' => [
                'gross' => Money::of((int) ($run->total_gross ?? 0))->format(),
                'deductions' => Money::of((int) ($run->total_deductions ?? 0))->format(),
                'net' => Money::of((int) ($run->total_net ?? 0))->format(),
                'employees' => $run->payslips->count(),
            ],
        ];
    }

    /**
     * Employee directory / headcount.
     *
     * @return array<int,array<string,mixed>>
     */
    public function employeeDirectory(): array
    {
        $employees = Employee::with(['department:id,name', 'designation:id,name'])
            ->orderBy('first_name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'employee_code' => $e->employee_code,
            'name' => $e->full_name,
            'email' => $e->email,
            'department' => $e->department?->name,
            'designation' => $e->designation?->name,
            'status' => $e->status,
        ])->all();
    }
}
