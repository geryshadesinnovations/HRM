<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Attendance\Models\BiometricDevice;
use App\Domains\Employee\Models\Employee;
use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;

/**
 * Attendance operations for both modes:
 *   - HR manual marking (single / bulk) for a date.
 *   - Employee self check-in / check-out with computed worked + overtime minutes.
 *   - GPS-tagged punches and biometric device punches (feature-gated).
 *
 * See docs/06-MODULES.md (Attendance Module).
 */
final class AttendanceService
{
    private const DEFAULT_FULL_DAY_MINUTES = 480; // 8h; overtime accrues beyond this

    public function __construct(private readonly FeatureAccess $access) {}

    /** Assert the tenant's plan includes biometric attendance. */
    public function assertBiometricAllowed(): void
    {
        $this->access->assert('feature:attendance.biometric');
    }

    /**
     * HR marks a single employee for a date. Idempotent on (employee, date).
     */
    public function mark(Employee $employee, string $workDate, string $status, string $source = 'manual'): AttendanceRecord
    {
        $this->assertStatus($status);

        $date = Carbon::parse($workDate)->toDateString();
        $existing = AttendanceRecord::where('employee_id', $employee->id)->where('work_date', $date)->first();
        if ($existing !== null) {
            $this->assertNotLocked($existing);
        }

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $date],
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

            $date = Carbon::parse($workDate)->toDateString();
            $existing = AttendanceRecord::where('employee_id', $employee->id)->where('work_date', $date)->first();
            if ($existing !== null && $existing->locked) {
                continue; // never overwrite a locked (finalized) day
            }

            AttendanceRecord::updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $date],
                ['status' => $status, 'source' => $source],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Self check-in for today. Rejects a second check-in.
     *
     * @param  array{lat?:float|null,lng?:float|null,method?:string}  $geo
     */
    public function checkIn(Employee $employee, ?Carbon $at = null, array $geo = []): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);
        $this->assertNotLocked($record);

        if ($record->check_in !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Already checked in today.', [], 422);
        }

        $method = $this->resolveCaptureMethod($geo);

        $record->fill([
            'status' => 'present',
            'check_in' => $at,
            'source' => 'self',
            'capture_method' => $method,
            'check_in_lat' => $geo['lat'] ?? null,
            'check_in_lng' => $geo['lng'] ?? null,
        ])->save();

        return $record;
    }

    /**
     * Self check-out for today. Computes worked + overtime minutes.
     *
     * @param  array{lat?:float|null,lng?:float|null,method?:string}  $geo
     */
    public function checkOut(Employee $employee, ?Carbon $at = null, array $geo = []): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);
        $this->assertNotLocked($record);

        if ($record->check_in === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'You must check in before checking out.', [], 422);
        }
        if ($record->check_out !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Already checked out today.', [], 422);
        }
        if ($record->break_in !== null && $record->break_out === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'End your break before checking out.', [], 422);
        }

        if (($geo['lat'] ?? null) !== null) {
            $this->resolveCaptureMethod($geo); // assert GPS feature when coords supplied
        }

        $gross = max(0, $record->check_in->diffInMinutes($at));
        $worked = max(0, $gross - (int) $record->break_minutes);
        $fullDay = self::DEFAULT_FULL_DAY_MINUTES;

        $record->fill([
            'check_out' => $at,
            'check_out_lat' => $geo['lat'] ?? null,
            'check_out_lng' => $geo['lng'] ?? null,
            'worked_minutes' => $worked,
            'overtime_minutes' => max(0, $worked - $fullDay),
            'status' => $worked < ($fullDay / 2) ? 'half_day' : 'present',
        ])->save();

        return $record;
    }

    /**
     * Record a punch pushed by a registered biometric/kiosk device. Toggles
     * between check-in and check-out for the day. Tenant scope is the device's
     * company. Gated by the `attendance.biometric` feature at the route.
     */
    public function biometricPunch(BiometricDevice $device, Employee $employee, ?Carbon $at = null): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);
        $this->assertNotLocked($record);

        $device->forceFill(['last_seen_at' => now()])->save();

        if ($record->check_in === null) {
            $record->fill([
                'status' => 'present',
                'check_in' => $at,
                'source' => 'device',
                'capture_method' => 'biometric',
                'biometric_device_id' => $device->id,
            ])->save();

            return $record;
        }

        if ($record->check_out === null) {
            $worked = max(0, $record->check_in->diffInMinutes($at) - (int) $record->break_minutes);
            $record->fill([
                'check_out' => $at,
                'biometric_device_id' => $device->id,
                'worked_minutes' => $worked,
                'overtime_minutes' => max(0, $worked - self::DEFAULT_FULL_DAY_MINUTES),
                'status' => $worked < (self::DEFAULT_FULL_DAY_MINUTES / 2) ? 'half_day' : 'present',
            ])->save();
        }

        return $record;
    }

    /**
     * Resolve and validate the capture method. GPS punches require the
     * `attendance.gps` feature.
     *
     * @param  array{lat?:float|null,lng?:float|null,method?:string}  $geo
     */
    private function resolveCaptureMethod(array $geo): string
    {
        $hasCoords = ($geo['lat'] ?? null) !== null && ($geo['lng'] ?? null) !== null;
        $method = $geo['method'] ?? ($hasCoords ? 'gps' : 'web');

        if ($method === 'gps') {
            $this->access->assert('feature:attendance.gps');
        }

        return $method;
    }

    /** Employee starts a break. */
    public function breakIn(Employee $employee, ?Carbon $at = null): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);
        $this->assertNotLocked($record);

        if ($record->check_in === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Check in before starting a break.', [], 422);
        }
        if ($record->break_in !== null && $record->break_out === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'You are already on a break.', [], 422);
        }

        $record->fill(['break_in' => $at, 'break_out' => null])->save();

        return $record;
    }

    /** Employee ends a break; accumulates total break minutes. */
    public function breakOut(Employee $employee, ?Carbon $at = null): AttendanceRecord
    {
        $at ??= now();
        $record = $this->todayRecord($employee, $at);
        $this->assertNotLocked($record);

        if ($record->break_in === null || $record->break_out !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'You are not currently on a break.', [], 422);
        }

        $minutes = max(0, $record->break_in->diffInMinutes($at));

        $record->fill([
            'break_out' => $at,
            'break_minutes' => (int) $record->break_minutes + $minutes,
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

    private function assertNotLocked(AttendanceRecord $record): void
    {
        if ($record->exists && $record->locked) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'This day is locked by a finalized payroll period and cannot be changed.',
                ['work_date' => (string) $record->work_date],
                422,
            );
        }
    }
}
