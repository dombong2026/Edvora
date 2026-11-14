<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\ClassTimetableChange;
use App\Models\ClassTimetableSlot;
use App\Models\Institution;
use App\Services\ClassTimetableChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ClassTimetableChangeController extends Controller
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
        int $slot,
        ClassTimetableChangeService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($service->canChange($request->user(), $class), 403);

        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'room_name' => ['nullable', 'string', 'max:100'],
            'reason_code' => ['required', Rule::in(ClassTimetableChangeService::REASONS)],
        ]);

        $source = ClassTimetableSlot::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->findOrFail($slot);

        $service->request($request->user(), $class, $source->id, $data);

        return back()->with('status', 'Schedule revision requested. The published timetable stays unchanged until independent approval.');
    }

    public function review(
        Request $request,
        Institution $institution,
        int $section,
        int $change,
        ClassTimetableChangeService $service
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = $this->section($institution, $section);
        abort_unless($service->canChange($request->user(), $class), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);

        $review = ClassTimetableChange::query()
            ->where('institution_id', $institution->id)
            ->where('campus_id', $class->campus_id)
            ->where('class_section_id', $class->id)
            ->findOrFail($change);

        $service->review($request->user(), $class, $review, $data['decision'] === 'approve');

        return back()->with('status', $data['decision'] === 'approve'
            ? 'Approved schedule applied with original and revised details retained.'
            : 'Schedule change rejected. The current timetable remains unchanged.');
    }
}
