<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassAssessment;
use App\Models\ClassAssessmentResult;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRegistryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StudentResultStatementTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $campus;
    private AcademicYear $year;
    private User $director;
    private User $administrator;
    private User $studentUser;
    private User $guardian;
    private StudentProfile $student;
    private ClassAssessmentResult $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['slug' => 'statement-school', 'name' => 'Statement School']);
        $this->campus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North',
        ]);
        $this->director = $this->member('director');
        $this->administrator = $this->member('administrator');
        $teacher = $this->member('teacher', $this->campus);
        $this->studentUser = $this->member('student', $this->campus);
        $this->guardian = $this->member('parent', $this->campus);

        $this->year = AcademicYear::create([
            'institution_id' => $this->school->id, 'code' => '2027',
            'name' => 'Academic 2027', 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'created_by' => $this->director->id,
        ]);
        $subject = AcademicSubject::create([
            'institution_id' => $this->school->id, 'code' => 'MTH',
            'name' => 'Mathematics', 'created_by' => $this->director->id,
        ]);
        $class = ClassSection::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'academic_year_id' => $this->year->id, 'academic_subject_id' => $subject->id,
            'name' => 'Grade 9', 'code' => 'G9', 'capacity' => 30,
            'created_by' => $this->director->id,
        ]);
        $this->student = StudentProfile::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'student_number' => 'S-12345', 'display_name' => 'Synthetic Learner',
            'created_by' => $this->director->id,
        ]);
        $this->student->forceFill(['user_id' => $this->studentUser->id])->save();
        $enrollment = ClassEnrollment::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'class_section_id' => $class->id, 'student_profile_id' => $this->student->id,
            'status' => 'active', 'enrolled_by' => $this->director->id, 'enrolled_at' => now(),
        ]);
        $assessment = ClassAssessment::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'class_section_id' => $class->id, 'code' => 'TERM1',
            'title' => 'Term one exam', 'max_points' => '100.00',
            'created_by' => $teacher->id,
        ]);
        $assessment->forceFill([
            'status' => 'published', 'submitted_by' => $teacher->id,
            'submitted_at' => now(), 'published_by' => $this->director->id,
            'published_at' => now(),
        ])->save();
        $this->result = ClassAssessmentResult::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'class_section_id' => $class->id, 'assessment_id' => $assessment->id,
            'class_enrollment_id' => $enrollment->id,
            'points_earned' => '88.50', 'recorded_by' => $teacher->id,
        ]);
        app(StudentRegistryService::class)->verifyGuardian(
            $this->director, $this->student, $this->guardian, 'parent'
        );
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
            'role_id' => Role::query()->where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator'], true)) {
            $user->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    private function requestStatement(User $requester): int
    {
        $this->actingAs($requester)->post(route('students.statements.store', [
            $this->school, $this->student->id,
        ]), ['academic_year_id' => $this->year->id])->assertRedirect();
        return (int) DB::table('student_result_statements')->orderByDesc('id')->value('id');
    }

    public function test_student_requests_and_independent_officer_issues_tamper_evident_snapshot(): void
    {
        $id = $this->requestStatement($this->studentUser);
        $this->assertDatabaseHas('student_result_statements', [
            'id' => $id, 'status' => 'pending', 'requested_by' => $this->studentUser->id,
        ]);
        $this->actingAs($this->studentUser)->post(route('students.statements.store', [
            $this->school, $this->student->id,
        ]), ['academic_year_id' => $this->year->id])->assertSessionHasErrors('academic_year_id');
        $this->actingAs($this->director)->post(route('students.statements.review', [
            $this->school, $this->student->id, $id,
        ]), ['decision' => 'issue'])->assertRedirect();

        $record = DB::table('student_result_statements')->find($id);
        $this->assertSame('issued', $record->status);
        $this->assertNotNull($record->statement_number);
        $this->assertSame(hash('sha256', $record->snapshot_json), $record->snapshot_sha256);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'statement.issued', 'actor_user_id' => $this->director->id,
        ]);
        $this->actingAs($this->guardian)->get(route('students.statements.show', [
            $this->school, $this->student->id, $id,
        ]))->assertOk()->assertSee('Term one exam')->assertSee('88.50');
        $this->get(route('students.statements.export', [
            $this->school, $this->student->id, $id,
        ]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->result->forceFill(['points_earned' => '79.00'])->save();
        $this->get(route('students.statements.show', [
            $this->school, $this->student->id, $id,
        ]))->assertOk()->assertSee('88.50')->assertDontSee('79.00');

        DB::table('student_result_statements')->where('id', $id)->update(['snapshot_sha256' => str_repeat('0', 64)]);
        $this->get(route('students.statements.show', [
            $this->school, $this->student->id, $id,
        ]))->assertStatus(409);
    }

    public function test_director_cannot_approve_own_request_and_reviewer_cannot_revoke_own_issue(): void
    {
        $id = $this->requestStatement($this->director);
        $this->actingAs($this->director)->post(route('students.statements.review', [
            $this->school, $this->student->id, $id,
        ]), ['decision' => 'issue'])->assertForbidden();
        $this->actingAs($this->administrator)->post(route('students.statements.review', [
            $this->school, $this->student->id, $id,
        ]), ['decision' => 'issue'])->assertRedirect();

        $this->post(route('students.statements.revoke', [
            $this->school, $this->student->id, $id,
        ]), ['reason' => 'Review and correction of school records'])->assertForbidden();
        $this->actingAs($this->director)->post(route('students.statements.revoke', [
            $this->school, $this->student->id, $id,
        ]), ['reason' => 'Review and correction of school records'])->assertRedirect();
        $this->assertDatabaseHas('student_result_statements', ['id' => $id, 'status' => 'revoked']);

        $this->actingAs($this->studentUser)->get(route('students.statements.show', [
            $this->school, $this->student->id, $id,
        ]))->assertStatus(409);
        $this->get(route('students.statements.export', [
            $this->school, $this->student->id, $id,
        ]))->assertStatus(409);
        $this->assertDatabaseHas('security_events', ['event_type' => 'statement.revoked']);
    }

    public function test_unrelated_family_member_cannot_see_request_or_issued_results(): void
    {
        $unrelated = $this->member('parent', $this->campus);
        $id = $this->requestStatement($this->studentUser);
        foreach ([
            route('students.statements.index', [$this->school, $this->student->id]),
            route('students.statements.show', [$this->school, $this->student->id, $id]),
            route('students.statements.export', [$this->school, $this->student->id, $id]),
        ] as $url) {
            $this->actingAs($unrelated)->get($url)->assertForbidden();
        }

        $link = $this->student->guardians()->where('guardian_user_id', $this->guardian->id)->firstOrFail();
        app(StudentRegistryService::class)->revokeGuardian($this->director, $this->student, $link);
        $this->actingAs($this->guardian)->get(
            route('students.statements.index', [$this->school, $this->student->id])
        )->assertForbidden();
    }

    public function test_cannot_request_another_school_year_or_another_students_statements(): void
    {
        $foreign = Institution::create(['slug' => 'other-statement-school', 'name' => 'Other']);
        $year = AcademicYear::create([
            'institution_id' => $foreign->id, 'code' => '2027',
            'name' => 'Foreign year', 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'created_by' => $this->director->id,
        ]);
        $this->actingAs($this->studentUser)->post(route('students.statements.store', [
            $this->school, $this->student->id,
        ]), ['academic_year_id' => $year->id])->assertNotFound();
        $this->post(route('students.statements.store', [
            $foreign, $this->student->id,
        ]), ['academic_year_id' => $this->year->id])->assertForbidden();
    }
}
