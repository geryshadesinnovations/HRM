<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Services\AuthService;
use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Subscription\Services\SubscriptionEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Platform\Http\ApiResponse;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CompanyRegistrationService $registration,
    ) {}

    public function registerCompany(RegisterCompanyRequest $request): JsonResponse
    {
        $result = $this->registration->register(
            $request->string('company_name')->toString(),
            $request->string('admin_name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('plan_code'),
            $request->only(['phone', 'gstin', 'industry', 'employees_estimate', 'address']),
        );

        $tokens = $this->auth->issueTokens($result['user'], $request);

        return ApiResponse::success([
            'company' => ['uuid' => $result['company']->uuid, 'name' => $result['company']->name],
            'user' => $this->userPayload($result['user']),
            'tokens' => $tokens,
        ], status: 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        return ApiResponse::success([
            'user' => $this->userPayload($result['user']),
            'tokens' => $result['tokens'],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $result = $this->auth->refresh($request->string('refresh_token')->toString(), $request);

        return ApiResponse::success([
            'user' => $this->userPayload($result['user']),
            'tokens' => $result['tokens'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->userPayload($request->user()));
    }

    public function entitlements(Request $request, SubscriptionEngine $engine, TenantContext $tenant): JsonResponse
    {
        // ResolveTenant middleware has already bound the tenant.
        return ApiResponse::success($engine->forCurrentTenant()->toArray());
    }

    private function userPayload($user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isSuperAdmin(),
            'company_id' => $user->company_id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
