<?php

declare(strict_types=1);

namespace App\Platform\Support;

/**
 * Canonical API error codes. See docs/07-API.md.
 */
enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case ValidationFailed = 'VALIDATION_FAILED';
    case NotFound = 'NOT_FOUND';
    case ModuleNotLicensed = 'MODULE_NOT_LICENSED';
    case FeatureNotAvailable = 'FEATURE_NOT_AVAILABLE';
    case SeatLimitReached = 'SEAT_LIMIT_REACHED';
    case SubscriptionInactive = 'SUBSCRIPTION_INACTIVE';
    case TenantMismatch = 'TENANT_MISMATCH';
    case RateLimited = 'RATE_LIMITED';
    case ServerError = 'SERVER_ERROR';
}
