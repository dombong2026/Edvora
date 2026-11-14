<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ExamScheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $campus;
    private ClassSection $section;
    private User $teacher;
    private User $director;
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['slug' => 'exam-school', 'name' => 'Exam School']);
        $this->campus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North',
        ]);
        $this->teacher = $this->member('teacher', $this->campus);
        $this->director = $this->member('director');
        $this->administrator = $this->member('administrator');

        $year = AcademicYear::create([
            'institution_id' => $this->school->id, 'code' => '2027',
            'name' => 'Year 2027', 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'created_by' => $this->director->id,
        ]);
        $year->forceFill(['status' => 'active'])->save();
        $subject = AcademicSubject::create([
            'institution_id' => $this->school->id, 'code' => 'MATH',
            'name' => 'Mathematics', 'created_by' => $this->director->id,
        ]);
        $this->section = ClassSection::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'academic_year_id' => $year->id, 'academic_subject_id' => $subject->id,
            'code' => 'MATH-A', 'name' => 'Mathematics A',
            'capacity' => 20, 'created_by' => $this->director->id,
        ]);
        $this->section->forceFill(['status' => 'active', 'teacher_user_id' => $this->teacher->id])->save();
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'user_id' => $user->id, 'institution_id' => $this->school->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['teacher', 'administrator', 'director'], true)) {
            $user->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    private function input(string $code = 'EXAM-A'): array
    {
        return [
            'code' => $code, 'title' => 'Mid-year science exam',
            'exam_date' => '2027-06-18',
            'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => 'A-01',
        ];
    }

    private function create(string $code = 'EXAM-A'): int
    {
        $this->actingAs($this->teacher)->post(
            route('exams.store', [$this->school, $this->section]), $this->input($code)
        )->assertRedirect();
        return (int) DB::table('class_exam_schedules')->where('code', $code)->value('id');
    }

    public function test_class_teacher_can_draft_submit_and_separate_leader_can_publish(): void
    {
        $this->actingAs($this->teacher)->get(route('exams.index', [$this->school, $this->section]))
            ->assertOk()->assertSee('Examination timetable');
        $id = $this->create();
        $this->assertDatabaseHas('class_exam_schedules', ['id' => $id, 'status' => 'draft']);

        $this->actingAs($this->teacher)->post(
            route('exams.submit', [$this->school, $this->section, $id])
        )->assertRedirect();
        $this->assertDatabaseHas('class_exam_schedules', ['id' => $id, 'status' => 'submitted']);

        $this->actingAs($this->director)->post(
            route('exams.review', [$this->school, $this->section, $id]),
            ['decision' => 'publish']
        )->assertRedirect();
        $this->assertDatabaseHas('class_exam_schedules', ['id' => $id, 'status' => 'published']);
        $this->assertDatabaseHas('security_events', ['event_type' => 'exam.schedule.published']);
        $this->post(route('exams.review', [$this->school, $this->section, $id]),
            ['decision' => 'publish'])->assertSessionHasErrors('exam');
    }

    public function test_exam_room_conflicts_are_checked_at_independent_publication(): void
    {
        $id = $this->create();
        $this->actingAs($this->teacher)->post(
            route('exams.submit', [$this->school, $this->section, $id])
        )->assertRedirect();
        $this->actingAs($this->director)->post(
            route('exams.review', [$this->school, $this->section, $id]), ['decision' => 'publish']
        )->assertRedirect();

        $second = ClassSection::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'academic_year_id' => $this->section->academic_year_id,
            'academic_subject_id' => $this->section->academic_subject_id,
            'code' => 'MATH-B', 'name' => 'Mathematics B',
            'capacity' => 20, 'created_by' => $this->director->id,
        ]);
        $second->forceFill(['status' => 'active'])->save();

        $this->actingAs($this->administrator)->post(route('exams.store', [$this->school, $second]),
            $this->input('EXAM-B'))->assertRedirect();
        $secondId = (int) DB::table('class_exam_schedules')->where('code', 'EXAM-B')->value('id');
        $this->post(route('exams.submit', [$this->school, $second, $secondId]))->assertRedirect();

        $this->actingAs($this->director)->post(
            route('exams.review', [$this->school, $second, $secondId]), ['decision' => 'publish']
        )->assertSessionHasErrors('room');
        $this->assertDatabaseHas('class_exam_schedules', [
            'id' => $secondId, 'status' => 'submitted',
        ]);
    }

    public function test_student_cannot_create_or_view_unpublished_class_exams(): void
    {
        $student = $this->member('student', $this->campus);
        $id = $this->create();
        $this->actingAs($student)->post(route('exams.store', [$this->school, $this->section]),
            $this->input('BLOCKED'))->assertForbidden();
        $this->get(route('exams.index', [$this->school, $this->section]))->assertForbidden();
        $this->post(route('exams.review', [$this->school, $this->section, $id]),
            ['decision' => 'publish'])->assertForbidden();
    }

    public function test_author_of_exam_cannot_publish_own_submitted_schedule(): void
    {
        $this->actingAs($this->director)->post(
            route('exams.store', [$this->school, $this->section]), $this->input()
        )->assertRedirect();
        $id = (int) DB::table('class_exam_schedules')->value('id');
        $this->post(route('exams.submit', [$this->school, $this->section, $id]))->assertRedirect();
        $this->post(route('exams.review', [$this->school, $this->section, $id]),
            ['decision' => 'publish'])->assertForbidden();

        $this->actingAs($this->administrator)->post(
            route('exams.review', [$this->school, $this->section, $id]),
            ['decision' => 'publish']
        )->assertRedirect();
    }
}
