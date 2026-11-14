<?php

namespace App\Http\Controllers;

use App\Models\AcademicRecordCorrection;
use App\Models\ClassAssessment;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Services\ClassAssessmentService;
use App\Services\ClassroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ClassAssessmentController extends Controller
{
    private function section(Institution $institution, int $id): ClassSection
    {
        return ClassSection::query()->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year', 'subject'])
            ->findOrFail($id);
    }

    private function assessment(ClassSection $section, int $id): ClassAssessment
    {
        return ClassAssessment::query()
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
        ClassAssessmentService $assessments,
        ClassroomService $classrooms
    ): View {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($classrooms->canViewSection($request->user(), $class), 403);

        $canWrite = $assessments->canWrite($request->user(), $class);
        $canReview = $assessments->canReview($request->user(), $class);
        $list = ClassAssessment::query()
            ->where('institution_id', $institution->id)
            ->where('class_section_id', $class->id)
            ->when(! ($canWrite || $canReview), fn ($query) => $query->where('status', 'published'))
            ->latest()->limit(100)->get();

        if (! $canWrite && ! $canReview) {
            $list = $list->filter(fn (ClassAssessment $item): bool =>
                $assessments->visibleResults($request->user(), $item)->isNotEmpty());
        }

        return view('dashboard.assessments.index', [
            'institution' => $institution,
            'section' => $class,
            'assessments' => $list,
            'canWrite' => $canWrite,
        ]);
    }

    public function store(
        Request $request,
        Institution $institution,
        int $section,
        ClassAssessmentService $assessments
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($assessments->canWrite($request->user(), $class), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/D',
                Rule::unique('class_assessments')->where('class_section_id', $class->id)],
            'title' => ['required', 'string', 'max:150'],
            'max_points' => ['required', 'string', 'max:10', 'regex:/^\d{1,5}(\.\d{1,2})?$/D'],
        ]);

        $item = $assessments->create($request->user(), $class, $data);

        return redirect()->route('assessments.show', [$institution, $class, $item])
            ->with('status', 'Draft assessment created. Scores remain private until independently reviewed.');
    }

    public function show(
        Request $request,
        Institution $institution,
        int $section,
        int $assessment,
        ClassAssessmentService $assessments,
        ClassroomService $classrooms
    ): View {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($classrooms->canViewSection($request->user(), $class), 403);
        $item = $this->assessment($class, $assessment);

        $canWrite = $assessments->canWrite($request->user(), $class);
        $canReview = $assessments->canReview($request->user(), $class);
        $results = $assessments->visibleResults($request->user(), $item);
        abort_if(! $canWrite && ! $canReview && $results->isEmpty(), 403);

        return view('dashboard.assessments.show', [
            'institution' => $institution,
            'section' => $class,
            'assessment' => $item,
            'results' => $results,
            'existingResults' => $results->keyBy('class_enrollment_id'),
            'canWrite' => $canWrite,
            'canReview' => $canReview,
            'pendingCorrections' => ($canWrite || $canReview)
                ? AcademicRecordCorrection::query()
                    ->where('institution_id', $institution->id)
                    ->where('class_section_id', $class->id)
                    ->where('kind', 'assessment')->where('status', 'pending')
                    ->whereHas('assessmentResult', fn ($query) => $query->where('assessment_id', $item->id))
                    ->with(['requester:id,name', 'assessmentResult.enrollment.student:id,student_number,display_name'])
                    ->orderBy('id')->limit(100)->get()
                : collect(),
            'eligibleEnrollments' => $canWrite && $item->status === 'draft'
                ? $class->enrollments()->where('status', 'active')
                    ->whereHas('student', fn ($query) => $query->where('status', 'active'))
                    ->with('student:id,student_number,display_name')
                    ->orderBy('id')->limit(150)->get()
                : collect(),
        ]);
    }

    public function record(
        Request $request,
        Institution $institution,
        int $section,
        int $assessment,
        ClassAssessmentService $assessments
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($assessments->canWrite($request->user(), $class), 403);
        $item = $this->assessment($class, $assessment);

        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'points_earned' => ['required', 'string', 'max:10', 'regex:/^\d{1,5}(\.\d{1,2})?$/D'],
        ]);
        $enrollment = ClassEnrollment::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->findOrFail($data['enrollment_id']);

        $assessments->record($request->user(), $item, $enrollment, $data['points_earned']);

        return back()->with('status', 'Score saved privately as a draft.');
    }

    public function submit(
        Request $request,
        Institution $institution,
        int $section,
        int $assessment,
        ClassAssessmentService $assessments
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $item = $this->assessment($class, $assessment);
        $assessments->submit($request->user(), $item);

        return back()->with('status', 'Assessment submitted to school leadership for independent review.');
    }

    public function publish(
        Request $request,
        Institution $institution,
        int $section,
        int $assessment,
        ClassAssessmentService $assessments
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        $item = $this->assessment($class, $assessment);
        $assessments->publish($request->user(), $item);

        return back()->with('status', 'Assessment published. Families can now view only their own reviewed scores.');
    }
}
