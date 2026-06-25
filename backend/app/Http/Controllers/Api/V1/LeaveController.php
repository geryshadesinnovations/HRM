<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Leave\Services\LeaveService;
use App\Http\Controllers\Controller;
use App\Platform\Exceptions\ApiException;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leave API. Gated by `module:leave`. See docs/07-API.md (Leave).
 */
final class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $service) {}

    // --- Leave types ---

    public function types(): JsonResponse
    {
        return ApiResponse::success(LeaveType::orderBy('name')->get());
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:80'],
            'is_paid' => ['boolean'],
            'annual_quota' => ['numeric', 'min:0', 'max:9999'],
            'carry_forward' => ['boolean'],
            'carry_forward_cap' => ['numeric', 'min:0', 'max:9999'],
        ]);

        return ApiResponse::success(LeaveType::create($data), status: 201);
    }

    // --- Balances ---

    public function balances(Request $request): JsonResponse
    {
        $query = LeaveBalance::query()->with(['leaveType:id,code,name', 'employee:id,uuid,first_name,last_name']);

        if ($uuid = $request->string('employee')->toString()) {
            $employee = Employee::where('uuid', $uuid)->first();
            $query->where('employee_id', $employee?->id ?? 0);
        }

        $balances = $query->get()->map(fn (LeaveBalance $b) => [
            'leave_type' => $b->leaveType?->only(['code', 'name']),
            'year' => $b->year,
            'allocated' => $b->allocated,
            'used' => $b->used,
            'remaining' => $b->remaining,
        ]);

        return ApiResponse::success($balances);
    }

    // --- Requests ---

    public function requests(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()->with(['leaveType:id,code,name', 'employee:id,uuid,first_name,last_name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $paginator = $query->orderByDesc('id')->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type' => ['required', 'string'],     // leave type code
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'employee' => ['nullable', 'string'],         // HR may file on behalf (uuid)
        ]);

        $employee = isset($data['employee'])
            ? Employee::where('uuid', $data['employee'])->firstOrFail()
            : $this->currentEmployee($request);

        $type = LeaveType::where('code', $data['leave_type'])->firstOrFail();

        $leave = $this->service->request($employee, $type, $data['start_date'], $data['end_date'], $data['reason'] ?? null);

        return ApiResponse::success($leave->load('leaveType:id,code,name'), status: 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $note = $request->string('note')->toString() ?: null;
        $updated = $this->service->approve($leaveRequest, $request->user()?->getKey(), $note);

        return ApiResponse::success($updated);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $note = $request->string('note')->toString() ?: null;
        $updated = $this->service->reject($leaveRequest, $request->user()?->getKey(), $note);

        return ApiResponse::success($updated);
    }

    public function calendar(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()
            ->where('status', 'approved')
            ->with(['employee:id,uuid,first_name,last_name', 'leaveType:id,code,name']);

        if ($from = $request->date('from')) {
            $query->where('end_date', '>=', $from->toDateString());
        }
        if ($to = $request->date('to')) {
            $query->where('start_date', '<=', $to->toDateString());
        }

        return ApiResponse::success($query->orderBy('start_date')->get());
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
