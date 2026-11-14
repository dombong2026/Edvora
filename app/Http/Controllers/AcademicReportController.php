<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Services\AcademicReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AcademicReportController extends Controller
{
    public function index(Request $request, Institution $institution, AcademicReporting $reports): View
    {
        Gate::authorize('view', $institution);
        $reports->authorize($request->user(), $institution);
        $campuses = $reports->campuses($request->user(), $institution);
        $campus = $this->selectedCampus($request, $campuses);

        return view('dashboard.reports.index', [
            'institution' => $institution,
            'campuses' => $campuses,
            'selectedCampus' => $campus,
            'metrics' => $reports->metrics($institution, $campus),
            'labels' => AcademicReporting::METRICS,
        ]);
    }

    public function export(Request $request, Institution $institution, AcademicReporting $reports): StreamedResponse
    {
        Gate::authorize('view', $institution);
        $reports->authorize($request->user(), $institution);
        $campuses = $reports->campuses($request->user(), $institution);
        $campus = $this->selectedCampus($request, $campuses);
        $metrics = $reports->metrics($institution, $campus);

        $name = 'edvora-academic-summary-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(static function () use ($metrics): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fputcsv($stream, ['Metric', 'Count']);
            foreach (AcademicReporting::METRICS as $key => $label) {
                fputcsv($stream, [$label, (int) $metrics[$key]]);
            }
            fclose($stream);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function selectedCampus(Request $request, $campuses): ?\App\Models\Campus
    {
        $data = $request->validate(['campus_id' => ['nullable', 'integer']]);
        if (! isset($data['campus_id'])) {
            return null;
        }
        $campus = $campuses->firstWhere('id', (int) $data['campus_id']);
        abort_unless($campus, 403);

        return $campus;
    }
}
