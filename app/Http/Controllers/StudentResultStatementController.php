<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Services\StudentResultStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentResultStatementController extends Controller
{
    private function authorized(Request $request, Institution $institution, int $student): StudentProfile
    {
        Gate::authorize('view', $institution);
        $profile = StudentProfile::query()->where('institution_id', $institution->id)
            ->with(['campus', 'institution'])->findOrFail($student);
        abort_unless(app(StudentResultStatementService::class)->canRead($request->user(), $profile), 403);
        return $profile;
    }

    private function record(Institution $institution, StudentProfile $student, int $id): object
    {
        $row = DB::table('student_result_statements')
            ->where('institution_id', $institution->id)
            ->where('student_profile_id', $student->id)
            ->find($id);
        abort_unless($row, 404);
        return $row;
    }

    public function index(
        Request $request, Institution $institution, int $student,
        StudentResultStatementService $statements
    ): View {
        $profile = $this->authorized($request, $institution, $student);
        $yearIds = DB::table('class_enrollments as e')
            ->join('class_sections as c', function ($join): void {
                $join->on('c.id', '=', 'e.class_section_id')
                    ->on('c.institution_id', '=', 'e.institution_id')
                    ->on('c.campus_id', '=', 'e.campus_id');
            })
            ->where('e.institution_id', $institution->id)
            ->where('e.student_profile_id', $profile->id)
            ->distinct()->pluck('c.academic_year_id')->all();
        $years = AcademicYear::query()->where('institution_id', $institution->id)
            ->whereIn('id', $yearIds)->orderByDesc('starts_on')->get();
        $history = DB::table('student_result_statements as s')
            ->join('academic_years as y', function ($join): void {
                $join->on('y.id', '=', 's.academic_year_id')
                    ->on('y.institution_id', '=', 's.institution_id');
            })
            ->where('s.institution_id', $institution->id)
            ->where('s.student_profile_id', $profile->id)
            ->select('s.id', 's.status', 's.requested_by', 's.reviewed_by', 's.reviewed_at',
                's.statement_number', 's.revoked_at', 's.revocation_reason', 's.created_at',
                'y.name as year_name')
            ->orderByDesc('s.id')->paginate(10);

        return view('dashboard.students.statements.index', [
            'institution' => $institution, 'profile' => $profile, 'years' => $years,
            'history' => $history,
            'canReview' => $statements->canReview($request->user(), $institution),
        ]);
    }

    public function store(
        Request $request, Institution $institution, int $student,
        StudentResultStatementService $statements
    ): RedirectResponse {
        $profile = $this->authorized($request, $institution, $student);
        $data = $request->validate(['academic_year_id' => ['required', 'integer']]);
        $year = AcademicYear::query()->where('institution_id', $institution->id)
            ->findOrFail($data['academic_year_id']);
        $statements->request($request->user(), $profile, $year);

        return redirect()->route('students.statements.index', [$institution, $profile->id])
            ->with('status', 'Results statement requested. A different authorized officer must review it.');
    }

    public function review(
        Request $request, Institution $institution, int $student, int $statement,
        StudentResultStatementService $statements
    ): RedirectResponse {
        $profile = $this->authorized($request, $institution, $student);
        abort_unless($statements->canReview($request->user(), $institution), 403);
        $this->record($institution, $profile, $statement);
        $data = $request->validate(['decision' => ['required', Rule::in(['issue', 'reject'])]]);
        $statements->review($request->user(), $profile, $statement, $data['decision']);

        return redirect()->route('students.statements.index', [$institution, $profile->id])
            ->with('status', 'Academic statement review recorded.');
    }

    public function revoke(
        Request $request, Institution $institution, int $student, int $statement,
        StudentResultStatementService $statements
    ): RedirectResponse {
        $profile = $this->authorized($request, $institution, $student);
        abort_unless($statements->canReview($request->user(), $institution), 403);
        $this->record($institution, $profile, $statement);
        $request->merge(['reason' => trim((string) $request->input('reason', ''))]);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:255']]);
        $statements->revoke($request->user(), $profile, $statement, $data['reason']);

        return redirect()->route('students.statements.index', [$institution, $profile->id])
            ->with('status', 'Issued statement revoked. The historical snapshot remains audit protected.');
    }

    public function show(
        Request $request, Institution $institution, int $student, int $statement,
        StudentResultStatementService $statements
    ): Response {
        $profile = $this->authorized($request, $institution, $student);
        $record = $this->record($institution, $profile, $statement);
        $snapshot = $statements->snapshot($record);
        return response()->view('dashboard.students.statements.show', [
            'institution' => $institution, 'profile' => $profile,
            'statement' => $record, 'snapshot' => $snapshot,
        ], 200, ['Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function export(
        Request $request, Institution $institution, int $student, int $statement,
        StudentResultStatementService $statements
    ): StreamedResponse {
        $profile = $this->authorized($request, $institution, $student);
        $record = $this->record($institution, $profile, $statement);
        $snapshot = $statements->snapshot($record);

        return response()->streamDownload(static function () use ($snapshot, $record): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open CSV export.');
            }
            $safe = static function ($value): string {
                $s = (string) $value;
                return preg_match('/^[=+\-@\t\r\n]/u', $s) ? "'".$s : $s;
            };
            fputcsv($stream, ['School-reviewed academic results statement — not a certified transcript']);
            fputcsv($stream, ['Statement number', $record->statement_number]);
            fputcsv($stream, ['Student number', $safe($snapshot['student_number'])]);
            fputcsv($stream, ['Student name', $safe($snapshot['student_name'])]);
            fputcsv($stream, ['Academic year', $safe($snapshot['academic_year'])]);
            fputcsv($stream, ['Snapshot SHA-256', $record->snapshot_sha256]);
            fputcsv($stream, ['Subject', 'Class', 'Assessment', 'Points', 'Maximum', 'Published at']);
            foreach ($snapshot['results'] as $result) {
                fputcsv($stream, [
                    $safe($result['subject']), $safe($result['class']),
                    $safe($result['assessment']), $result['points'],
                    $result['maximum'], $result['published_at'],
                ]);
            }
            fclose($stream);
        }, 'edvora-results-statement-'.$record->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
