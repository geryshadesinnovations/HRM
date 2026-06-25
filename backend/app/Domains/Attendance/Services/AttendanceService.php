<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Employee\Models\Employee;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;

/**
 * Attendance operations for both modes:
 *   - HR manual marking (single / bulk) for a date.
 *   - Employee self check-in / check-out with computed worked + overtime minutes.
 *
 * See docs/06-MODULES.md (Attendance Module).
 */
final class AttendanceService
{
    private const DEFAULT_FULL_DAY_MINUTES = 480; // 8h; overtime accrues beyond this

    /**
     * HR marks a single employee for a date. Idempotent on (employee, date).
     */
    public function mark(Employee $employee, string $workDate, string $status, string $source = 'manual'): AttendanceRecord
    {
        $this->assertStatus($status);

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => Carbon::parse($workDate)->toDateString()],
            ['status' => $status, 'source' => $source],
        );
    }

    /**
     * Bulk-mark many employees for one date.
     *
     * @param  array<int,string>  $statusByEmployeeId  employee_id => status
     * @return int number of records written
     */
    public function bulkMark(string $workDate, array $statusByEmployeeId, string $source = 'manual'): int
    {
        $count = 0;

        foreach ($statusByEmployeeId as $employeeId => $status) {
            $this->assertStatus($status);

            // Tenant scope guarantees the employee belongs to the active company.
            $employee = Employee::find($employeeId);
            if ($employee === null) {
                continue;
            }

            AttendanceRecord::updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => Carbon::parse($workDate)->toDateString()],
                ['status' => $status, 'source' => $source],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Self check-in for today. Rejects a second check-in.
     */
    public function checkIn(Employee $employee, ?Carbon $at = null): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);

        if ($record->check_in !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Already checked in today.', [], 422);
        }

        $record->fill([
            'status' => 'present',
            'check_in' => $at,
            'source' => 'self',
        ])->save();

        return $record;
    }

    /**
     * Self check-out for today. Computes worked + overtime minutes.
     */
    public function checkOut(Employee $employee, ?Carbon $at = null): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);

        if ($record->check_in === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'You must check in before checking out.', [], 422);
        }
        if ($record->check_out !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Already checked out today.', [], 422);
        }

        $worked = max(0, $record->check_in->diffInMinutes($at));
        $fullDay = self::DEFAULT_FULL_DAY_MINUTES;

        $record->fill([
            'check_out' => $at,
            'worked_minutes' => $worked,
            'overtime_minutes' => max(0, $worked - $fullDay),
            'status' => $worked < ($fullDay / 2) ? 'half_day' : 'present',
        ])->save();

        return $record;
    }

    private function todayRecord(Employee $employee, Carbon $at): AttendanceRecord
    {
        return AttendanceRecord::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => $at->toDateString(),
        ]);
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, AttendanceRecord::STATUSES, true)) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'Invalid attendance status.',
                ['status' => AttendanceRecord::STATUSES],
                422,
            );
        }
    }
}
