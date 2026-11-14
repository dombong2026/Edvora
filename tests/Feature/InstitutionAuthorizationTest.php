<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformAdministrator;
use App\Models\Role;
use App\Models\User;
use App\Services\InstitutionAccess;
use App\Services\RoleAssignmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstitutionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function institution(string $slug): Institution
    {
        return Institution::create(['slug' => $slug, 'name' => ucfirst($slug), 'status' => 'active']);
    }

    private function campus(Institution $institution, string $slug): Campus
    {
        return Campus::create([
            'institution_id' => $institution->id,
            'slug' => $slug, 'name' => ucfirst($slug), 'status' => 'active',
        ]);
    }

    private function assign(User $user, Institution $institution, AccountRole $role, ?Campus $campus = null): void
    {
        InstitutionMembership::query()->firstOrCreate(
            ['user_id' => $user->id, 'institution_id' => $institution->id],
            ['status' => 'active'],
        );

        InstitutionRoleAssignment::create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'role_id' => Role::where('code', $role->value)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
    }

    public function test_all_fifteen_roles_exist_without_creating_example_users(): void
    {
        $this->assertDatabaseCount('roles', 15);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('roles', ['code' => 'subdirector', 'name' => 'Deputy Director']);
    }

    public function test_student_cannot_open_another_campus_or_institution(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $assigned = $this->campus($first, 'north');
        $unassigned = $this->campus($first, 'south');
        $foreign = $this->campus($second, 'east');
        $student = User::factory()->create();
        $this->assign($student, $first, AccountRole::Student, $assigned);

        $this->actingAs($student)
            ->get(route('dashboard.institution', $first))->assertOk()
            ->assertSee('North')->assertDontSee('South');

        $this->get(route('dashboard.campus', [$first, $assigned]))->assertOk();
        $this->get(route('dashboard.campus', [$first, $unassigned]))->assertForbidden();
        $this->get(route('dashboard.institution', $second))->assertForbidden();
        // Scoped route binding must not resolve a campus through the wrong institution.
        $this->get('/dashboard/institutions/first/campuses/east')->assertNotFound();
        $this->assertFalse(app(InstitutionAccess::class)->canViewCampus($student, $foreign));
    }

    public function test_suspended_membership_blocks_access_even_with_an_old_role(): void
    {
        $institution = $this->institution('school');
        $campus = $this->campus($institution, 'main');
        $teacher = User::factory()->create();
        $this->assign($teacher, $institution, AccountRole::Teacher, $campus);

        InstitutionMembership::query()->where('user_id', $teacher->id)
            ->update(['status' => 'suspended']);

        $this->actingAs($teacher)->get(route('dashboard.institution', $institution))->assertForbidden();
        $this->get(route('dashboard.campus', [$institution, $campus]))->assertForbidden();
    }

    public function test_director_can_open_all_campuses_but_student_cannot(): void
    {
        $institution = $this->institution('college');
        $campus = $this->campus($institution, 'main');
        $director = User::factory()->create();
        $this->assign($director, $institution, AccountRole::Director);

        $this->actingAs($director)->get(route('dashboard.campus', [$institution, $campus]))->assertOk();
    }

    public function test_platform_admin_cannot_bypass_tenant_membership(): void
    {
        $institution = $this->institution('private-school');
        $platform = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $platform->id]);

        $this->actingAs($platform)->get('/dashboard')->assertOk()->assertSee('Edvora platform');
        $this->get(route('dashboard.institution', $institution))->assertForbidden();
    }

    public function test_foreign_campus_assignment_is_rejected_by_database_constraint(): void
    {
        $first = $this->institution('alpha');
        $second = $this->institution('beta');
        $foreign = $this->campus($second, 'other');
        $student = User::factory()->create();
        InstitutionMembership::create(['user_id' => $student->id, 'institution_id' => $first->id, 'status' => 'active']);

        $this->expectException(QueryException::class);
        InstitutionRoleAssignment::create([
            'user_id' => $student->id,
            'institution_id' => $first->id,
            'role_id' => Role::where('code', 'student')->firstOrFail()->id,
            'campus_id' => $foreign->id,
        ]);
    }

    public function test_institution_officer_can_assign_role_but_cannot_create_platform_admin(): void
    {
        $institution = $this->institution('edvora-school');
        $campus = $this->campus($institution, 'main');
        $director = User::factory()->create();
        $teacher = User::factory()->create();
        $this->assign($director, $institution, AccountRole::Director);
        InstitutionMembership::create(['user_id' => $teacher->id, 'institution_id' => $institution->id, 'status' => 'active']);

        $service = app(RoleAssignmentService::class);
        $result = $service->grant($director, $teacher, $institution, AccountRole::Teacher, $campus);

        $this->assertSame($campus->id, $result->campus_id);
        $this->expectException(ValidationException::class);
        $service->grant($director, $teacher, $institution, AccountRole::Admin);
    }

    public function test_unprivileged_student_cannot_grant_roles(): void
    {
        $institution = $this->institution('academy');
        $campus = $this->campus($institution, 'main');
        $student = User::factory()->create();
        $target = User::factory()->create();
        $this->assign($student, $institution, AccountRole::Student, $campus);
        InstitutionMembership::create(['user_id' => $target->id, 'institution_id' => $institution->id, 'status' => 'active']);

        $this->expectException(AuthorizationException::class);
        app(RoleAssignmentService::class)->grant($student, $target, $institution, AccountRole::Teacher, $campus);
    }
}
