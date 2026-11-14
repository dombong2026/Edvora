<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassAttendanceMark;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\ClassTimetableSlot;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\ClassAttendanceService;
use App\Services\ClassTimetableService;
use App\Services\StudentRegistryService;
use App\Services\Totp;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TimetableAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2027-06-12 11:00:00'));
    }

    private function school(string $slug = 'school'): Institution
    {
        return Institution::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    private function campus(Institution $institution, string $slug = 'main'): Campus
    {
        return Campus::create([
            'institution_id' => $institution->id,
            'slug' => $slug,
            'name' => ucfirst($slug),
            'status' => 'active',
        ]);
    }

    private function account(Institution $institution, string $role, ?Campus $campus = null): User
    {
        $account = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $institution->id, 'user_id' => $account->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $institution->id,
            'user_id' => $account->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator', 'teacher', 'professor'], true)) {
            $account->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(Totp::class)->newSecret(),
            ])->save();
        }

        return $account;
    }

    private function class(Campus $campus, User $officer, User $teacher, string $code = 'A'): ClassSection
    {
        $year = AcademicYear::create([
            'institution_id' => $campus->institution_id, 'code' => 'YEAR'.$code,
            'name' => 'Year '.$code, 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31', 'created_by' => $officer->id,
        ]);
        $year->forceFill(['status' => 'active'])->save();

        $subject = AcademicSubject::create([
            'institution_id' => $campus->institution_id, 'code' => 'SUBJ'.$code,
            'name' => 'Subject '.$code, 'created_by' => $officer->id,
        ]);

        $class = ClassSection::create([
            'institution_id' => $campus->institution_id,
            'campus_id' => $campus->id,
            'academic_year_id' => $year->id,
            'academic_subject_id' => $subject->id,
            'code' => $code,
            'name' => 'Class '.$code,
            'capacity' => 30,
            'created_by' => $officer->id,
        ]);
        app(ClassroomService::class)->assignTeacher($officer, $class, $teacher);
        app(ClassroomService::class)->activate($officer, $class);
        return $class->fresh();
    }

    private function student(Campus $campus, User $officer, string $code, ?User $user = null): StudentProfile
    {
        $profile = StudentProfile::create([
            'institution_id' => $campus->institution_id, 'campus_id' => $campus->id,
            'student_number' => $code, 'display_name' => 'Student '.$code,
            'created_by' => $officer->id,
        ]);
        if ($user) {
            app(StudentRegistryService::class)->linkStudentAccount($officer, $profile, $user);
        }
        // Reload database-default status before testing the production enrollment guard.
        return $profile->fresh();
    }

    private function enroll(ClassSection $class, User $officer, StudentProfile $student): ClassEnrollment
    {
        return app(ClassroomService::class)->enroll($officer, $class, $student);
    }

    public function test_leadership_adds_timetable_and_conflicts_are_rejected(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $officer, $teacher);

        $this->actingAs($teacher)->post(route('timetable.store', [$school, $class]), [
            'weekday' => 1, 'starts_at' => '08:00',
            'ends_at' => '09:00', 'room_name' => 'Room 1',
        ])->assertForbidden();

        $this->actingAs($officer)->post(route('timetable.store', [$school, $class]), [
            'weekday' => 1, 'starts_at' => '08:00',
            'ends_at' => '09:00', 'room_name' => 'Room 1',
        ])->assertRedirect(route('classrooms.show', [$school, $class]));
        $this->assertDatabaseCount('class_timetable_slots', 1);

        $this->post(route('timetable.store', [$school, $class]), [
            'weekday' => 1, 'starts_at' => '08:30',
            'ends_at' => '10:00', 'room_name' => 'Room 2',
        ])->assertSessionHasErrors('starts_at');

        $this->post(route('timetable.store', [$school, $class]), [
            'weekday' => 1, 'starts_at' => '10:00',
            'ends_at' => '09:00', 'room_name' => 'Room 2',
        ])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('class_timetable_slots', 1);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'class.timetable.created', 'actor_user_id' => $officer->id,
        ]);
        $this->actingAs($teacher)->get(route('classrooms.show', [$school, $class]))
            ->assertOk()->assertSee('Room 1');
    }

    public function test_timetable_room_is_not_double_booked_across_classes_in_same_campus(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $anotherTeacher = $this->account($school, 'teacher', $campus);
        $first = $this->class($campus, $officer, $teacher, 'A');
        $second = $this->class($campus, $officer, $anotherTeacher, 'B');

        $service = app(ClassTimetableService::class);
        $service->create($officer, $first, [
            'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '10:00',
            'room_name' => 'Lab 2',
        ]);
        $this->actingAs($officer)->post(route('timetable.store', [$school, $second]), [
            'weekday' => 2, 'starts_at' => '09:15',
            'ends_at' => '09:45', 'room_name' => 'Lab 2',
        ])->assertSessionHasErrors('room_name');

        $this->assertDatabaseCount('class_timetable_slots', 1);
    }

    public function test_draft_attendance_requires_complete_roster_and_independent_approval(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $officer, $teacher);
        $student1Account = $this->account($school, 'student', $campus);
        $parentAccount = $this->account($school, 'parent', $campus);
        $unrelatedParent = $this->account($school, 'parent', $campus);
        $child1 = $this->student($campus, $officer, 'S1', $student1Account);
        $child2 = $this->student($campus, $officer, 'S2');
        $enrollment1 = $this->enroll($class, $officer, $child1);
        $enrollment2 = $this->enroll($class, $officer, $child2);
        app(StudentRegistryService::class)->verifyGuardian($officer, $child1, $parentAccount, 'parent');

        $this->actingAs($teacher)->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2027-06-12',
        ])->assertRedirect();

        $session = ClassAttendanceSession::firstOrFail();
        $this->assertSame('draft', $session->status);
        $this->get(route('attendance.show', [$school, $class, $session]))
            ->assertOk()->assertSee('Record student attendance');

        $this->post(route('attendance.mark', [$school, $class, $session]), [
            'enrollment_id' => $enrollment1->id, 'status' => 'present',
        ])->assertRedirect();
        $this->post(route('attendance.submit', [$school, $class, $session]))
            ->assertSessionHasErrors('attendance');

        $this->post(route('attendance.mark', [$school, $class, $session]), [
            'enrollment_id' => $enrollment2->id, 'status' => 'absent',
        ])->assertRedirect();
        $this->post(route('attendance.submit', [$school, $class, $session]))->assertRedirect();

        $this->assertSame('submitted', $session->fresh()->status);
        $this->assertDatabaseCount('class_attendance_marks', 2);
        $this->post(route('attendance.mark', [$school, $class, $session]), [
            'enrollment_id' => $enrollment1->id, 'status' => 'late',
        ])->assertSessionHasErrors('status');

        $this->actingAs($student1Account)->get(route('attendance.index', [$school, $class]))
            ->assertOk()->assertDontSee('12 Jun 2027');
        $this->get(route('attendance.show', [$school, $class, $session]))->assertForbidden();

        $this->actingAs($teacher)->post(route('attendance.approve', [$school, $class, $session]))
            ->assertForbidden();
        $this->actingAs($officer)->post(route('attendance.approve', [$school, $class, $session]))
            ->assertRedirect();
        $this->assertSame('approved', $session->fresh()->status);

        $this->actingAs($student1Account)->get(route('attendance.show', [$school, $class, $session]))
            ->assertOk()->assertSee('Student S1')->assertDontSee('Student S2');
        $this->actingAs($parentAccount)->get(route('attendance.show', [$school, $class, $session]))
            ->assertOk()->assertSee('Student S1')->assertDontSee('Student S2');
        $this->actingAs($unrelatedParent)->get(route('attendance.index', [$school, $class]))
            ->assertForbidden();

        $this->assertDatabaseHas('security_events', ['event_type' => 'attendance.approved', 'actor_user_id' => $officer->id]);
        $this->assertDatabaseMissing('security_events', ['metadata' => 'absent']);
    }

    public function test_unassigned_teacher_and_suspended_accounts_cannot_open_or_edit_attendance(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $unassigned = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $officer, $teacher);

        $this->actingAs($unassigned)->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2027-06-12',
        ])->assertForbidden();

        InstitutionMembership::query()->where('user_id', $teacher->id)
            ->where('institution_id', $school->id)->update(['status' => 'suspended']);
        $this->actingAs($teacher)->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2027-06-12',
        ])->assertForbidden();

        $this->assertDatabaseCount('class_attendance_sessions', 0);
    }

    public function test_attendance_date_must_be_within_academic_year_and_not_future(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $officer, $teacher);

        $this->actingAs($teacher)->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2027-06-13',
        ])->assertSessionHasErrors('session_date');
        $this->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2026-12-31',
        ])->assertSessionHasErrors('session_date');

        $this->assertDatabaseCount('class_attendance_sessions', 0);
    }

    public function test_database_rejects_marks_with_wrong_class_enrollment(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $first = $this->class($campus, $officer, $teacher, 'A');
        $second = $this->class($campus, $officer, $teacher, 'B');
        $student = $this->student($campus, $officer, 'S1');
        $enrollment = $this->enroll($second, $officer, $student);
        $session = app(ClassAttendanceService::class)->create($teacher, $first, '2027-06-12');

        $this->expectException(QueryException::class);
        ClassAttendanceMark::create([
            'institution_id' => $school->id,
            'campus_id' => $campus->id,
            'class_section_id' => $first->id,
            'attendance_session_id' => $session->id,
            'class_enrollment_id' => $enrollment->id,
            'status' => 'present',
            'recorded_by' => $teacher->id,
        ]);
    }

    public function test_teacher_cannot_be_double_booked_across_two_rooms(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $first = $this->class($campus, $officer, $teacher, 'A');
        $second = $this->class($campus, $officer, $teacher, 'B');

        $service = app(ClassTimetableService::class);
        $service->create($officer, $first, [
            'weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '10:00', 'room_name' => 'Room 1',
        ]);

        $this->actingAs($officer)->post(route('timetable.store', [$school, $second]), [
            'weekday' => 3, 'starts_at' => '09:30', 'ends_at' => '10:30', 'room_name' => 'Room 2',
        ])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('class_timetable_slots', 1);
    }


    public function test_institution_timezone_controls_date_boundary_for_attendance(): void
    {
        // 20:30 UTC is 03:30 the following day in Asia/Phnom_Penh.
        $this->travelTo(Carbon::parse('2027-06-12 20:30:00', 'UTC'));
        $school = $this->school();
        $this->assertSame('Asia/Phnom_Penh', $school->fresh()->timezone);
        $campus = $this->campus($school);
        $officer = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $officer, $teacher);

        $this->actingAs($teacher)->post(route('attendance.store', [$school, $class]), [
            'session_date' => '2027-06-13',
        ])->assertRedirect();

        $session = ClassAttendanceSession::firstOrFail();
        $this->assertSame($class->id, $session->class_section_id);
        $this->assertSame('2027-06-13', $session->session_date->toDateString());
    }


    public function test_attendance_corrections_require_independent_review_and_preserve_history(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $parent = $this->account($school, 'parent', $campus);
        $class = $this->class($campus, $director, $teacher);
        $student = $this->student($campus, $director, 'S1');
        $enrollment = $this->enroll($class, $director, $student);
        app(StudentRegistryService::class)->verifyGuardian($director, $student, $parent, 'parent');

        $attendance = app(ClassAttendanceService::class);
        $session = $attendance->create($teacher, $class, '2027-06-12');
        $mark = $attendance->mark($teacher, $session, $enrollment, 'present');
        $attendance->submit($teacher, $session);
        $attendance->approve($director, $session);

        $this->actingAs($teacher)->post(route('corrections.store', [$school, $class]), [
            'kind' => 'attendance',
            'target_id' => $mark->id,
            'new_value' => 'late',
            'reason_code' => 'recording_error',
        ])->assertRedirect();
        $correction = \App\Models\AcademicRecordCorrection::firstOrFail();
        $this->assertSame('pending', $correction->status);
        $this->assertSame('present', $mark->fresh()->status);
        $this->assertSame('present', $correction->from_value);
        $this->assertSame('late', $correction->to_value);

        $this->post(route('corrections.store', [$school, $class]), [
            'kind' => 'attendance',
            'target_id' => $mark->id,
            'new_value' => 'absent',
            'reason_code' => 'verified_excuse',
        ])->assertSessionHasErrors('correction');
        $this->assertDatabaseCount('academic_record_corrections', 1);

        $this->actingAs($parent)->get(route('attendance.show', [$school, $class, $session]))
            ->assertOk()->assertSee('Present')->assertDontSee('Late');

        $this->actingAs($teacher)->post(route('corrections.review', [$school, $class, $correction]), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($director)->post(route('corrections.review', [$school, $class, $correction]), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame('late', $mark->fresh()->status);
        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame($director->id, $correction->fresh()->reviewed_by);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'academic.correction.approved', 'actor_user_id' => $director->id,
        ]);
        $this->actingAs($parent)->get(route('attendance.show', [$school, $class, $session]))
            ->assertOk()->assertSee('Late')->assertDontSee('Present');

        $this->actingAs($teacher)->post(route('corrections.store', [$school, $class]), [
            'kind' => 'attendance',
            'target_id' => $mark->id,
            'new_value' => 'absent',
            'reason_code' => 'administrative_review',
        ])->assertRedirect();
        $second = \App\Models\AcademicRecordCorrection::orderByDesc('id')->firstOrFail();

        $this->actingAs($director)->post(route('corrections.review', [$school, $class, $second]), [
            'decision' => 'reject',
        ])->assertRedirect();
        $this->assertSame('late', $mark->fresh()->status);
        $this->assertSame('rejected', $second->fresh()->status);
        $this->assertDatabaseCount('academic_record_corrections', 2);

        $this->post(route('corrections.review', [$school, $class, $correction]), [
            'decision' => 'approve',
        ])->assertSessionHasErrors('correction');
    }

    public function test_attendance_corrections_reject_cross_class_and_not_yet_approved_marks(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $first = $this->class($campus, $director, $teacher, 'A');
        $second = $this->class($campus, $director, $teacher, 'B');
        $student = $this->student($campus, $director, 'S1');
        $enrollment = $this->enroll($first, $director, $student);
        $attendance = app(ClassAttendanceService::class);
        $session = $attendance->create($teacher, $first, '2027-06-12');
        $mark = $attendance->mark($teacher, $session, $enrollment, 'present');

        $this->actingAs($teacher)->post(route('corrections.store', [$school, $first]), [
            'kind' => 'attendance', 'target_id' => $mark->id,
            'new_value' => 'late', 'reason_code' => 'recording_error',
        ])->assertSessionHasErrors('correction');

        $this->post(route('corrections.store', [$school, $second]), [
            'kind' => 'attendance', 'target_id' => $mark->id,
            'new_value' => 'late', 'reason_code' => 'recording_error',
        ])->assertNotFound();
        $this->assertDatabaseCount('academic_record_corrections', 0);
    }


    public function test_schedule_revision_requires_independent_review_and_preserves_original_history(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $administrator = $this->account($school, 'administrator');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $director, $teacher);
        $slot = app(ClassTimetableService::class)->create($director, $class, [
            'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00',
            'room_name' => 'Room A',
        ]);

        $this->actingAs($director)->post(route('timetable.changes.store', [$school, $class, $slot]), [
            'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00',
            'room_name' => 'Room B', 'reason_code' => 'room_change',
        ])->assertRedirect();

        $change = \App\Models\ClassTimetableChange::firstOrFail();
        $this->assertSame('pending', $change->status);
        $this->assertSame('08:00', substr($slot->fresh()->starts_at, 0, 5));
        $this->assertSame('Room A', $change->old_room_name);
        $this->assertSame('Room B', $change->new_room_name);

        $this->post(route('timetable.changes.store', [$school, $class, $slot]), [
            'weekday' => 2, 'starts_at' => '11:00', 'ends_at' => '12:00',
            'room_name' => 'Room C', 'reason_code' => 'staff_scheduling',
        ])->assertSessionHasErrors('timetable');
        $this->assertDatabaseCount('class_timetable_changes', 1);

        $this->post(route('timetable.changes.review', [$school, $class, $change]), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($administrator)->post(route('timetable.changes.review', [$school, $class, $change]), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame('approved', $change->fresh()->status);
        $this->assertSame($administrator->id, $change->fresh()->reviewed_by);
        $this->assertSame('09:00', substr($slot->fresh()->starts_at, 0, 5));
        $this->assertSame('Room B', $slot->fresh()->room_name);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'class.timetable.revision.approved',
            'actor_user_id' => $administrator->id,
        ]);

        $this->actingAs($director)->post(route('timetable.changes.store', [$school, $class, $slot]), [
            'weekday' => 3, 'starts_at' => '10:00', 'ends_at' => '11:00',
            'room_name' => 'Room C', 'reason_code' => 'operational_adjustment',
        ])->assertRedirect();
        $rejected = \App\Models\ClassTimetableChange::orderByDesc('id')->firstOrFail();

        $this->actingAs($administrator)->post(route('timetable.changes.review', [$school, $class, $rejected]), [
            'decision' => 'reject',
        ])->assertRedirect();
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('Room B', $slot->fresh()->room_name);
        $this->assertDatabaseCount('class_timetable_changes', 2);
    }

    public function test_timetable_revision_approval_rechecks_room_and_teacher_conflicts(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $administrator = $this->account($school, 'administrator');
        $teacherA = $this->account($school, 'teacher', $campus);
        $teacherB = $this->account($school, 'teacher', $campus);
        $a = $this->class($campus, $director, $teacherA, 'A');
        $b = $this->class($campus, $director, $teacherB, 'B');
        $schedule = app(ClassTimetableService::class);
        $schedule->create($director, $a, [
            'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room_name' => 'Room A',
        ]);
        $slotB = $schedule->create($director, $b, [
            'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00', 'room_name' => 'Room B',
        ]);

        $this->actingAs($director)->post(route('timetable.changes.store', [$school, $b, $slotB]), [
            'weekday' => 1, 'starts_at' => '08:30', 'ends_at' => '09:30',
            'room_name' => 'Room A', 'reason_code' => 'conflict_resolution',
        ])->assertRedirect();

        $change = \App\Models\ClassTimetableChange::firstOrFail();
        $this->actingAs($administrator)->post(route('timetable.changes.review', [$school, $b, $change]), [
            'decision' => 'approve',
        ])->assertSessionHasErrors('timetable');
        $this->assertSame('pending', $change->fresh()->status);
        $this->assertSame('10:00', substr($slotB->fresh()->starts_at, 0, 5));

        $this->post(route('timetable.changes.review', [$school, $b, $change]), [
            'decision' => 'reject',
        ])->assertRedirect();
        $this->assertSame('rejected', $change->fresh()->status);
    }

    public function test_unassigned_teacher_cannot_request_schedule_change_or_review_it(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $class = $this->class($campus, $director, $teacher);
        $slot = app(ClassTimetableService::class)->create($director, $class, [
            'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room_name' => 'Room A',
        ]);
        $payload = [
            'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00',
            'room_name' => 'Room B', 'reason_code' => 'room_change',
        ];
        $this->actingAs($teacher)->post(route('timetable.changes.store', [$school, $class, $slot]), $payload)
            ->assertForbidden();

        $this->actingAs($director)->post(route('timetable.changes.store', [$school, $class, $slot]), $payload)
            ->assertRedirect();
        $change = \App\Models\ClassTimetableChange::firstOrFail();

        $this->actingAs($teacher)->post(route('timetable.changes.review', [$school, $class, $change]), [
            'decision' => 'approve',
        ])->assertForbidden();
        $this->assertDatabaseHas('class_timetable_changes', [
            'id' => $change->id, 'status' => 'pending',
        ]);
    }

    public function test_database_rejects_revision_target_from_another_class(): void
    {
        $school = $this->school();
        $campus = $this->campus($school);
        $director = $this->account($school, 'director');
        $teacher = $this->account($school, 'teacher', $campus);
        $a = $this->class($campus, $director, $teacher, 'A');
        $b = $this->class($campus, $director, $teacher, 'B');
        $slot = app(ClassTimetableService::class)->create($director, $a, [
            'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room_name' => 'Room A',
        ]);

        $this->expectException(QueryException::class);
        \App\Models\ClassTimetableChange::create([
            'institution_id' => $school->id,
            'campus_id' => $campus->id,
            'class_section_id' => $b->id,
            'timetable_slot_id' => $slot->id,
            'old_weekday' => 1, 'old_starts_at' => '08:00', 'old_ends_at' => '09:00',
            'old_room_name' => 'Room A',
            'new_weekday' => 1, 'new_starts_at' => '09:00', 'new_ends_at' => '10:00',
            'new_room_name' => 'Room B',
            'reason_code' => 'room_change', 'requested_by' => $director->id,
        ]);
    }

}
