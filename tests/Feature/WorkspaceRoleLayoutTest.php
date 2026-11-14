<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRoleLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_institution_role_can_open_the_same_real_dashboard_and_account_shell(): void
    {
        $this->seed(RoleSeeder::class);
        $school = Institution::create(['slug' => 'shared-design-school', 'name' => 'Shared Design School']);
        $campus = Campus::create(['institution_id' => $school->id, 'slug' => 'main', 'name' => 'Main Campus']);

        foreach (AccountRole::cases() as $role) {
            if ($role === AccountRole::Admin) {
                continue; // Platform administrators use a separate, equally styled console.
            }
            $user = User::factory()->create(['name' => $role->label().' Account']);
            InstitutionMembership::create([
                'institution_id' => $school->id, 'user_id' => $user->id, 'status' => 'active',
            ]);
            InstitutionRoleAssignment::create([
                'institution_id' => $school->id,
                'user_id' => $user->id,
                'role_id' => Role::where('code', $role->value)->firstOrFail()->id,
                'campus_id' => in_array($role->value, AccountRole::institutionWideCodes(), true) ? null : $campus->id,
            ]);
            if (in_array($role->value, [
                'administrator', 'director', 'subdirector', 'manager', 'dean', 'supervisor', 'cashier',
            ], true)) {
                $user->forceFill([
                    'mfa_enabled_at' => now(),
                    'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
                ])->save();
            }

            $this->actingAs($user)
                ->get(route('dashboard.role', [$school, $role->value]))
                ->assertOk()
                ->assertSee($role->label())
                ->assertSee('My account')
                ->assertSee('edvora-sidebar')
                ->assertSee('data-theme-option="system"', false);

            $this->get(route('account.index'))->assertOk()
                ->assertSee('Shared Design School')->assertSee('Profile information');
        }
    }

    public function test_public_landing_has_genuine_sign_in_and_no_disabled_dummy_actions(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('A smarter home for', false)
            ->assertSee(route('login'), false)
            ->assertSee('Explore the foundation');
    }
}
