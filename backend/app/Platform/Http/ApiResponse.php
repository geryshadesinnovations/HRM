<?php

declare(strict_types=1);

namespace App\Platform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Standardized API response envelope.
 *
 * Success: { "data": ..., "meta"?: ... }
 * Error:   { "error": { "code", "message", "details"? } }
 *
 * See docs/07-API.md.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function paginated(AbstractPaginator $paginator, mixed $data = null): JsonResponse
    {
        /** @var LengthAwarePaginator $paginator */
        return self::success(
            $data ?? $paginator->items(),
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
            ],
        );
    }

    public static function error(string $code, string $message, array $details = [], int $status = 400): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
