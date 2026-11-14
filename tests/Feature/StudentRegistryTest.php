<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentGuardianLink;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRegistryService;
use App\Services\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StudentRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function institution(string $code): Institution
    {
        return Institution::create([
            'name' => strtoupper($code).' School', 'slug' => $code, 'status' => 'active',
        ]);
    }

    private function campus(Institution $institution, string $code): Campus
    {
        return Campus::create([
            'institution_id' => $institution->id, 'name' => ucfirst($code),
            'slug' => $code, 'status' => 'active',
        ]);
    }

    private function account(
        Institution $institution,
        string $role,
        ?Campus $campus = null,
        ?string $email = null
    ): User {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        InstitutionMembership::create([
            'user_id' => $user->id, 'institution_id' => $institution->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'user_id' => $user->id, 'institution_id' => $institution->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);

        if (in_array($role, ['director', 'administrator', 'dean', 'manager'], true)) {
            $user->forceFill([
                'mfa_secret' => app(Totp::class)->newSecret(), 'mfa_enabled_at' => now(),
            ])->save();
        }

        return $user;
    }

    private function student(Campus $campus, User $officer, string $number, string $name): StudentProfile
    {
        return StudentProfile::create([
            'institution_id' => $campus->institution_id,
            'campus_id' => $campus->id,
            'student_number' => $number,
            'display_name' => $name,
            'created_by' => $officer->id,
        ]);
    }

    public function test_authorized_director_creates_student_with_audit_and_deduplicated_number(): void
    {
        $institution = $this->institution('first');
        $campus = $this->campus($institution, 'north');
        $director = $this->account($institution, 'director');

        $this->actingAs($director)->post(route('students.store', $institution), [
            'campus_id' => $campus->id, 'student_number' => 'S-0001',
            'display_name' => 'Synthetic Student One',
        ])->assertRedirect();

        $profile = StudentProfile::firstOrFail();
        $this->assertSame($institution->id, $profile->institution_id);
        $this->assertSame($campus->id, $profile->campus_id);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'student.created', 'actor_user_id' => $director->id,
        ]);

        $this->post(route('students.store', $institution), [
            'campus_id' => $campus->id, 'student_number' => 'S-0001',
            'display_name' => 'Duplicate Number',
        ])->assertSessionHasErrors('student_number');
        $this->assertDatabaseCount('student_profiles', 1);
        $this->get(route('students.index', $institution))
            ->assertOk()->assertSee('Synthetic Student One');
    }

    public function test_teacher_parent_and_student_cannot_create_or_browse_arbitrary_roster(): void
    {
        $institution = $this->institution('first');
        $campus = $this->campus($institution, 'north');
        $director = $this->account($institution, 'director');
        $this->student($campus, $director, 'S-SECRET', 'Only Authorized Student');
        $teacher = $this->account($institution, 'teacher', $campus);
        $parent = $this->account($institution, 'parent', $campus);
        $student = $this->account($institution, 'student', $campus);

        foreach ([$teacher, $parent, $student] as $user) {
            $this->actingAs($user)->post(route('students.store', $institution), [
                'campus_id' => $campus->id, 'student_number' => 'NOT-ALLOWED',
                'display_name' => 'Unauthorized',
            ])->assertForbidden();
            $this->get(route('students.index', $institution))->assertOk()
                ->assertDontSee('Only Authorized Student');
        }
        $this->assertDatabaseCount('student_profiles', 1);
    }

    public function test_student_self_access_requires_a_verified_school_campus_assignment(): void
    {
        $institution = $this->institution('first');
        $campus = $this->campus($institution, 'north');
        $other = $this->campus($institution, 'south');
        $director = $this->account($institution, 'director');
        $studentAccount = $this->account($institution, 'student', $campus, 'student@example.test');

        $profile = $this->student($campus, $director, 'S-0001', 'My Student Record');
        $private = $this->student($other, $director, 'S-0002', 'Other Campus Record');

        $this->actingAs($director)->post(route('students.account.link', [$institution, $profile]), [
            'email' => 'student@example.test',
        ])->assertRedirect();

        $this->assertSame($studentAccount->id, $profile->fresh()->user_id);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'student.account_linked', 'subject_user_id' => $studentAccount->id,
        ]);

        $this->actingAs($studentAccount)->get(route('students.index', $institution))->assertOk()
            ->assertSee('My Student Record')->assertDontSee('Other Campus Record');
        $this->get(route('students.show', [$institution, $profile]))->assertOk();
        $this->get(route('students.show', [$institution, $private]))->assertForbidden();
    }

    public function test_guardian_access_requires_independent_officer_approval_and_can_be_revoked(): void
    {
        $institution = $this->institution('first');
        $campus = $this->campus($institution, 'north');
        $director = $this->account($institution, 'director');
        $parent = $this->account($institution, 'parent', $campus, 'guardian@example.test');
        $child = $this->student($campus, $director, 'S-0001', 'Linked Child');
        $other = $this->student($campus, $director, 'S-0002', 'Unrelated Child');

        $this->actingAs($parent)->get(route('students.index', $institution))->assertOk()
            ->assertDontSee('Linked Child');
        $this->get(route('students.show', [$institution, $child]))->assertForbidden();

        $this->actingAs($director)->post(route('students.guardians.verify', [$institution, $child]), [
            'email' => 'guardian@example.test', 'relationship' => 'legal_guardian',
        ])->assertRedirect();

        $link = StudentGuardianLink::firstOrFail();
        $this->assertNotNull($link->verified_at);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'guardian.verified', 'subject_user_id' => $parent->id,
        ]);

        $this->actingAs($parent)->get(route('students.index', $institution))->assertOk()
            ->assertSee('Linked Child')->assertDontSee('Unrelated Child');
        $this->get(route('students.show', [$institution, $child]))->assertOk();
        $this->get(route('students.show', [$institution, $other]))->assertForbidden();

        $this->actingAs($director)->delete(route('students.guardians.revoke', [$institution, $child, $link]))
            ->assertRedirect();
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'guardian.revoked', 'actor_user_id' => $director->id,
        ]);

        $this->actingAs($parent)->get(route('students.show', [$institution, $child]))->assertForbidden();
        $this->get(route('students.index', $institution))->assertDontSee('Linked Child');
    }

    public function test_parent_from_another_campus_cannot_be_linked(): void
    {
        $institution = $this->institution('first');
        $north = $this->campus($institution, 'north');
        $south = $this->campus($institution, 'south');
        $director = $this->account($institution, 'director');
        $parent = $this->account($institution, 'parent', $south);
        $student = $this->student($north, $director, 'S-0001', 'North Student');

        $this->expectException(AuthorizationException::class);
        app(StudentRegistryService::class)->verifyGuardian($director, $student, $parent, 'parent');
    }

    public function test_different_institution_and_suspended_accounts_are_always_denied(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $north = $this->campus($first, 'north');
        $east = $this->campus($second, 'east');
        $officer = $this->account($first, 'director');
        $foreignOfficer = $this->account($second, 'director');
        $firstStudent = $this->student($north, $officer, 'S1', 'Private First');
        $foreign = $this->student($east, $foreignOfficer, 'S2', 'Foreign Student');

        $this->actingAs($officer)->get(route('students.show', [$first, $foreign]))->assertNotFound();
        $this->get(route('students.index', $first))->assertOk()->assertDontSee('Foreign Student');

        InstitutionMembership::where('user_id', $officer->id)->where('institution_id', $first->id)
            ->update(['status' => 'suspended']);
        $this->get(route('students.index', $first))->assertForbidden();
        $this->get(route('students.show', [$first, $firstStudent]))->assertForbidden();
    }

    public function test_database_rejects_cross_institution_student_campus(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $otherCampus = $this->campus($second, 'east');
        $officer = $this->account($first, 'director');

        $this->expectException(QueryException::class);
        StudentProfile::create([
            'institution_id' => $first->id,
            'campus_id' => $otherCampus->id,
            'student_number' => 'CROSS-BOUNDARY',
            'display_name' => 'Wrong Campus',
            'created_by' => $officer->id,
        ]);
    }

    public function test_database_rejects_foreign_student_guardian_link(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $otherCampus = $this->campus($second, 'east');
        $campus = $this->campus($first, 'north');
        $officer = $this->account($first, 'director');
        $student = $this->student($otherCampus, $this->account($second, 'director'), 'S2', 'Foreign Student');
        $parent = $this->account($first, 'parent', $campus);

        $this->expectException(QueryException::class);
        StudentGuardianLink::create([
            'institution_id' => $first->id,
            'student_profile_id' => $student->id,
            'guardian_user_id' => $parent->id,
            'relationship' => 'parent',
            'verified_by' => $officer->id,
            'verified_at' => now(),
        ]);
    }
}
