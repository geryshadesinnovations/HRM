<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Attendance\Models\AttendanceRecord;
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
    public function __construct(private readonly AttendanceService $service) {}

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
