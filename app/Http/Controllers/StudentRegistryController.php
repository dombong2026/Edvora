<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\StudentGuardianLink;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class StudentRegistryController extends Controller
{
    public function index(Request $request, Institution $institution, StudentRegistryService $registry): View
    {
        Gate::authorize('view', $institution);

        $campuses = $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => $registry->canManageCampus($request->user(), $campus));

        return view('dashboard.students.index', [
            'institution' => $institution,
            'students' => $registry->visible($request->user(), $institution),
            'managedCampuses' => $campuses,
        ]);
    }

    public function store(Request $request, Institution $institution, StudentRegistryService $registry): RedirectResponse
    {
        Gate::authorize('view', $institution);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'student_number' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D',
                Rule::unique('student_profiles')->where('institution_id', $institution->id)],
            'display_name' => ['required', 'string', 'max:180'],
        ]);

        $campus = Campus::query()->where('institution_id', $institution->id)->findOrFail($data['campus_id']);
        abort_unless($registry->canManageCampus($request->user(), $campus), 403);

        $profile = $registry->create($request->user(), $campus, $data);

        return redirect()->route('students.show', [$institution, $profile])
            ->with('status', 'Student record created. Account and guardian access require separate verification.');
    }

    public function show(Request $request, Institution $institution, int $student, StudentRegistryService $registry): View
    {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()
            ->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])
            ->findOrFail($student);

        abort_unless($registry->canViewProfile($request->user(), $profile), 403);

        return view('dashboard.students.show', [
            'institution' => $institution,
            'profile' => $profile,
            'canManage' => $registry->canManageCampus($request->user(), $profile->campus),
            'canVerifyGuardian' => $registry->canVerifyGuardian($request->user(), $profile),
            'guardianLinks' => $registry->canVerifyGuardian($request->user(), $profile)
                ? $profile->guardians()->with('guardian:id,name,email')->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function linkAccount(
        Request $request,
        Institution $institution,
        int $student,
        StudentRegistryService $registry
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);

        abort_unless($registry->canManageCampus($request->user(), $profile->campus), 403);
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $account = User::query()->where('email', mb_strtolower(trim($validated['email'])))->firstOrFail();

        $registry->linkStudentAccount($request->user(), $profile, $account);

        return back()->with('status', 'Verified student account linked to the student record.');
    }

    public function verifyGuardian(
        Request $request,
        Institution $institution,
        int $student,
        StudentRegistryService $registry
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless($registry->canVerifyGuardian($request->user(), $profile), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'relationship' => ['required', Rule::in(['parent', 'legal_guardian'])],
        ]);
        $guardian = User::query()->where('email', mb_strtolower(trim($data['email'])))->firstOrFail();
        $registry->verifyGuardian($request->user(), $profile, $guardian, $data['relationship']);

        return back()->with('status', 'Guardian relationship verified and linked.');
    }

    public function revokeGuardian(
        Request $request,
        Institution $institution,
        int $student,
        int $link,
        StudentRegistryService $registry
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless($registry->canVerifyGuardian($request->user(), $profile), 403);
        $relationship = StudentGuardianLink::query()
            ->where('student_profile_id', $profile->id)
            ->where('institution_id', $institution->id)
            ->findOrFail($link);

        $registry->revokeGuardian($request->user(), $profile, $relationship);

        return back()->with('status', 'Guardian access revoked and recorded in the security audit.');
    }
}
