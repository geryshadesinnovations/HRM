<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveRequestApproval;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave workflow: request → approve/reject. Approval deducts the balance and,
 * when the Attendance module is licensed, marks the covered dates as 'leave'.
 *
 * See docs/06-MODULES.md (Leave Module).
 */
final class LeaveService
{
    public function __construct(
        private readonly FeatureAccess $access,
        private readonly AttendanceService $attendance,
    ) {}

    public function request(Employee $employee, LeaveType $type, string $start, string $end, ?string $reason = null): LeaveRequest
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new ApiException(ErrorCode::ValidationFailed, 'End date must be on or after start date.', [], 422);
        }

        $days = (float) ($startDate->diffInDays($endDate) + 1); // inclusive calendar days

        // Validate there is enough balance (for paid types with a quota).
        $balance = $this->balanceFor($employee, $type, $startDate->year);
        if ($type->is_paid && $balance->remaining < $days) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'Insufficient leave balance.',
                ['remaining' => $balance->remaining, 'requested' => $days],
                422,
            );
        }

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'status' => 'pending',
            'reason' => $reason,
        ]);
    }

    public function approve(LeaveRequest $request, ?int $actorUserId, ?string $note = null): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $actorUserId, $note) {
            $request->update(['status' => 'approved']);

            LeaveRequestApproval::create([
                'leave_request_id' => $request->id,
                'actor_user_id' => $actorUserId,
                'decision' => 'approved',
                'note' => $note,
            ]);

            // Deduct balance.
            $balance = $this->balanceFor($request->employee, $request->leaveType, $request->start_date->year);
            $balance->increment('used', $request->days);

            // Integrate with Attendance when that module is licensed (graceful otherwise).
            if ($this->access->canAccessModule('attendance')) {
                $this->markAttendanceAsLeave($request);
            }

            return $request->refresh();
        });
    }

    public function reject(LeaveRequest $request, ?int $actorUserId, ?string $note = null): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $actorUserId, $note) {
            $request->update(['status' => 'rejected']);

            LeaveRequestApproval::create([
                'leave_request_id' => $request->id,
                'actor_user_id' => $actorUserId,
                'decision' => 'rejected',
                'note' => $note,
            ]);

            return $request->refresh();
        });
    }

    /**
     * Get or lazily create the balance row, seeding `allocated` from the type quota.
     */
    public function balanceFor(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['allocated' => $type->annual_quota, 'used' => 0],
        );
    }

    private function markAttendanceAsLeave(LeaveRequest $request): void
    {
        $cursor = $request->start_date->copy();

        while ($cursor->lte($request->end_date)) {
            $this->attendance->mark($request->employee, $cursor->toDateString(), 'leave', 'manual');
            $cursor->addDay();
        }
    }

    private function assertPending(LeaveRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                "Leave request is already {$request->status}.",
                [],
                422,
            );
        }
    }
}
