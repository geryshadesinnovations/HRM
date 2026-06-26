<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Company\Models\Company;
use App\Domains\Identity\Services\AuthService;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Services\SubscriptionEngine;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Exceptions\ApiException;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Super-admin company management (req #1). Runs platform-wide; tenant-scoped
 * relations are read inside an explicit bypass.
 */
final class AdminCompanyController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuthService $auth,
        private readonly SubscriptionEngine $engine,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->tenant->bypass(function () use ($request) {
            $query = Company::query()->with('subscription.plan');

            if ($search = $request->string('search')->toString()) {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
                });
            }
            if ($status = $request->string('status')->toString()) {
                $query->where('status', $status);
            }

            $paginator = $query->orderByDesc('id')->paginate(min((int) $request->integer('per_page', 20), 100));

            $data = collect($paginator->items())->map(fn (Company $c) => $this->summary($c))->all();

            return ApiResponse::paginated($paginator, $data);
        });
    }

    public function show(Company $company): JsonResponse
    {
        return $this->tenant->bypass(function () use ($company) {
            $company->load('subscription.plan');

            return ApiResponse::success(array_merge($this->summary($company), [
                'phone' => $company->phone,
                'industry' => $company->industry,
                'address' => $company->address,
                'gstin' => $company->gstin,
                'users' => User::where('company_id', $company->id)->count(),
                'recent_payments' => DB::table('payments')
                    ->where('company_id', $company->id)
                    ->orderByDesc('id')->limit(10)
                    ->get(['uuid', 'gateway', 'status', 'amount', 'currency', 'created_at']),
                'invoices' => DB::table('invoices')
                    ->where('company_id', $company->id)
                    ->orderByDesc('id')->limit(10)
                    ->get(['uuid', 'number', 'status', 'total', 'currency', 'issued_at']),
            ]));
        });
    }

    public function suspend(Company $company): JsonResponse
    {
        return $this->tenant->bypass(function () use ($company) {
            $company->update(['status' => 'suspended']);
            $company->subscription?->update(['status' => SubscriptionStatus::Suspended]);
            $this->engine->flush((int) $company->id);

            return ApiResponse::success($this->summary($company->fresh('subscription.plan')));
        });
    }

    public function activate(Company $company): JsonResponse
    {
        return $this->tenant->bypass(function () use ($company) {
            $company->update(['status' => 'active']);
            $sub = $company->subscription;
            if ($sub && $sub->status === SubscriptionStatus::Suspended) {
                $sub->update(['status' => SubscriptionStatus::Active]);
            }
            $this->engine->flush((int) $company->id);

            return ApiResponse::success($this->summary($company->fresh('subscription.plan')));
        });
    }

    public function destroy(Company $company): JsonResponse
    {
        // Soft delete only (req #1) — data is preserved and recoverable.
        $company->delete();

        return ApiResponse::success(['message' => 'Company archived (soft deleted).']);
    }

    public function resetPassword(Company $company): JsonResponse
    {
        return $this->tenant->bypass(function () use ($company) {
            $admin = User::where('company_id', $company->id)->role('Company Admin', 'api')->first()
                ?? User::where('company_id', $company->id)->first();

            if ($admin === null) {
                throw new ApiException(ErrorCode::NotFound, 'No admin user found for this company.', [], 404);
            }

            $temp = Str::password(12);
            $admin->password = $temp; // hashed by cast
            $admin->save();

            Log::warning('Super admin reset company password', ['company_id' => $company->id, 'admin_user_id' => $admin->id]);

            return ApiResponse::success(['email' => $admin->email, 'temporary_password' => $temp]);
        });
    }

    public function impersonate(Request $request, Company $company): JsonResponse
    {
        return $this->tenant->bypass(function () use ($request, $company) {
            $admin = User::where('company_id', $company->id)->role('Company Admin', 'api')->first()
                ?? User::where('company_id', $company->id)->first();

            if ($admin === null) {
                throw new ApiException(ErrorCode::NotFound, 'No user to impersonate for this company.', [], 404);
            }

            Log::warning('Super admin impersonation', [
                'company_id' => $company->id,
                'impersonated_user_id' => $admin->id,
                'by' => $request->user()?->id,
            ]);

            $tokens = $this->auth->issueTokens($admin, $request);

            return ApiResponse::success([
                'impersonating' => ['company' => $company->name, 'user' => $admin->email],
                'tokens' => $tokens,
            ]);
        });
    }

    private function summary(Company $company): array
    {
        return [
            'uuid' => $company->uuid,
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->status,
            'employees' => DB::table('employees')
                ->where('company_id', $company->id)->whereNull('deleted_at')->count(),
            'subscription' => $company->subscription ? [
                'status' => $company->subscription->status->value,
                'plan' => $company->subscription->plan?->name,
                'seats' => $company->subscription->seats,
                'current_period_end' => $company->subscription->current_period_end?->toIso8601String(),
            ] : null,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
