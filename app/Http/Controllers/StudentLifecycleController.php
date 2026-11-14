<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\StudentProfile;
use App\Services\StudentLifecycleService;
use App\Services\StudentRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class StudentLifecycleController extends Controller
{
    public function index(
        Request $request,
        Institution $institution,
        int $student,
        StudentLifecycleService $service
    ): View {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()
            ->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless($service->canRequest($request->user(), $profile)
            || $service->canReview($request->user(), $institution), 403);

        $history = DB::table('student_lifecycle_requests')
            ->where('institution_id', $institution->id)
            ->where('student_profile_id', $profile->id)
            ->orderByDesc('id')->paginate(10);
        $classes = DB::table('class_sections')->where('institution_id', $institution->id)
            ->where('campus_id', $profile->campus_id)->where('status', 'active')
            ->orderBy('code')->limit(200)->get(['id', 'name', 'code']);
        $campuses = $institution->campuses()->where('status', 'active')
            ->where('id', '!=', $profile->campus_id)->orderBy('name')->get();

        return view('dashboard.students.lifecycle', [
            'institution' => $institution,
            'profile' => $profile,
            'history' => $history,
            'classes' => $classes,
            'campuses' => $campuses,
            'canRequest' => $service->canRequest($request->user(), $profile),
            'canReview' => $service->canReview($request->user(), $institution),
        ]);
    }

    public function store(
        Request $request,
        Institution $institution,
        int $student,
        StudentLifecycleService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless($service->canRequest($request->user(), $profile), 403);
        $request->merge(['reason' => trim((string) $request->input('reason', ''))]);

        $data = $request->validate([
            'kind' => ['required', Rule::in(StudentLifecycleService::KINDS)],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'target_campus_id' => ['nullable', 'integer'],
            'target_section_id' => ['nullable', 'integer'],
        ]);
        $service->request($request->user(), $profile, $data['kind'],
            isset($data['target_campus_id']) ? (int) $data['target_campus_id'] : null,
            isset($data['target_section_id']) ? (int) $data['target_section_id'] : null,
            $data['reason']);

        return redirect()->route('students.lifecycle.index', [$institution, $profile->id])
            ->with('status', 'Student change request saved for independent school review.');
    }

    public function review(
        Request $request,
        Institution $institution,
        int $student,
        int $lifecycle,
        StudentLifecycleService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($service->canReview($request->user(), $institution), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        abort_unless(DB::table('student_lifecycle_requests')->where('institution_id', $institution->id)
            ->where('student_profile_id', $student)->where('id', $lifecycle)->exists(), 404);
        $service->review($request->user(), $institution, $lifecycle, $data['decision']);

        return redirect()->route('students.lifecycle.index', [$institution, $student])
            ->with('status', 'Independent review decision completed and recorded.');
    }
}
