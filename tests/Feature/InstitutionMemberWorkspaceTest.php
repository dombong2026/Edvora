<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionMemberWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function setupSchool(): array
    {
        $school = Institution::create(['name' => 'Alpha School', 'slug' => 'alpha-school']);
        $campus = Campus::create(['institution_id' => $school->id, 'name' => 'Main', 'slug' => 'main']);
        $director = User::factory()->create(['name' => 'Authorized Director']);
        $member = User::factory()->create(['name' => 'Potential Teacher']);
        foreach ([$director, $member] as $user) {
            InstitutionMembership::create(['institution_id' => $school->id, 'user_id' => $user->id, 'status' => 'active']);
        }
        InstitutionRoleAssignment::create([
            'institution_id' => $school->id,
            'user_id' => $director->id,
            'role_id' => Role::where('code', 'director')->firstOrFail()->id,
            'campus_id' => null,
        ]);
        $director->forceFill(['mfa_enabled_at' => now(), 'mfa_secret' => app(\App\Services\Totp::class)->newSecret()])->save();

        return [$school, $campus, $director, $member];
    }

    public function test_director_can_search_members_and_grant_a_scoped_role(): void
    {
        [$school, $campus, $director, $member] = $this->setupSchool();
        $this->actingAs($director)->get(route('members.index', $school))
            ->assertOk()->assertSee('Potential Teacher')->assertSee('Assign role');
        $this->get(route('members.index', [$school, 'q' => 'Potential']))
            ->assertOk()->assertSee('Potential Teacher');

        $this->post(route('members.grant', $school), [
            'user_id' => $member->id, 'role' => 'teacher', 'campus_id' => $campus->id,
        ])->assertRedirect(route('members.index', $school));

        $this->assertDatabaseHas('institution_role_assignments', [
            'user_id' => $member->id, 'institution_id' => $school->id,
            'campus_id' => $campus->id, 'role_id' => Role::where('code', 'teacher')->firstOrFail()->id,
        ]);
        $this->assertDatabaseHas('security_events', ['event_type' => 'role.granted', 'actor_user_id' => $director->id]);
    }

    public function test_cannot_grant_nonleadership_role_without_a_campus(): void
    {
        [$school, $campus, $director, $member] = $this->setupSchool();
        $this->actingAs($director)->post(route('members.grant', $school), [
            'user_id' => $member->id, 'role' => 'teacher',
        ])->assertSessionHasErrors('campus_id');
    }

    public function test_unprivileged_and_foreign_members_are_not_exposed_or_assignable(): void
    {
        [$school, $campus, $director, $member] = $this->setupSchool();
        $other = Institution::create(['name' => 'Private School', 'slug' => 'private-school']);
        $stranger = User::factory()->create(['name' => 'Foreign User']);
        InstitutionMembership::create(['institution_id' => $other->id, 'user_id' => $stranger->id, 'status' => 'active']);

        $this->actingAs($stranger)->get(route('members.index', $school))->assertForbidden();
        $this->actingAs($director)->get(route('members.index', $school))
            ->assertDontSee('Foreign User')->assertDontSee('Private School');
        $this->post(route('members.grant', $school), [
            'user_id' => $stranger->id, 'role' => 'teacher', 'campus_id' => $campus->id,
        ])->assertNotFound();
    }
}
