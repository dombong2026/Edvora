<?php

namespace App\Http\Controllers;

use App\Models\AcademicRecordCorrection;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Services\AcademicCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class AcademicCorrectionController extends Controller
{
    private function section(Institution $institution, int $section): ClassSection
    {
        return ClassSection::query()->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year'])->findOrFail($section);
    }

    public function store(
        Request $request,
        Institution $institution,
        int $section,
        AcademicCorrectionService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($service->canRequest($request->user(), $class), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['attendance', 'assessment'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'new_value' => ['required', 'string', 'max:32'],
            'reason_code' => ['required', Rule::in(AcademicCorrectionService::REASONS)],
        ]);

        $service->request(
            $request->user(), $class, $data['kind'], (int) $data['target_id'],
            $data['new_value'], $data['reason_code']
        );

        return back()->with('status', 'Correction requested. The published record remains unchanged until independent approval.');
    }

    public function review(
        Request $request,
        Institution $institution,
        int $section,
        int $correction,
        AcademicCorrectionService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($service->canReview($request->user(), $class), 403);

        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $item = AcademicRecordCorrection::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->findOrFail($correction);

        $service->review($request->user(), $class, $item, $data['decision'] === 'approve');

        return back()->with('status', $data['decision'] === 'approve'
            ? 'Correction approved and applied with complete review history.'
            : 'Correction rejected. The underlying record has not changed.');
    }
}
