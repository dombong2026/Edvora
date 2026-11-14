<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassAssessment;
use App\Models\ClassAssessmentResult;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\ClassAssessmentService;
use App\Services\StudentRegistryService;
use App\Services\Totp;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AssessmentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2027-06-12 12:00:00'));
    }

    private function school(string $slug = 'school'): Institution
    {
        return Institution::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
    }

    private function campus(Institution $school, string $slug = 'main'): Campus
    {
        return Campus::create([
            'institution_id' => $school->id, 'slug' => $slug,
            'name' => ucfirst($slug), 'status' => 'active',
        ]);
    }

    private function account(Institution $school, string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $school->id, 'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id, 'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator', 'teacher', 'professor'], true)) {
            $user->forceFill([
                'mfa_secret' => app(Totp::class)->newSecret(), 'mfa_enabled_at' => now(),
            ])->save();
        }
        return $user;
    }

    private function class(Campus $campus, User $officer, User $teacher, string $code = 'A'): ClassSection
    {
        $year = AcademicYear::create([
            'institution_id' => $campus->institution_id, 'code' => 'Y'.$code,
            'name' => 'Year '.$code, 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
            'created_by' => $officer->id,
        ]);
        $year->forceFill(['status' => 'active'])->save();
        $subject = AcademicSubject::create([
            'institution_id' => $campus->institution_id,
            'code' => 'SUB'.$code, 'name' => 'Subject '.$code, 'created_by' => $officer->id,
        ]);
        $class = ClassSection::create([
            'institution_id' => $campus->institution_id, 'campus_id' => $campus->id,
            'academic_year_id' => $year->id, 'academic_subject_id' => $subject->id,
            'code' => $code, 'name' => 'Class '.$code, 'capacity' => 20, 'created_by' => $officer->id,
        ]);
        app(ClassroomService::class)->assignTeacher($officer, $class, $teacher);
        app(ClassroomService::class)->activate($officer, $class);
        return $class->fresh();
    }

    private function enroll(Campus $campus, ClassSection $class, User $officer, string $code, ?User $user = null): \App\Models\ClassEnrollment
    {
        $profile = StudentProfile::create([
            'institution_id' => $campus->institution_id, 'campus_id' => $campus->id,
            'student_number' => $code, 'display_name' => 'Student '.$code,
            'created_by' => $officer->id,
        ])->fresh();
        if ($user) {
            app(StudentRegistryService::class)->linkStudentAccount($officer, $profile, $user);
        }

        return app(ClassroomService::class)->enroll($officer, $class, $profile);
    }

    public function test_independent_publication_and_private_student_guardian_results(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $studentAccount = $this->account($school, 'student', $campus);
        $guardian = $this->account($school, 'parent', $campus);
        $unlinkedParent = $this->account($school, 'parent', $campus);
        $class = $this->class($campus, $director, $teacher);
        $enrollment1 = $this->enroll($campus, $class, $director, 'S1', $studentAccount);
        $enrollment2 = $this->enroll($campus, $class, $director, 'S2');

        app(StudentRegistryService::class)->verifyGuardian($director, $enrollment1->student, $guardian, 'parent');

        $this->actingAs($teacher)->post(route('assessments.store', [$school, $class]), [
            'code' => 'QUIZ1', 'title' => 'Learning Quiz',
            'max_points' => '100',
        ])->assertRedirect();

        $assessment = ClassAssessment::firstOrFail();
        $this->assertSame('draft', $assessment->status);

        $this->actingAs($studentAccount)->get(route('assessments.show', [$school, $class, $assessment]))
            ->assertForbidden();

        $this->actingAs($teacher)->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment1->id, 'points_earned' => '96.25',
        ])->assertRedirect();
        $this->post(route('assessments.submit', [$school, $class, $assessment]))
            ->assertSessionHasErrors('assessment');

        $this->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment2->id, 'points_earned' => '53.00',
        ])->assertRedirect();
        $this->post(route('assessments.submit', [$school, $class, $assessment]))->assertRedirect();
        $this->assertSame('submitted', $assessment->fresh()->status);
        $this->assertDatabaseCount('class_assessment_results', 2);

        $this->actingAs($studentAccount)->get(route('assessments.index', [$school, $class]))
            ->assertOk()->assertDontSee('Learning Quiz');
        $this->get(route('assessments.show', [$school, $class, $assessment]))->assertForbidden();

        $this->actingAs($teacher)->post(route('assessments.publish', [$school, $class, $assessment]))
            ->assertForbidden();

        $this->actingAs($director)->post(route('assessments.publish', [$school, $class, $assessment]))
            ->assertRedirect();

        $this->assertSame('published', $assessment->fresh()->status);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'assessment.published', 'actor_user_id' => $director->id,
        ]);

        $this->actingAs($studentAccount)->get(route('assessments.show', [$school, $class, $assessment]))
            ->assertOk()->assertSee('Student S1')->assertSee('96.25')->assertDontSee('Student S2')->assertDontSee('53.00');

        $this->actingAs($guardian)->get(route('assessments.show', [$school, $class, $assessment]))
            ->assertOk()->assertSee('Student S1')->assertDontSee('Student S2');

        $this->actingAs($unlinkedParent)->get(route('assessments.index', [$school, $class]))->assertForbidden();
        $this->assertDatabaseMissing('security_events', ['metadata' => '96.25']);
    }

    public function test_invalid_scores_rejected_and_submitted_results_frozen(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $director, $teacher);
        $enrollment = $this->enroll($campus, $class, $director, 'S1');

        $service = app(ClassAssessmentService::class);
        $assessment = $service->create($teacher, $class, [
            'code' => 'TEST', 'title' => 'Scoring rules', 'max_points' => '20.00',
        ])->fresh();

        $this->actingAs($teacher)->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment->id, 'points_earned' => '20.01',
        ])->assertSessionHasErrors('points_earned');

        $this->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment->id, 'points_earned' => '-1',
        ])->assertSessionHasErrors('points_earned');

        $this->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment->id, 'points_earned' => '0.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('class_assessment_results', [
            'assessment_id' => $assessment->id, 'points_earned' => '0',
        ]);

        $this->post(route('assessments.submit', [$school, $class, $assessment]))->assertRedirect();

        $this->post(route('assessments.record', [$school, $class, $assessment]), [
            'enrollment_id' => $enrollment->id, 'points_earned' => '5',
        ])->assertSessionHasErrors('result');

        $this->assertDatabaseCount('class_assessment_results', 1);
    }

    public function test_unassigned_teacher_and_foreign_institution_cannot_access_gradebook(): void
    {
        $school = $this->school('first');
        $otherSchool = $this->school('second');
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $unassigned = $this->account($school, 'teacher', $campus);
        $stranger = $this->account($otherSchool, 'director');
        $class = $this->class($campus, $officer, $teacher);

        $this->actingAs($unassigned)->post(route('assessments.store', [$school, $class]), [
            'code' => 'TEST', 'title' => 'Denied', 'max_points' => '30',
        ])->assertForbidden();

        $assessment = app(ClassAssessmentService::class)->create($teacher, $class, [
            'code' => 'TEST', 'title' => 'Private', 'max_points' => '30',
        ]);
        $this->get(route('assessments.show', [$school, $class, $assessment]))->assertForbidden();

        $this->actingAs($stranger)->get(route('assessments.index', [$school, $class]))
            ->assertForbidden();
    }

    public function test_revoked_guardian_loses_approved_grade_access(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $parent = $this->account($school, 'parent', $campus);
        $class = $this->class($campus, $director, $teacher);
        $enrollment = $this->enroll($campus, $class, $director, 'S1');
        $service = app(StudentRegistryService::class);
        $link = $service->verifyGuardian($director, $enrollment->student, $parent, 'parent');

        $grades = app(ClassAssessmentService::class);
        $assessment = $grades->create($teacher, $class, [
            'code' => 'MIDTERM', 'title' => 'Private Midterm', 'max_points' => '100',
        ]);
        $grades->record($teacher, $assessment, $enrollment, '75');
        $grades->submit($teacher, $assessment);
        $grades->publish($director, $assessment);

        $this->actingAs($parent)->get(route('assessments.show', [$school, $class, $assessment]))
            ->assertOk()->assertSee('Student S1');

        $service->revokeGuardian($director, $enrollment->student, $link);
        $this->get(route('assessments.show', [$school, $class, $assessment]))->assertForbidden();
    }

    public function test_database_rejects_score_on_enrollment_in_another_class(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $a = $this->class($campus, $director, $teacher, 'A');
        $b = $this->class($campus, $director, $teacher, 'B');
        $enrollment = $this->enroll($campus, $b, $director, 'S2');
        $assessment = app(ClassAssessmentService::class)->create($teacher, $a, [
            'code' => 'QUIZ', 'title' => 'Private Quiz', 'max_points' => '50',
        ]);

        $this->expectException(QueryException::class);
        ClassAssessmentResult::create([
            'institution_id' => $school->id, 'campus_id' => $campus->id,
            'class_section_id' => $a->id, 'assessment_id' => $assessment->id,
            'class_enrollment_id' => $enrollment->id,
            'points_earned' => '10.00', 'recorded_by' => $teacher->id,
        ]);
    }
}
