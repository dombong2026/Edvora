<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Services\ClassTimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClassTimetableController extends Controller
{
    public function store(
        Request $request,
        Institution $institution,
        int $section,
        ClassTimetableService $timetable
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $class = ClassSection::query()->where('institution_id', $institution->id)
            ->with(['institution', 'campus', 'year'])->findOrFail($section);

        abort_unless($timetable->canCreate($request->user(), $class), 403);

        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'room_name' => ['nullable', 'string', 'max:100'],
        ]);

        $timetable->create($request->user(), $class, $data);

        return redirect()->route('classrooms.show', [$institution, $class])
            ->with('status', 'The recurring class timetable has been updated.');
    }
}
