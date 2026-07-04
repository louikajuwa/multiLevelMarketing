<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@mlm.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'admin@mlm.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.redirect', route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin@mlm.test',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => 'admin@mlm.test',
            'password' => 'wrong',
        ])->assertStatus(422)->assertJsonValidationErrorFor('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'off@mlm.test',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->postJson('/login', [
            'email' => 'off@mlm.test',
            'password' => 'password',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_reach_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($user->name);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/logout')->assertOk();
        $this->assertGuest();
    }
}
