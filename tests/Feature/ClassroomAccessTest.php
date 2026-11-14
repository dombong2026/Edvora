<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRegistryService;
use App\Services\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassroomAccessTest extends TestCase
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
        return Campus::create(['institution_id' => $institution->id, 'slug' => $slug,
            'name' => ucfirst($slug), 'status' => 'active']);
    }

    private function account(Institution $institution, string $role, ?Campus $campus = null, ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        InstitutionMembership::create([
            'user_id' => $user->id, 'institution_id' => $institution->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'user_id' => $user->id, 'institution_id' => $institution->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id, 'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator', 'dean', 'manager', 'teacher', 'professor'], true)) {
            $user->forceFill([
                'mfa_secret' => app(Totp::class)->newSecret(),
                'mfa_enabled_at' => now(),
            ])->save();
        }
        return $user;
    }

    private function academic(Institution $institution, User $officer, string $suffix = '1'): array
    {
        $year = AcademicYear::create([
            'institution_id' => $institution->id, 'code' => 'Y'.$suffix,
            'name' => 'School Year '.$suffix, 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'created_by' => $officer->id,
        ]);
        $subject = AcademicSubject::create([
            'institution_id' => $institution->id, 'code' => 'SCI'.$suffix,
            'name' => 'Science '.$suffix, 'created_by' => $officer->id,
        ]);
        return [$year, $subject];
    }

    private function profile(Campus $campus, User $officer, string $number, ?User $user = null): StudentProfile
    {
        $profile = StudentProfile::create([
            'institution_id' => $campus->institution_id, 'campus_id' => $campus->id,
            'student_number' => $number, 'display_name' => 'Student '.$number,
            'created_by' => $officer->id,
        ]);
        if ($user) {
            app(StudentRegistryService::class)->linkStudentAccount($officer, $profile, $user);
        }
        return $profile;
    }

    private function section(Campus $campus, AcademicYear $year, AcademicSubject $subject, User $officer, string $code, int $capacity = 2): ClassSection
    {
        return ClassSection::create([
            'institution_id' => $campus->institution_id, 'campus_id' => $campus->id,
            'academic_year_id' => $year->id, 'academic_subject_id' => $subject->id,
            'code' => $code, 'name' => 'Section '.$code,
            'capacity' => $capacity, 'created_by' => $officer->id,
        ]);
    }

    public function test_academic_year_must_be_activated_before_classes_can_be_created(): void
    {
        $institution = $this->institution('school');
        $campus = $this->campus($institution, 'north');
        $officer = $this->account($institution, 'director');
        [$year, $subject] = $this->academic($institution, $officer);

        $this->actingAs($officer)->post(route('classrooms.store', $institution), [
            'campus_id' => $campus->id, 'academic_year_id' => $year->id,
            'academic_subject_id' => $subject->id, 'code' => 'A1',
            'name' => 'Science Class', 'capacity' => 3,
        ])->assertForbidden();
        $this->assertDatabaseCount('class_sections', 0);

        $this->post(route('academic.years.activate', [$institution, $year]))
            ->assertRedirect(route('academic.index', $institution));
        $this->assertSame('active', $year->fresh()->status);

        $this->post(route('classrooms.store', $institution), [
            'campus_id' => $campus->id, 'academic_year_id' => $year->id,
            'academic_subject_id' => $subject->id, 'code' => 'A1',
            'name' => 'Science Class', 'capacity' => 3,
        ])->assertRedirect();
        $section = ClassSection::firstOrFail();
        $this->assertSame('draft', $section->status);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'class.section.created', 'actor_user_id' => $officer->id,
        ]);
    }

    public function test_teacher_sees_only_assigned_roster_students_and_parents_see_no_classmates(): void
    {
        $school = $this->institution('school');
        $campus = $this->campus($school, 'north');
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $foreignTeacher = $this->account($school, 'teacher', $campus);
        $studentAccount = $this->account($school, 'student', $campus);
        $parent = $this->account($school, 'parent', $campus);
        [$year, $subject] = $this->academic($school, $officer);
        $year->forceFill(['status' => 'active'])->save();
        $section = $this->section($campus, $year, $subject, $officer, 'A1');
        $student = $this->profile($campus, $officer, 'S1', $studentAccount);
        $other = $this->profile($campus, $officer, 'S2');

        $this->actingAs($officer)->post(route('classrooms.teacher.assign', [$school, $section]), [
            'email' => $teacher->email,
        ])->assertRedirect();
        $this->post(route('classrooms.activate', [$school, $section]))->assertRedirect();
        $this->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => $student->student_number,
        ])->assertRedirect();
        $this->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => $other->student_number,
        ])->assertRedirect();

        $this->assertDatabaseCount('class_enrollments', 2);
        $this->assertSame('active', $section->fresh()->status);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'class.student.enrolled', 'actor_user_id' => $officer->id,
        ]);

        $this->actingAs($teacher)->get(route('classrooms.index', $school))
            ->assertOk()->assertSee('Section A1');
        $this->get(route('classrooms.show', [$school, $section]))
            ->assertOk()->assertSee('Authorized class roster')
            ->assertSee('Student S1')->assertSee('Student S2');

        $this->actingAs($foreignTeacher)->get(route('classrooms.index', $school))
            ->assertOk()->assertDontSee('Section A1');
        $this->get(route('classrooms.show', [$school, $section]))->assertForbidden();

        $this->actingAs($studentAccount)->get(route('classrooms.index', $school))
            ->assertOk()->assertSee('Section A1');
        $this->get(route('classrooms.show', [$school, $section]))
            ->assertOk()->assertDontSee('Student S2')->assertDontSee('Authorized class roster');

        app(StudentRegistryService::class)->verifyGuardian($officer, $student, $parent, 'parent');
        $this->actingAs($parent)->get(route('classrooms.index', $school))
            ->assertOk()->assertSee('Section A1');
        $this->get(route('classrooms.show', [$school, $section]))
            ->assertOk()->assertDontSee('Student S2')->assertDontSee('Authorized class roster');

        $link = $student->guardians()->firstOrFail();
        app(StudentRegistryService::class)->revokeGuardian($officer, $student, $link);
        $this->get(route('classrooms.show', [$school, $section]))->assertForbidden();
    }

    public function test_capacity_prevents_overbooking_and_duplicate_enrollment_is_idempotent(): void
    {
        $school = $this->institution('school');
        $campus = $this->campus($school, 'north');
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        [$year, $subject] = $this->academic($school, $officer);
        $year->forceFill(['status' => 'active'])->save();
        $section = $this->section($campus, $year, $subject, $officer, 'B1', 1);

        $service = app(\App\Services\ClassroomService::class);
        $service->assignTeacher($officer, $section, $teacher);
        $service->activate($officer, $section);

        $one = $this->profile($campus, $officer, 'S1');
        $two = $this->profile($campus, $officer, 'S2');
        $this->actingAs($officer)->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => 'S1',
        ])->assertRedirect();
        $this->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => 'S1',
        ])->assertRedirect();
        $this->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => 'S2',
        ])->assertSessionHasErrors('student');
        $this->assertDatabaseCount('class_enrollments', 1);
    }

    public function test_teacher_cannot_manage_class_enrollment_or_create_sections(): void
    {
        $school = $this->institution('school');
        $campus = $this->campus($school, 'north');
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        [$year, $subject] = $this->academic($school, $officer);
        $year->forceFill(['status' => 'active'])->save();
        $section = $this->section($campus, $year, $subject, $officer, 'C1');

        $this->actingAs($teacher)->post(route('classrooms.store', $school), [
            'campus_id' => $campus->id, 'academic_year_id' => $year->id,
            'academic_subject_id' => $subject->id, 'code' => 'C2',
            'name' => 'Not Authorized', 'capacity' => 5,
        ])->assertForbidden();
        $this->post(route('classrooms.activate', [$school, $section]))->assertForbidden();
        $this->post(route('classrooms.students.enroll', [$school, $section]), [
            'student_number' => 'S1',
        ])->assertForbidden();
    }

    public function test_parent_cannot_view_another_class_or_another_institution(): void
    {
        $first = $this->institution('first');
        $second = $this->institution('second');
        $a = $this->campus($first, 'north');
        $b = $this->campus($second, 'east');
        $officer = $this->account($first, 'director');
        $foreign = $this->account($second, 'director');
        $parent = $this->account($first, 'parent', $a);
        [$year1, $subject1] = $this->academic($first, $officer);
        [$year2, $subject2] = $this->academic($second, $foreign, '2');
        $year1->forceFill(['status' => 'active'])->save();
        $year2->forceFill(['status' => 'active'])->save();
        $sectionA = $this->section($a, $year1, $subject1, $officer, 'A');
        $sectionB = $this->section($b, $year2, $subject2, $foreign, 'B');

        $this->actingAs($parent)->get(route('classrooms.show', [$first, $sectionA]))
            ->assertForbidden();
        $this->get(route('classrooms.show', [$first, $sectionB]))->assertNotFound();
        $this->get(route('classrooms.index', $second))->assertForbidden();
    }

    public function test_database_blocks_cross_campus_enrollment(): void
    {
        $school = $this->institution('school');
        $north = $this->campus($school, 'north');
        $south = $this->campus($school, 'south');
        $officer = $this->account($school, 'director');
        [$year, $subject] = $this->academic($school, $officer);
        $year->forceFill(['status' => 'active'])->save();
        $section = $this->section($north, $year, $subject, $officer, 'A');
        $other = $this->profile($south, $officer, 'S2');

        $this->expectException(QueryException::class);
        ClassEnrollment::create([
            'institution_id' => $school->id,
            'campus_id' => $north->id,
            'class_section_id' => $section->id,
            'student_profile_id' => $other->id,
            'enrolled_by' => $officer->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }
    public function test_teacher_without_mfa_cannot_view_class_roster_until_enrollment(): void
    {
        $school = $this->institution('school');
        $campus = $this->campus($school, 'north');
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        [$year, $subject] = $this->academic($school, $officer);
        $year->forceFill(['status' => 'active'])->save();
        $section = $this->section($campus, $year, $subject, $officer, 'MFA');
        app(\App\Services\ClassroomService::class)->assignTeacher($officer, $section, $teacher);

        $teacher->forceFill(['mfa_enabled_at' => null, 'mfa_secret' => null])->save();

        $this->actingAs($teacher)->get(route('classrooms.index', $school))
            ->assertOk()
            ->assertSee('Teacher account verification required')
            ->assertDontSee('Section MFA');

        $this->get(route('classrooms.show', [$school, $section]))->assertForbidden();
    }
}
