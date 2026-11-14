<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformAdministrator;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_or_change_account_settings(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->patch('/account/profile', ['name' => 'Hacker'])->assertRedirect(route('login'));
        $this->put('/account/password', ['current_password' => 'test'])->assertRedirect(route('login'));
    }

    public function test_account_updates_own_name_and_records_only_own_activity(): void
    {
        $user = User::factory()->create(['name' => 'Previous Name']);
        $other = User::factory()->create();

        $this->actingAs($user)->patch(route('account.profile.update'), ['name' => 'New Display Name'])
            ->assertRedirect();
        $this->assertSame('New Display Name', $user->fresh()->name);
        $this->assertDatabaseHas('security_events', ['actor_user_id' => $user->id, 'event_type' => 'profile.updated']);

        app(\App\Services\SecurityAudit::class)->record('profile.updated', $other);
        $this->get(route('account.index'))->assertOk()->assertSee('New Display Name');
        $this->get(route('account.index'))->assertDontSee($other->email);
        $this->patch(route('account.profile.update'), ['name' => ' '])->assertSessionHasErrors('name');
    }

    public function test_password_change_requires_current_password_and_rotates_remember_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-safe')]);
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'incorrect',
            'password' => 'Different123!Strong',
            'password_confirmation' => 'Different123!Strong',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('account.password.update'), [
            'current_password' => 'old-password-safe',
            'password' => 'Different123!Strong',
            'password_confirmation' => 'Different123!Strong',
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('Different123!Strong', $fresh->password));
        $this->assertNotSame('old-remember-token', $fresh->remember_token);
        $this->assertDatabaseHas('security_events', ['actor_user_id' => $user->id, 'event_type' => 'password.updated']);
    }

    public function test_account_workspace_list_is_scoped_to_authorized_institutions(): void
    {
        $this->seed(RoleSeeder::class);
        $person = User::factory()->create();
        $visible = Institution::create(['name' => 'Authorized Academy', 'slug' => 'authorized-academy']);
        $secret = Institution::create(['name' => 'Private Academy', 'slug' => 'private-academy']);
        $campus = Campus::create(['institution_id' => $visible->id, 'name' => 'Central', 'slug' => 'central']);

        InstitutionMembership::create(['user_id' => $person->id, 'institution_id' => $visible->id, 'status' => 'active']);
        InstitutionMembership::create(['user_id' => $person->id, 'institution_id' => $secret->id, 'status' => 'suspended']);
        InstitutionRoleAssignment::create([
            'institution_id' => $visible->id,
            'user_id' => $person->id,
            'role_id' => Role::where('code', 'student')->firstOrFail()->id,
            'campus_id' => $campus->id,
        ]);

        $this->actingAs($person)->get(route('account.index'))
            ->assertOk()->assertSee('Authorized Academy')->assertDontSee('Private Academy');
    }

    public function test_platform_console_is_restricted_to_platform_administrators(): void
    {
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get(route('dashboard.platform'))->assertForbidden();

        $administrator = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $administrator->id]);
        $administrator->forceFill([
            'mfa_enabled_at' => now(),
            'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
        ])->save();

        $this->actingAs($administrator)->get(route('dashboard.platform'))->assertOk()->assertSee('Edvora platform');
    }
}
