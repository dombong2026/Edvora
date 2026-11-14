<?php

namespace App\Http\Controllers;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassSection;
use App\Models\ClassTimetableChange;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\ClassTimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ClassroomController extends Controller
{
    public function index(Request $request, Institution $institution, ClassroomService $classrooms): View
    {
        Gate::authorize('view', $institution);
        $canManage = $classrooms->canManage($request->user(), $institution);

        return view('dashboard.classrooms.index', [
            'institution' => $institution,
            'sections' => $classrooms->visible($request->user(), $institution),
            'canManage' => $canManage,
            'teacherNeedsMfa' => ! $request->user()->mfa_enabled_at
                && $request->user()->roleAssignments()
                    ->where('institution_id', $institution->id)
                    ->whereHas('role', fn ($q) => $q->whereIn('code', ['teacher', 'professor']))
                    ->exists(),
            'campuses' => $canManage ? $institution->campuses()
                ->where('status', 'active')->orderBy('name')->get() : collect(),
            'years' => $canManage ? AcademicYear::query()
                ->where('institution_id', $institution->id)->where('status', 'active')
                ->orderByDesc('starts_on')->limit(30)->get() : collect(),
            'subjects' => $canManage ? AcademicSubject::query()
                ->where('institution_id', $institution->id)->where('is_active', true)
                ->orderBy('name')->limit(150)->get() : collect(),
        ]);
    }

    public function store(Request $request, Institution $institution, ClassroomService $classrooms): RedirectResponse
    {
        Gate::authorize('view', $institution);
        abort_unless($classrooms->canManage($request->user(), $institution), 403);

        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'academic_subject_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/D',
                Rule::unique('class_sections')->where(fn ($query) => $query
                    ->where('institution_id', $institution->id)
                    ->where('campus_id', $request->input('campus_id'))
                    ->where('academic_year_id', $request->input('academic_year_id')))],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1', 'max:150'],
        ]);

        $campus = Campus::query()->where('institution_id', $institution->id)->findOrFail($data['campus_id']);
        $year = AcademicYear::query()->where('institution_id', $institution->id)->findOrFail($data['academic_year_id']);
        $subject = AcademicSubject::query()->where('institution_id', $institution->id)->findOrFail($data['academic_subject_id']);

        $section = $classrooms->create($request->user(), $campus, $year, $subject, $data);

        return redirect()->route('classrooms.show', [$institution, $section])
            ->with('status', 'Draft class created. Assign a verified teacher, then activate the class.');
    }

    public function show(Request $request, Institution $institution, int $section, ClassroomService $classrooms): View
    {
        Gate::authorize('view', $institution);
        $record = ClassSection::query()
            ->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year', 'subject', 'teacher'])
            ->findOrFail($section);

        abort_unless($classrooms->canViewSection($request->user(), $record), 403);

        $canManage = $classrooms->canManage($request->user(), $institution);
        $canViewRoster = $classrooms->canViewRoster($request->user(), $record);

        return view('dashboard.classrooms.show', [
            'timetableSlots' => $record->timetableSlots()->orderBy('weekday')->orderBy('starts_at')->limit(80)->get(),
            'canSchedule' => app(ClassTimetableService::class)->canCreate($request->user(), $record),
            'pendingScheduleChanges' => app(ClassTimetableService::class)->canCreate($request->user(), $record)
                ? ClassTimetableChange::query()->where('institution_id', $institution->id)
                    ->where('class_section_id', $record->id)->where('status', 'pending')
                    ->with('requester:id,name')->orderBy('id')->limit(50)->get()
                : collect(),
            'institution' => $institution,
            'section' => $record,
            'canManage' => $canManage,
            'canViewRoster' => $canViewRoster,
            'enrollments' => $canViewRoster
                ? $record->enrollments()->where('status', 'active')
                    ->with('student:id,student_number,display_name,institution_id,campus_id')
                    ->orderBy('id')->limit(150)->get()
                : collect(),
        ]);
    }

    public function assignTeacher(
        Request $request,
        Institution $institution,
        int $section,
        ClassroomService $classrooms
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($classrooms->canManage($request->user(), $institution), 403);
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $record = ClassSection::query()->where('institution_id', $institution->id)
            ->with(['institution', 'campus'])->findOrFail($section);
        $teacher = User::query()->where('email', mb_strtolower(trim($validated['email'])))->firstOrFail();
        $classrooms->assignTeacher($request->user(), $record, $teacher);

        return back()->with('status', 'Verified teacher assigned to this class.');
    }

    public function activate(
        Request $request,
        Institution $institution,
        int $section,
        ClassroomService $classrooms
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($classrooms->canManage($request->user(), $institution), 403);
        $record = ClassSection::query()->where('institution_id', $institution->id)
            ->with('institution')->findOrFail($section);
        $classrooms->activate($request->user(), $record);

        return back()->with('status', 'Class activated; student enrollments may now be added.');
    }

    public function enroll(
        Request $request,
        Institution $institution,
        int $section,
        ClassroomService $classrooms
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($classrooms->canManage($request->user(), $institution), 403);
        $data = $request->validate([
            'student_number' => ['required', 'string', 'max:64'],
        ]);

        $record = ClassSection::query()->where('institution_id', $institution->id)
            ->with('institution')->findOrFail($section);
        $student = StudentProfile::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $record->campus_id)
            ->where('student_number', $data['student_number'])->firstOrFail();

        $classrooms->enroll($request->user(), $record, $student);

        return back()->with('status', 'Student enrolled in the class.');
    }
}
