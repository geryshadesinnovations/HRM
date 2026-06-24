<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the authenticated user and binds it to the
 * TenantContext for the rest of the request.
 *
 * Super Admins (platform users with no company_id) pass through with no tenant;
 * they must opt into a tenant explicitly for support actions.
 *
 * See docs/08-SECURITY.md (Tenant Isolation).
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->company_id !== null) {
            $this->context->setCompanyId((int) $user->company_id);
        }

        return $next($request);
    }
}
