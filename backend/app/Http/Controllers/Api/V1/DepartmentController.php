<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Department;
use App\Domains\Employee\Models\Designation;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Departments and designations (organisational structure). Tenant-scoped.
 */
final class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Department::query()->withCount('employees')->orderBy('name')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        return ApiResponse::success(Department::create($data), status: 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $department->update($data);

        return ApiResponse::success($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return ApiResponse::success(['message' => 'Department removed.']);
    }

    // --- Designations (nested under the same org-structure concern) ---

    public function designations(): JsonResponse
    {
        return ApiResponse::success(
            Designation::query()->with('department:id,name')->orderBy('name')->get(),
        );
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        return ApiResponse::success(Designation::create($data), status: 201);
    }
}
