<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Subscription\Models\Module;
use App\Domains\Subscription\Models\Plan;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-admin plan management (req #1). Pricing changes here flow straight to
 * the public landing page, which reads GET /public/plans.
 */
final class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Plan::with('modules:id,code,name')->orderBy('base_price')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request, null);
        $plan = Plan::create($this->attrs($data));
        $this->syncModules($plan, $data['modules'] ?? []);

        return ApiResponse::success($plan->load('modules:id,code,name'), status: 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validatePlan($request, $plan->id);
        $plan->update($this->attrs($data));
        if (array_key_exists('modules', $data)) {
            $this->syncModules($plan, $data['modules'] ?? []);
        }

        return ApiResponse::success($plan->load('modules:id,code,name'));
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete(); // soft delete

        return ApiResponse::success(['message' => 'Plan deleted.']);
    }

    public function duplicate(Plan $plan): JsonResponse
    {
        $copy = $plan->replicate(['code']);
        $copy->code = $plan->code.'-copy-'.Str::lower(Str::random(4));
        $copy->name = $plan->name.' (copy)';
        $copy->is_public = false;
        $copy->is_active = false;
        $copy->save();
        $copy->modules()->sync($plan->modules->pluck('id'));

        return ApiResponse::success($copy->load('modules:id,code,name'), status: 201);
    }

    public function toggle(Plan $plan): JsonResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return ApiResponse::success($plan);
    }

    private function validatePlan(Request $request, ?int $planId): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('plans', 'code')->ignore($planId)],
            'name' => ['required', 'string', 'max:120'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'base_price' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'included_seats' => ['required', 'integer', 'min:0'],
            'per_seat_price' => ['required', 'integer', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::exists('modules', 'code')],
        ]);
    }

    private function attrs(array $data): array
    {
        return collect($data)->except('modules')->all();
    }

    private function syncModules(Plan $plan, array $codes): void
    {
        $ids = Module::whereIn('code', $codes)->pluck('id');
        $plan->modules()->sync($ids);
    }
}
