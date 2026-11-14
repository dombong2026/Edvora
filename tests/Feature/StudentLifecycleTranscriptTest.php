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
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StudentLifecycleTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $north;
    private Campus $south;
    private User $director;
    private User $reviewer;
    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['slug' => 'lifecycle', 'name' => 'Lifecycle School']);
        $this->north = Campus::create(['institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North']);
        $this->south = Campus::create(['institution_id' => $this->school->id, 'slug' => 'south', 'name' => 'South']);
        $this->director = $this->member('director');
        $this->reviewer = $this->member('administrator');
        $this->student = StudentProfile::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->north->id,
            'student_number' => 'L-0001', 'display_name' => 'Synthetic Record',
            'created_by' => $this->director->id,
        ]);
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator', 'dean', 'manager'], true)) {
            $user->forceFill([
                'mfa_enabled_at' => now(), 'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    private function requestChange(string $kind, array $extra = []): int
    {
        $this->actingAs($this->director)->post(route('students.lifecycle.store', [
            $this->school, $this->student->id,
        ]), array_merge([
            'kind' => $kind, 'reason' => 'Reviewed academic change requested',
        ], $extra))->assertRedirect();
        return (int) DB::table('student_lifecycle_requests')->orderByDesc('id')->value('id');
    }

    private function review(int $id, string $decision = 'approve')
    {
        return $this->actingAs($this->reviewer)->post(route('students.lifecycle.review', [
            $this->school, $this->student->id, $id,
        ]), ['decision' => $decision]);
    }

    public function test_independent_withdrawal_deactivates_enrollment_and_is_audited(): void
    {
        $id = $this->requestChange('withdrawal');
        $this->actingAs($this->director)->post(route('students.lifecycle.review', [
            $this->school, $this->student->id, $id,
        ]), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('active', $this->student->fresh()->status);

        $this->review($id)->assertRedirect();
        $this->assertSame('withdrawn', $this->student->fresh()->status);
        $this->assertDatabaseHas('student_lifecycle_requests', [
            'id' => $id, 'status' => 'approved', 'reviewed_by' => $this->reviewer->id,
        ]);
        $this->assertDatabaseHas('security_events', ['event_type' => 'student.lifecycle.approved']);
        $this->review($id)->assertSessionHasErrors('decision');
    }

    public function test_unlinked_student_can_move_to_another_campus_only_after_review(): void
    {
        $id = $this->requestChange('transfer', ['target_campus_id' => $this->south->id]);
        $this->assertSame($this->north->id, $this->student->fresh()->campus_id);
        $this->review($id)->assertRedirect();
        $this->assertSame($this->south->id, $this->student->fresh()->campus_id);
        $this->assertNotNull(DB::table('student_lifecycle_requests')->find($id)->applied_at);
    }

    public function test_transfer_blocks_linked_student_to_avoid_cross_campus_privacy_leaks(): void
    {
        $account = $this->member('student', $this->north);
        $this->student->forceFill(['user_id' => $account->id])->save();
        $id = $this->requestChange('transfer', ['target_campus_id' => $this->south->id]);

        $this->review($id)->assertSessionHasErrors('student');
        $this->assertSame($this->north->id, $this->student->fresh()->campus_id);
        $this->assertDatabaseHas('student_lifecycle_requests', ['id' => $id, 'status' => 'pending']);
    }

    public function test_promotion_creates_a_real_enrollment_and_preserves_history(): void
    {
        $year = AcademicYear::create([
            'institution_id' => $this->school->id, 'code' => '2027', 'name' => '2027',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
            'created_by' => $this->director->id,
        ]);
        $subject = AcademicSubject::create([
            'institution_id' => $this->school->id,
            'code' => 'SCI', 'name' => 'Science', 'created_by' => $this->director->id,
        ]);
        $section = ClassSection::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->north->id,
            'academic_year_id' => $year->id, 'academic_subject_id' => $subject->id,
            'code' => 'GR-A', 'name' => 'Grade A', 'capacity' => 1,
            'created_by' => $this->director->id,
        ]);
        $section->forceFill(['status' => 'active'])->save();
        $id = $this->requestChange('promotion', ['target_section_id' => $section->id]);
        $this->review($id)->assertRedirect();
        $this->assertDatabaseHas('class_enrollments', [
            'institution_id' => $this->school->id, 'campus_id' => $this->north->id,
            'student_profile_id' => $this->student->id, 'class_section_id' => $section->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('class_enrollments', 1);
    }

    public function test_students_cannot_review_arbitrary_records_or_other_student_results(): void
    {
        $outsider = $this->member('student', $this->north);
        $this->actingAs($outsider)
            ->get(route('students.lifecycle.index', [$this->school, $this->student->id]))
            ->assertForbidden();
        $this->get(route('students.transcript.show', [$this->school, $this->student->id]))
            ->assertForbidden();
        $this->get(route('students.transcript.export', [$this->school, $this->student->id]))
            ->assertForbidden();

        $this->actingAs($this->director)
            ->get(route('students.transcript.show', [$this->school, $this->student->id]))
            ->assertOk()->assertSee('Reviewed academic results');
        $this->get(route('students.transcript.export', [$this->school, $this->student->id]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
