<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Subscription\Contracts\FeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `feature:<code>`. Rejects with 403 FEATURE_NOT_AVAILABLE
 * when the tenant's plan does not enable the feature.
 */
final class EnsureFeatureAccess
{
    public function __construct(private readonly FeatureAccess $access) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $this->access->assert("feature:{$feature}");

        return $next($request);
    }
}
