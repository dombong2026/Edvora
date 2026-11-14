<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformAdministrator;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaService;
use App\Services\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfc_totp_vector_and_replay_window(): void
    {
        $totp = app(Totp::class);
        // RFC 6238 SHA-1 vector at Unix time 59, truncated to six digits.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $totp->codeAt($secret, 1));
        $this->assertSame(1, $totp->verify($secret, '287082', null, 59));
        $this->assertNull($totp->verify($secret, '287082', 1, 59));
        $this->assertNull($totp->verify($secret, 'not-a-code', null, 59));
    }

    public function test_platform_admin_is_forced_to_enroll_before_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'CorrectPassword123!']);
        PlatformAdministrator::create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('mfa.setup'));
        $this->get(route('mfa.setup'))->assertOk()
            ->assertSee('Protect your Edvora account');

        $user->refresh();
        $this->assertNotNull($user->mfa_pending_secret);
        $this->assertNotSame($user->mfa_pending_secret, $user->getRawOriginal('mfa_pending_secret'));
    }

    public function test_mfa_enrollment_requires_password_and_a_fresh_code(): void
    {
        $user = User::factory()->create(['password' => 'CorrectPassword123!']);
        PlatformAdministrator::create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('mfa.setup'))->assertOk();
        $secret = $user->fresh()->mfa_pending_secret;
        $code = app(Totp::class)->codeAt($secret, intdiv(time(), 30));

        $this->post(route('mfa.enable'), [
            'password' => 'WrongPassword123!', 'code' => $code,
        ])->assertSessionHasErrors('password');

        $this->post(route('mfa.enable'), [
            'password' => 'CorrectPassword123!', 'code' => $code,
        ])->assertRedirect(route('mfa.recovery'));

        $user->refresh();
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertNotSame($secret, $user->getRawOriginal('mfa_secret'));
        $this->assertSame(8, $user->recoveryCodes()->count());
        $this->get(route('mfa.recovery'))->assertOk()->assertSee('Save your recovery codes');
        $this->get('/dashboard')->assertOk()->assertSee('Edvora platform');
        $this->assertFalse(app(MfaService::class)->verifyAndConsume($user, $code));
    }

    public function test_enabled_mfa_requires_a_second_step_and_recovery_code_is_one_use(): void
    {
        $user = User::factory()->create(['password' => 'CorrectPassword123!']);
        $secret = app(Totp::class)->newSecret();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled_at' => now(),
            'mfa_last_counter' => null,
        ])->save();
        $plainRecovery = 'ABCDEF123456ABCDEF123456';
        $user->recoveryCodes()->create(['code_hash' => hash('sha256', $plainRecovery)]);

        $this->post('/login', [
            'email' => $user->email, 'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        $this->post(route('mfa.challenge.complete'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('mfa.challenge.complete'), ['code' => $plainRecovery])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->recoveryCodes()->first()->fresh()->used_at);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post('/login', [
            'email' => $user->email, 'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.challenge.complete'), ['code' => $plainRecovery])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_privileged_institution_role_requires_mfa_but_nonprivileged_role_does_not(): void
    {
        $this->seed(RoleSeeder::class);
        $institution = Institution::create(['name' => 'Campus One', 'slug' => 'campus-one']);
        $director = User::factory()->create();
        $student = User::factory()->create();

        foreach ([[$director, AccountRole::Director], [$student, AccountRole::Student]] as [$user, $role]) {
            InstitutionMembership::create(['user_id' => $user->id, 'institution_id' => $institution->id, 'status' => 'active']);
            InstitutionRoleAssignment::create([
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'role_id' => Role::where('code', $role->value)->first()->id,
            ]);
        }

        $this->actingAs($director)->get(route('dashboard.institution', $institution))
            ->assertRedirect(route('mfa.setup'));
        $this->actingAs($student)->get(route('dashboard.institution', $institution))
            ->assertOk();
    }
}
