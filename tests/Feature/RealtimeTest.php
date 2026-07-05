<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\PartnerLevel;
use App\Models\User;
use App\Services\PartnerService;
use App\Services\RealtimeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_token_includes_admin_and_user_rooms(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $res = $this->actingAs($admin)->getJson(route('realtime.token'))->assertOk();
        $rooms = $res->json('data.rooms');

        $this->assertContains('admin', $rooms);
        $this->assertContains('user.'.$admin->id, $rooms);
    }

    public function test_partner_token_includes_partner_room(): void
    {
        $level = PartnerLevel::factory()->create();
        $partner = app(PartnerService::class)->register([
            'name' => 'P', 'email' => 'p@x.test', 'password' => 'password123', 'partner_level_id' => $level->id,
        ]);

        $rooms = $this->actingAs($partner->user)->getJson(route('realtime.token'))->json('data.rooms');

        $this->assertContains('partner.'.$partner->id, $rooms);
        $this->assertNotContains('admin', $rooms);
    }

    public function test_token_signature_is_valid_hmac(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $token = $this->actingAs($admin)->getJson(route('realtime.token'))->json('data.token');
        [$body, $sig] = explode('.', $token, 2);

        $expected = hash_hmac('sha256', $body, (string) env('SOCKET_SECRET'));
        $this->assertSame($expected, $sig);

        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertGreaterThan(time(), $payload['exp']);
        $this->assertSame($admin->id, $payload['uid']);
    }

    public function test_publish_is_safe(): void
    {
        app(RealtimeService::class)->publish(['user.1'], 'notification', ['title' => 'hi']);
        $this->assertTrue(true); // no exception thrown
    }
}
