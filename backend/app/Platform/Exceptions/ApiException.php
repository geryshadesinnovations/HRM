<?php

declare(strict_types=1);

namespace App\Platform\Exceptions;

use App\Platform\Http\ApiResponse;
use App\Platform\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base class for domain exceptions that render as the standard error envelope.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            $this->errorCode->value,
            $this->getMessage(),
            $this->details,
            $this->status,
        );
    }

    public static function moduleNotLicensed(string $module): self
    {
        return new self(
            ErrorCode::ModuleNotLicensed,
            "The '{$module}' module is not included in your current subscription.",
            ['module' => $module],
            403,
        );
    }

    public static function featureNotAvailable(string $feature): self
    {
        return new self(
            ErrorCode::FeatureNotAvailable,
            "The '{$feature}' feature is not available on your plan.",
            ['feature' => $feature],
            403,
        );
    }

    public static function seatLimitReached(int $limit, int $current): self
    {
        return new self(
            ErrorCode::SeatLimitReached,
            "Your plan allows {$limit} employees.",
            ['limit' => $limit, 'current' => $current],
            403,
        );
    }

    public static function subscriptionInactive(string $status): self
    {
        return new self(
            ErrorCode::SubscriptionInactive,
            'Your subscription is not active.',
            ['status' => $status],
            403,
        );
    }

    public static function tenantMismatch(): self
    {
        return new self(
            ErrorCode::TenantMismatch,
            'The requested resource belongs to a different company.',
            [],
            403,
        );
    }
}
