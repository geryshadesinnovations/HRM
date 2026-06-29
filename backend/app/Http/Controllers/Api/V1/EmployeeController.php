<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeImportService;
use App\Domains\Employee\Services\EmployeeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            new EmployeeResource(
                $employee->load(['department', 'designation', 'manager', 'shift'])->loadCount('documents'),
            ),
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

    /** Bulk import employees from a CSV upload (seat-gated). */
    public function import(Request $request, EmployeeImportService $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 MB
        ]);

        $result = $importer->import($request->file('file'));

        return ApiResponse::success($result, status: $result['summary']['failed'] > 0 ? 207 : 201);
    }

    /** Download a CSV template with the recognised import columns. */
    public function importTemplate(): StreamedResponse
    {
        $headers = EmployeeImportService::COLUMNS;
        $sample = [array_combine($headers, [
            'EMP-1001', 'Asha', 'Rao', 'asha.rao@example.com', '9876543210',
            'Engineering', 'Senior Engineer', '2024-04-01', 'full_time', 'female', 'Bengaluru HQ',
        ])];

        return Csv::download('employee-import-template.csv', $sample, $headers);
    }
}
