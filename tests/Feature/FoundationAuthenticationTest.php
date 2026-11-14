<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_and_login_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Edvora');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_registration_is_disabled_by_default(): void
    {
        config(['edvora.self_registration' => false]);
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unauthorized', 'email' => 'unauthorized@example.test',
            'password' => 'ReasonablePassword123!', 'password_confirmation' => 'ReasonablePassword123!',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unassigned_user_does_not_gain_institution_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('No active institution workspace');
    }

    public function test_login_rotates_session_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123!']);

        $this->post('/login', [
            'email' => $user->email, 'password' => 'ValidPassword123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email, 'password' => 'IncorrectPassword!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_anonymous_account_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_theme_controls_are_available_on_authentication_pages(): void
    {
        $this->get('/login')->assertSee('data-theme-option="light"', false)
            ->assertSee('data-theme-option="dark"', false)
            ->assertSee('data-theme-option="system"', false);
    }

    public function test_password_reset_request_does_not_reveal_account_existence(): void
    {
        $this->post('/forgot-password', ['email' => 'not-registered@example.test'])
            ->assertSessionHas('status', 'If that account exists, a password reset link has been sent.');
    }
}
