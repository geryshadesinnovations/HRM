<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Core HR employee directory. Tenant-scoped automatically; create is seat-gated.
 * See docs/07-API.md (Employees).
 */
final class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['department', 'designation']);

        if ($status = $request->query('filter.status', $request->input('filter')['status'] ?? null)) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($paginator, EmployeeResource::collection($paginator)->resolve());
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated());

        return ApiResponse::success(
            new EmployeeResource($employee->load(['department', 'designation'])),
            status: 201,
        );
    }

    public function show(Employee $employee): JsonResponse
    {
        return ApiResponse::success(
            new EmployeeResource($employee->load(['department', 'designation', 'manager'])),
        );
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->service->update($employee, $request->validated());

        return ApiResponse::success(new EmployeeResource($employee->load(['department', 'designation'])));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->service->delete($employee);

        return ApiResponse::success(['message' => 'Employee removed.']);
    }
}
