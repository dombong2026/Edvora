<?php

namespace App\Http\Controllers;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Institution;
use App\Services\AcademicCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicCatalogController extends Controller
{
    public function index(Request $request, Institution $institution, AcademicCatalogService $catalog): View
    {
        Gate::authorize('view', $institution);

        return view('dashboard.academic', [
            'institution' => $institution,
            'canManage' => $catalog->canManage($request->user(), $institution),
            'years' => AcademicYear::query()->where('institution_id', $institution->id)
                ->orderByDesc('starts_on')->limit(25)->get(),
            'subjects' => AcademicSubject::query()->where('institution_id', $institution->id)
                ->orderBy('code')->limit(50)->get(),
        ]);
    }

    public function createYear(Request $request, Institution $institution, AcademicCatalogService $catalog): RedirectResponse
    {
        abort_unless($catalog->canManage($request->user(), $institution), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/D',
                Rule::unique('academic_years')->where('institution_id', $institution->id)],
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);
        $catalog->createYear($request->user(), $institution, $data);

        return redirect()->route('academic.index', $institution)->with('status', 'Academic year created in draft status.');
    }

    public function createSubject(Request $request, Institution $institution, AcademicCatalogService $catalog): RedirectResponse
    {
        abort_unless($catalog->canManage($request->user(), $institution), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/D',
                Rule::unique('academic_subjects')->where('institution_id', $institution->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $catalog->createSubject($request->user(), $institution, $data);

        return redirect()->route('academic.index', $institution)->with('status', 'Academic subject created.');
    }
}
