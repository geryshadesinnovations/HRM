<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Subscription\Contracts\FeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `module:<code>`. Rejects with 403 MODULE_NOT_LICENSED when
 * the tenant's subscription does not grant the module. See docs/03-SUBSCRIPTION.md.
 */
final class EnsureModuleAccess
{
    public function __construct(private readonly FeatureAccess $access) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $this->access->assert("module:{$module}");

        return $next($request);
    }
}
