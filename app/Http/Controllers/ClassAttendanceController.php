<?php

namespace App\Http\Controllers;

use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Services\ClassAttendanceService;
use App\Services\ClassroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ClassAttendanceController extends Controller
{
    private function section(Institution $institution, int $id): ClassSection
    {
        return ClassSection::query()
            ->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year', 'subject'])
            ->findOrFail($id);
    }

    private function session(ClassSection $section, int $id): ClassAttendanceSession
    {
        return ClassAttendanceSession::query()
            ->where('institution_id', $section->institution_id)
            ->where('campus_id', $section->campus_id)
            ->where('class_section_id', $section->id)
            ->with('section')
            ->findOrFail($id);
    }

    public function index(
        Request $request,
        Institution $institution,
        int $section,
        ClassAttendanceService $attendance,
        ClassroomService $classrooms
    ): View {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($classrooms->canViewSection($request->user(), $class), 403);

        $canTake = $attendance->canTake($request->user(), $class);
        $canApprove = $attendance->canApprove($request->user(), $class);
        $sessions = ClassAttendanceSession::query()
            ->where('institution_id', $institution->id)
            ->where('class_section_id', $class->id)
            ->when(! ($canTake || $canApprove), fn ($query) => $query->where('status', 'approved'))
            ->orderByDesc('session_date')->limit(100)->get();

        if (! $canTake && ! $canApprove) {
            $sessions = $sessions->filter(fn (ClassAttendanceSession $session): bool =>
                $attendance->visibleMarks($request->user(), $session)->isNotEmpty());
        }

        return view('dashboard.attendance.index', [
            'institution' => $institution,
            'section' => $class,
            'sessions' => $sessions,
            'canTake' => $canTake,
        ]);
    }

    public function store(
        Request $request,
        Institution $institution,
        int $section,
        ClassAttendanceService $attendance
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($attendance->canTake($request->user(), $class), 403);

        $data = $request->validate(['session_date' => ['required', 'date_format:Y-m-d']]);
        $session = $attendance->create($request->user(), $class, $data['session_date']);

        return redirect()->route('attendance.show', [$institution, $class, $session])
            ->with('status', 'Attendance session opened for your assigned class.');
    }

    public function show(
        Request $request,
        Institution $institution,
        int $section,
        int $session,
        ClassAttendanceService $attendance,
        ClassroomService $classrooms
    ): View {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($classrooms->canViewSection($request->user(), $class), 403);

        $record = $this->session($class, $session);
        $canTake = $attendance->canTake($request->user(), $class);
        $canApprove = $attendance->canApprove($request->user(), $class);
        $marks = $attendance->visibleMarks($request->user(), $record);

        abort_if(! $canTake && ! $canApprove && $marks->isEmpty(), 403);

        return view('dashboard.attendance.show', [
            'institution' => $institution,
            'section' => $class,
            'session' => $record,
            'marks' => $marks,
            'existingMarks' => $marks->keyBy('class_enrollment_id'),
            'canTake' => $canTake,
            'canApprove' => $canApprove,
            'eligibleEnrollments' => $canTake && $record->status === 'draft'
                ? $class->enrollments()->where('status', 'active')
                    ->whereDate('enrolled_at', '<=', $record->session_date->toDateString())
                    ->whereHas('student', fn ($query) => $query->where('status', 'active'))
                    ->with('student:id,student_number,display_name')
                    ->orderBy('id')->limit(150)->get()
                : collect(),
        ]);
    }

    public function mark(
        Request $request,
        Institution $institution,
        int $section,
        int $session,
        ClassAttendanceService $attendance
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($attendance->canTake($request->user(), $class), 403);
        $record = $this->session($class, $session);
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(ClassAttendanceService::STATUSES)],
        ]);

        $enrollment = ClassEnrollment::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->findOrFail($data['enrollment_id']);

        $attendance->mark($request->user(), $record, $enrollment, $data['status']);

        return back()->with('status', 'Attendance mark saved as a private draft.');
    }

    public function submit(
        Request $request,
        Institution $institution,
        int $section,
        int $session,
        ClassAttendanceService $attendance
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $record = $this->session($class, $session);

        $attendance->submit($request->user(), $record);

        return back()->with('status', 'Attendance submitted to school leadership for independent approval.');
    }

    public function approve(
        Request $request,
        Institution $institution,
        int $section,
        int $session,
        ClassAttendanceService $attendance
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $record = $this->session($class, $session);

        $attendance->approve($request->user(), $record);

        return back()->with('status', 'Attendance approved; authorized families can now see only their own marks.');
    }
}
