<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Notification\Models\Notification;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user in-app notifications. Every query is constrained to the authenticated
 * user, so a user can only ever see and act on their own notifications.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $query = Notification::where('user_id', $userId);
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->orderByDesc('id')->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($paginator);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread' => $count]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 404);

        $notification->update(['read_at' => now()]);

        return ApiResponse::success($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(['message' => 'All notifications marked as read.']);
    }
}
