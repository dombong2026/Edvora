<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\StudentProfile;
use App\Services\StudentRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentTranscriptController extends Controller
{
    /** Only published, independently reviewed scores; a record preview, not a signed certificate. */
    private function results(\App\Models\StudentProfile $student)
    {
        return DB::table('class_assessment_results as r')
            ->join('class_assessments as a', function ($join): void {
                $join->on('a.id', '=', 'r.assessment_id')
                    ->on('a.institution_id', '=', 'r.institution_id')
                    ->on('a.campus_id', '=', 'r.campus_id')
                    ->on('a.class_section_id', '=', 'r.class_section_id');
            })
            ->join('class_enrollments as e', function ($join): void {
                $join->on('e.id', '=', 'r.class_enrollment_id')
                    ->on('e.institution_id', '=', 'r.institution_id')
                    ->on('e.campus_id', '=', 'r.campus_id')
                    ->on('e.class_section_id', '=', 'r.class_section_id');
            })
            ->join('class_sections as c', 'c.id', '=', 'a.class_section_id')
            ->join('academic_years as y', 'y.id', '=', 'c.academic_year_id')
            ->where('r.institution_id', $student->institution_id)
            ->where('e.student_profile_id', $student->id)
            ->where('a.status', 'published')
            ->whereNotNull('a.published_at')
            ->select('a.code', 'a.title', 'a.max_points', 'r.points_earned',
                'a.published_at', 'c.name as class_name', 'y.name as academic_year')
            ->orderBy('a.published_at')
            ->orderBy('a.id')
            ->limit(500)
            ->get();
    }

    private function profile(Request $request, Institution $institution, int $student): StudentProfile
    {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless(app(StudentRegistryService::class)->canViewProfile($request->user(), $profile), 403);

        return $profile;
    }

    public function show(Request $request, Institution $institution, int $student): View
    {
        $profile = $this->profile($request, $institution, $student);

        return view('dashboard.students.transcript', [
            'institution' => $institution,
            'profile' => $profile,
            'results' => $this->results($profile),
        ]);
    }

    public function export(Request $request, Institution $institution, int $student): StreamedResponse
    {
        $profile = $this->profile($request, $institution, $student);
        $results = $this->results($profile);
        return response()->streamDownload(static function () use ($profile, $results): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fputcsv($stream, ['Academic results preview — not an official transcript']);
            // Prefix any user-originated text starting with formula symbols to defeat spreadsheet injection.
            $safe = static function ($value): string {
                $text = (string) $value;
                return preg_match('/^[=+\-@\t\r\n]/u', $text) ? "'".$text : $text;
            };
            fputcsv($stream, ['Student number', $safe($profile->student_number)]);
            fputcsv($stream, ['Student', $safe($profile->display_name)]);
            fputcsv($stream, ['Academic year', 'Class', 'Assessment', 'Points', 'Maximum', 'Published at']);
            foreach ($results as $row) {
                fputcsv($stream, [
                    $safe($row->academic_year), $safe($row->class_name), $safe($row->title),
                    $row->points_earned, $row->max_points, $row->published_at,
                ]);
            }
            fclose($stream);
        }, 'edvora-reviewed-results-'.$profile->id.'.csv', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
