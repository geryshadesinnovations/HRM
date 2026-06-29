<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Attendance\Models\BiometricDevice;
use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Platform\Exceptions\ApiException;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Biometric/kiosk device management (tenant, feature-gated) plus the public
 * device punch ingestion endpoint (authenticated by device token, no user
 * session). See docs/06-MODULES.md (Attendance) and docs/07-API.md.
 */
final class BiometricDeviceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly TenantContext $tenant,
    ) {}

    /** List registered devices for the tenant. */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            BiometricDevice::orderByDesc('id')->get()->map(fn (BiometricDevice $d) => $this->present($d)),
        );
    }

    /** Register a device; returns the plaintext token ONCE. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
        ]);

        $token = 'dev_'.Str::random(40);

        $device = BiometricDevice::create([
            'name' => $data['name'],
            'serial' => $data['serial'] ?? null,
            'location' => $data['location'] ?? null,
            'token_hash' => BiometricDevice::hashToken($token),
            'is_active' => true,
        ]);

        return ApiResponse::success([
            ...$this->present($device),
            // Shown once — the device must store this; it is never retrievable again.
            'token' => $token,
        ], status: 201);
    }

    /** Deactivate (soft-delete) a device. */
    public function destroy(BiometricDevice $device): JsonResponse
    {
        $device->update(['is_active' => false]);
        $device->delete();

        return ApiResponse::success(['message' => 'Device revoked.']);
    }

    /**
     * Public punch ingestion. Authenticated by the `X-Device-Token` header.
     * Body: { employee_code, at? }. Toggles check-in/out for the day.
     */
    public function punch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:40'],
            'at' => ['nullable', 'date'],
        ]);

        $token = (string) $request->header('X-Device-Token', '');
        if ($token === '') {
            throw new ApiException(ErrorCode::Unauthenticated, 'Missing device token.', [], 401);
        }

        // Devices are tenant-owned; look up across tenants by token hash.
        $device = $this->tenant->bypass(fn () => BiometricDevice::where('token_hash', BiometricDevice::hashToken($token))
            ->where('is_active', true)
            ->first());

        if ($device === null) {
            throw new ApiException(ErrorCode::Unauthenticated, 'Invalid or revoked device token.', [], 401);
        }

        // Operate within the device's tenant and assert the biometric feature.
        return $this->tenant->forCompany((int) $device->company_id, function () use ($device, $data) {
            $this->attendance->assertBiometricAllowed();

            $employee = Employee::where('employee_code', $data['employee_code'])->first();
            if ($employee === null) {
                throw new ApiException(ErrorCode::NotFound, 'No employee matches that code.', [], 404);
            }

            $at = isset($data['at']) ? Carbon::parse($data['at']) : now();
            $record = $this->attendance->biometricPunch($device, $employee, $at);

            return ApiResponse::success([
                'employee_code' => $employee->employee_code,
                'work_date' => (string) $record->work_date,
                'checked_in' => $record->check_in?->toIso8601String(),
                'checked_out' => $record->check_out?->toIso8601String(),
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function present(BiometricDevice $d): array
    {
        return [
            'uuid' => $d->uuid,
            'name' => $d->name,
            'serial' => $d->serial,
            'location' => $d->location,
            'is_active' => $d->is_active,
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
        ];
    }
}
