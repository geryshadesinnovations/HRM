<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Platform\Services\PlatformMetricsService;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Super-admin platform dashboard analytics (req #1).
 */
final class AdminDashboardController extends Controller
{
    public function index(PlatformMetricsService $metrics): JsonResponse
    {
        return ApiResponse::success($metrics->dashboard());
    }
}
