<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\ModuleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ModuleSeeder::class, PlanSeeder::class]);
    }

    public function test_public_plans_are_returned_without_auth(): void
    {
        $plans = $this->getJson('/api/v1/public/plans')->assertOk()->json('data');

        $this->assertNotEmpty($plans);
        $this->assertArrayHasKey('base_price', $plans[0]);
        $this->assertArrayHasKey('modules', $plans[0]);
    }

    public function test_contact_form_validates(): void
    {
        $this->postJson('/api/v1/public/contact', ['name' => 'x'])->assertStatus(422);
    }
}
