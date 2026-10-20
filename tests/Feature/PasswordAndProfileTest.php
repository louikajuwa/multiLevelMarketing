<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(): User
    {
        $u = User::factory()->create(['password' => 'old-password-123']);
        $u->assignRole(RoleName::Admin->value);

        return $u;
    }

    public function test_forgot_password_sends_notification(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->postJson(route('password.email'), ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_with_valid_token_works(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->postJson(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'brand-new-pass-123', 'password_confirmation' => 'brand-new-pass-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-123', $user->fresh()->password));
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        $user = $this->user();

        $this->postJson(route('password.update'), [
            'token' => 'not-a-real-token', 'email' => $user->email,
            'password' => 'brand-new-pass-123', 'password_confirmation' => 'brand-new-pass-123',
        ])->assertStatus(422)->assertJsonValidationErrorFor('email');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson(route('profile.password'), [
            'current_password' => 'wrong', 'password' => 'brand-new-pass-123', 'password_confirmation' => 'brand-new-pass-123',
        ])->assertStatus(422)->assertJsonValidationErrorFor('current_password');

        $this->actingAs($user)->putJson(route('profile.password'), [
            'password' => 'brand-new-pass-123', 'password_confirmation' => 'brand-new-pass-123',
        ])->assertStatus(422)->assertJsonValidationErrorFor('current_password');

        $this->actingAs($user)->putJson(route('profile.password'), [
            'current_password' => 'old-password-123', 'password' => 'brand-new-pass-123', 'password_confirmation' => 'brand-new-pass-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-123', $user->fresh()->password));
    }

    public function test_profile_update_validates_unique_email(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user)->putJson(route('profile.update'), ['name' => 'Me', 'email' => $other->email])
            ->assertStatus(422)->assertJsonValidationErrorFor('email');

        // Keeping your own email is fine.
        $this->actingAs($user)->putJson(route('profile.update'), ['name' => 'Me', 'email' => $user->email])->assertOk();
    }

    public function test_unicode_name_is_accepted(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson(route('profile.update'), ['name' => 'Zoë Müller 山田太郎', 'email' => $user->email])->assertOk();

        $this->assertSame('Zoë Müller 山田太郎', $user->fresh()->name);
    }

    public function test_overlong_name_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson(route('profile.update'), ['name' => str_repeat('a', 256), 'email' => $user->email])
            ->assertStatus(422)->assertJsonValidationErrorFor('name');
    }
}
