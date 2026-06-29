<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Employee\Models\Employee;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attendance correction request workflow (req #6). An employee or HR raises a
 * request to amend a day's attendance; an approver applies or rejects it. On
 * approval the underlying AttendanceRecord is updated (unless the day is locked
 * by a finalized payroll period).
 */
final class AttendanceCorrectionService
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function create(Employee $employee, array $data, ?int $userId): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'work_date' => Carbon::parse($data['work_date'])->toDateString(),
            'requested_check_in' => $data['requested_check_in'] ?? null,
            'requested_check_out' => $data['requested_check_out'] ?? null,
            'requested_status' => $data['requested_status'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by' => $userId,
        ]);
    }

    public function approve(AttendanceCorrectionRequest $request, ?int $reviewerId, ?string $note): AttendanceCorrectionRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $reviewerId, $note) {
            $date = Carbon::parse($request->work_date)->toDateString();

            $record = AttendanceRecord::firstOrNew([
                'employee_id' => $request->employee_id,
                'work_date' => $date,
            ]);

            if ($record->exists && $record->locked) {
                throw new ApiException(
                    ErrorCode::ValidationFailed,
                    'The target day is locked by a finalized payroll period.',
                    ['work_date' => $date],
                    422,
                );
            }

            if ($request->requested_status !== null) {
                $record->status = $request->requested_status;
            }
            if ($request->requested_check_in !== null) {
                $record->check_in = $request->requested_check_in;
            }
            if ($request->requested_check_out !== null) {
                $record->check_out = $request->requested_check_out;
            }

            // Recompute worked/overtime when both punches are present.
            if ($record->check_in !== null && $record->check_out !== null) {
                $worked = max(0, Carbon::parse($record->check_in)->diffInMinutes(Carbon::parse($record->check_out)));
                $worked = max(0, $worked - (int) $record->break_minutes);
                $record->worked_minutes = $worked;
                $record->overtime_minutes = max(0, $worked - 480);
            }

            $record->source = 'manual';
            $record->save();

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return $request->refresh();
        });
    }

    public function reject(AttendanceCorrectionRequest $request, ?int $reviewerId, ?string $note): AttendanceCorrectionRequest
    {
        $this->assertPending($request);

        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $request->refresh();
    }

    private function assertPending(AttendanceCorrectionRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'This correction request has already been reviewed.',
                ['status' => $request->status],
                422,
            );
        }
    }
}
