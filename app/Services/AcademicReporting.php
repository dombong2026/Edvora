<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class AcademicReporting
{
    public const METRICS = [
        'students' => 'Registered student profiles',
        'classes' => 'Total class sections',
        'active_classes' => 'Active class sections',
        'active_enrollments' => 'Active class enrollments',
        'attendance_approved' => 'Approved attendance sessions',
        'attendance_pending' => 'Attendance sessions in review',
        'assessments_published' => 'Published class assessments',
        'assessments_pending' => 'Assessments in review',
        'active_academic_years' => 'Active academic years',
        'active_subjects' => 'Active academic subjects',
    ];

    public function authorize(User $user, Institution $institution): void
    {
        if (! app(AcademicCatalogService::class)->canManage($user, $institution)) {
            throw new AuthorizationException('Academic reporting requires an authorized MFA-verified school officer.');
        }
    }

    public function campuses(User $user, Institution $institution)
    {
        $this->authorize($user, $institution);
        return $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => app(InstitutionAccess::class)->canViewCampus($user, $campus))
            ->values();
    }

    /** Aggregate-only metrics. No marks, student identities, contact details or fee amounts. */
    public function metrics(Institution $institution, ?Campus $campus = null): array
    {
        $scoped = static function (string $table) use ($institution, $campus) {
            return DB::table($table)
                ->where('institution_id', $institution->id)
                ->when($campus, fn ($query) => $query->where('campus_id', $campus->id));
        };

        return [
            'students' => $scoped('student_profiles')->count(),
            'classes' => $scoped('class_sections')->count(),
            'active_classes' => $scoped('class_sections')->where('status', 'active')->count(),
            'active_enrollments' => $scoped('class_enrollments')->where('status', 'active')->count(),
            'attendance_approved' => $scoped('class_attendance_sessions')->where('status', 'approved')->count(),
            'attendance_pending' => $scoped('class_attendance_sessions')->where('status', 'submitted')->count(),
            'assessments_published' => $scoped('class_assessments')->where('status', 'published')->count(),
            'assessments_pending' => $scoped('class_assessments')->where('status', 'submitted')->count(),
            // Academic year and subject catalogs are institution-level, not campus scoped.
            'active_academic_years' => DB::table('academic_years')->where('institution_id', $institution->id)
                ->where('status', 'active')->count(),
            'active_subjects' => DB::table('academic_subjects')->where('institution_id', $institution->id)
                ->where('is_active', true)->count(),
        ];
    }
}
