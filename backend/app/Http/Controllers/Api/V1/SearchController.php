<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Department;
use App\Domains\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-scoped global search powering the command palette (req: command
 * palette + global search). Results are grouped by type and respect the
 * caller's permissions. See docs/10-FRONTEND-UX.md.
 */
final class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();
        $groups = [];

        if ($term === '' || mb_strlen($term) < 2) {
            return ApiResponse::success(['query' => $term, 'groups' => $groups]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        if ($user?->can('employee.profile.view')) {
            $employees = Employee::query()
                ->where(function ($q) use ($like): void {
                    $q->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('employee_code', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orderBy('first_name')
                ->limit(8)
                ->get(['uuid', 'first_name', 'last_name', 'employee_code', 'status']);

            if ($employees->isNotEmpty()) {
                $groups[] = [
                    'type' => 'employees',
                    'label' => 'Employees',
                    'items' => $employees->map(fn (Employee $e) => [
                        'id' => $e->uuid,
                        'title' => trim($e->first_name.' '.(string) $e->last_name),
                        'subtitle' => $e->employee_code,
                        'href' => '/employees?q='.urlencode($e->employee_code),
                    ])->all(),
                ];

                $departments = Department::where('name', 'like', $like)->limit(5)->get(['id', 'name']);
                if ($departments->isNotEmpty()) {
                    $groups[] = [
                        'type' => 'departments',
                        'label' => 'Departments',
                        'items' => $departments->map(fn (Department $d) => [
                            'id' => (string) $d->id,
                            'title' => $d->name,
                            'subtitle' => 'Department',
                            'href' => '/employees',
                        ])->all(),
                    ];
                }
            }
        }

        return ApiResponse::success(['query' => $term, 'groups' => $groups]);
    }
}
