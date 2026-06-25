<?php

declare(strict_types=1);

namespace App\Domains\Notification\Services;

use App\Domains\Notification\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

/**
 * Central notification dispatcher. Always persists an in-app notification; also
 * sends an email when the mail channel is enabled (best-effort). Safe to call
 * from services, controllers, and console commands (no tenant context required).
 *
 * See docs/09-REPORTING-NOTIFICATIONS.md.
 */
final class NotificationService
{
    /**
     * Notify a single user (no-op if the user id is null, e.g. an employee with
     * no linked login account).
     *
     * @param  array<string,mixed>  $data
     */
    public function toUser(
        ?int $userId,
        ?int $companyId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
    ): ?Notification {
        if ($userId === null) {
            return null;
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channel' => 'in_app',
        ]);

        $this->maybeEmail($userId, $title, $body);

        return $notification;
    }

    /**
     * Notify every Company Admin of a company (e.g. billing/subscription events).
     *
     * @param  array<string,mixed>  $data
     */
    public function toCompanyAdmins(
        int $companyId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
    ): void {
        // Guard: if the role isn't defined (e.g. minimal test seed), there are
        // no admins to notify — skip rather than throw.
        if (! Role::where('name', 'Company Admin')->where('guard_name', 'api')->exists()) {
            return;
        }

        $admins = User::query()
            ->where('company_id', $companyId)
            ->role('Company Admin', 'api')
            ->get(['id']);

        foreach ($admins as $admin) {
            $this->toUser($admin->id, $companyId, $type, $title, $body, $data);
        }
    }

    private function maybeEmail(int $userId, string $title, ?string $body): void
    {
        if (! config('notifications.channels.mail')) {
            return;
        }

        try {
            $user = User::find($userId);
            if ($user?->email) {
                Mail::raw($body ?? $title, function ($message) use ($user, $title): void {
                    $message->to($user->email)->subject($title);
                });
            }
        } catch (\Throwable $e) {
            // Email is best-effort; never let it break the business action.
            Log::warning('Notification email failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
