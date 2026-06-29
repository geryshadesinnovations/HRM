<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use App\Domains\Attendance\Models\AttendanceRecord;
use App\Domains\Attendance\Services\AttendanceCorrectionService;
use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Platform\Exceptions\ApiException;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attendance API. Gated by `module:attendance`. HR marking requires
 * `attendance.mark`; self check-in/out requires `attendance.self`.
 * See docs/07-API.md (Attendance).
 */
final class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $service,
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    /** HR: mark a single employee, or bulk-mark many, for a date. */
    public function mark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'employee' => ['required_without:entries', 'string'],
            'status' => ['required_without:entries', Rule::in(AttendanceRecord::STATUSES)],
            'entries' => ['required_without:employee', 'array'],
            'entries.*' => [Rule::in(AttendanceRecord::STATUSES)],
        ]);

        if (isset($data['entries'])) {
            // entries keyed by employee uuid → resolve to ids within the tenant
            $map = [];
            foreach ($data['entries'] as $uuid => $status) {
                $employee = Employee::where('uuid', $uuid)->first();
                if ($employee !== null) {
                    $map[$employee->id] = $status;
                }
            }
            $written = $this->service->bulkMark($data['work_date'], $map);

            return ApiResponse::success(['marked' => $written]);
        }

        $employee = Employee::where('uuid', $data['employee'])->firstOrFail();
        $record = $this->service->mark($employee, $data['work_date'], $data['status']);

        return ApiResponse::success($record, status: 201);
    }

    /** Employee self check-in. */
    public function checkIn(Request $request): JsonResponse
    {
        $record = $this->service->checkIn($this->currentEmployee($request));

        return ApiResponse::success($record, status: 201);
    }

    /** Employee self check-out. */
    public function checkOut(Request $request): JsonResponse
    {
        $record = $this->service->checkOut($this->currentEmployee($request));

        return ApiResponse::success($record);
    }

    /** Employee starts a break. */
    public function breakIn(Request $request): JsonResponse
    {
        $record = $this->service->breakIn($this->currentEmployee($request));

        return ApiResponse::success($record);
    }

    /** Employee ends a break. */
    public function breakOut(Request $request): JsonResponse
    {
        $record = $this->service->breakOut($this->currentEmployee($request));

        return ApiResponse::success($record);
    }

    /** List attendance correction requests (optionally filtered by status). */
    public function corrections(Request $request): JsonResponse
    {
        $query = AttendanceCorrectionRequest::query()
            ->with('employee:id,uuid,first_name,last_name')
            ->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($paginator);
    }

    /** Raise an attendance correction request for the current employee. */
    public function storeCorrection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'requested_check_in' => ['nullable', 'date'],
            'requested_check_out' => ['nullable', 'date'],
            'requested_status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $correction = $this->corrections->create($this->currentEmployee($request), $data, $request->user()?->getKey());

        return ApiResponse::success($correction, status: 201);
    }

    /** Approve a correction request and apply it to the attendance record. */
    public function approveCorrection(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return ApiResponse::success(
            $this->corrections->approve($correction, $request->user()?->getKey(), $note),
        );
    }

    /** Reject a correction request. */
    public function rejectCorrection(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return ApiResponse::success(
            $this->corrections->reject($correction, $request->user()?->getKey(), $note),
        );
    }

    /** List attendance, optionally filtered by employee uuid and date range. */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::query()->with('employee:id,uuid,first_name,last_name');

        if ($uuid = $request->string('employee')->toString()) {
            $employee = Employee::where('uuid', $uuid)->first();
            $query->where('employee_id', $employee?->id ?? 0);
        }
        if ($from = $request->date('from')) {
            $query->where('work_date', '>=', $from->toDateString());
        }
        if ($to = $request->date('to')) {
            $query->where('work_date', '<=', $to->toDateString());
        }

        $paginator = $query->orderByDesc('work_date')
            ->paginate(min((int) $request->integer('per_page', 31), 100));

        return ApiResponse::paginated($paginator);
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = Employee::where('user_id', $request->user()?->getKey())->first();

        if ($employee === null) {
            throw new ApiException(
                ErrorCode::NotFound,
                'Your login is not linked to an employee profile.',
                [],
                404,
            );
        }

        return $employee;
    }
}
