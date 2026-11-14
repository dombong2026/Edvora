<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Services\ClassroomService;
use App\Services\ExamScheduling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ExamScheduleController extends Controller
{
    private function section(Institution $institution, int $section): ClassSection
    {
        return ClassSection::query()->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year', 'subject'])->findOrFail($section);
    }

    public function index(
        Request $request, Institution $institution, int $section,
        ExamScheduling $exams, ClassroomService $classrooms
    ): View {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($classrooms->canViewSection($request->user(), $class), 403);
        $canWrite = $exams->canWrite($request->user(), $class);
        $canReview = $exams->canReview($request->user(), $class);

        $records = DB::table('class_exam_schedules')
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->when(! $canWrite && ! $canReview, fn ($q) => $q->where('status', 'published'))
            ->orderBy('exam_date')->orderBy('starts_at')->paginate(10);

        return view('dashboard.exams.index', [
            'institution' => $institution, 'section' => $class,
            'canWrite' => $canWrite, 'canReview' => $canReview, 'records' => $records,
        ]);
    }

    public function store(
        Request $request, Institution $institution, int $section, ExamScheduling $exams
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($exams->canWrite($request->user(), $class), 403);
        $request->merge([
            'code' => strtoupper(trim((string)$request->input('code', ''))),
            'title' => trim((string)$request->input('title', '')),
            'room' => strtoupper(trim((string)$request->input('room', ''))),
        ]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/D'],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'exam_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9 _-]+$/D'],
        ]);
        $exams->create($request->user(), $class, $data);
        return redirect()->route('exams.index', [$institution, $class])
            ->with('status', 'Exam draft created. Other accounts cannot see it until independently published.');
    }

    public function submit(
        Request $request, Institution $institution, int $section, int $exam,
        ExamScheduling $exams
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $exams->submit($request->user(), $class, $exam);
        return redirect()->route('exams.index', [$institution, $class])
            ->with('status', 'Exam submitted for independent school leadership review.');
    }

    public function review(
        Request $request, Institution $institution, int $section, int $exam,
        ExamScheduling $exams
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $data = $request->validate(['decision' => ['required', Rule::in(['publish', 'reject'])]]);
        $exams->review($request->user(), $class, $exam, $data['decision']);
        return redirect()->route('exams.index', [$institution, $class])
            ->with('status', 'Exam schedule review decision recorded.');
    }
}
