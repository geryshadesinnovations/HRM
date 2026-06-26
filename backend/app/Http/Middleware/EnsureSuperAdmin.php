<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform Super Admins. Super Admins operate across all
 * tenants, so these routes deliberately run without a tenant scope (the
 * controllers bypass tenancy explicitly where they touch tenant-owned data).
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            throw new ApiException(ErrorCode::Forbidden, 'Super admin access required.', [], 403);
        }

        return $next($request);
    }
}
