<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Services\CompanyRegistrationService;
use App\Domains\Notification\Models\Notification;
use App\Domains\Notification\Services\NotificationService;
use App\Models\User;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class, RolePermissionSeeder::class]);

        $result = app(CompanyRegistrationService::class)->register('Acme', 'Admin', 'admin@acme.test', 'password123', 'growth');
        $this->admin = $result['user'];
        app(TenantContext::class)->setCompanyId($result['company']->id);
    }

    public function test_notify_user_creates_in_app_row(): void
    {
        $n = app(NotificationService::class)->toUser($this->admin->id, $this->admin->company_id, 'test.x', 'Hello', 'Body');

        $this->assertNotNull($n);
        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->count());
        $this->assertNull($n->read_at);
    }

    public function test_notify_user_is_noop_for_null_user(): void
    {
        $this->assertNull(app(NotificationService::class)->toUser(null, $this->admin->company_id, 'x', 'y'));
        $this->assertSame(0, Notification::count());
    }

    public function test_notify_company_admins_reaches_the_admin(): void
    {
        app(NotificationService::class)->toCompanyAdmins((int) $this->admin->company_id, 'billing.x', 'Payment', 'Paid');

        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->count());
    }

    public function test_notification_endpoints(): void
    {
        $this->actingAs($this->admin, 'api');
        app(NotificationService::class)->toUser($this->admin->id, $this->admin->company_id, 'test', 'One');
        app(NotificationService::class)->toUser($this->admin->id, $this->admin->company_id, 'test', 'Two');

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 2);

        $list = $this->getJson('/api/v1/notifications')->assertOk()->json('data');
        $this->assertCount(2, $list);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 0);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $other = User::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Other',
            'email' => 'other@acme.test',
            'password' => 'x',
        ]);
        $n = app(NotificationService::class)->toUser($other->id, $this->admin->company_id, 'test', 'Secret');

        $this->actingAs($this->admin, 'api');
        $this->postJson("/api/v1/notifications/{$n->uuid}/read")->assertNotFound();
    }
}
