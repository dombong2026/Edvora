<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use App\Services\ManualFinancialLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ManualFinancialController extends Controller
{
    private function selectedCampus(Request $request, Institution $institution, ManualFinancialLedger $ledger): Campus
    {
        Gate::authorize('view', $institution);
        $campuses = $ledger->campuses($request->user(), $institution);
        abort_if($campuses->isEmpty(), 403);
        $data = $request->validate(['campus_id' => ['required', 'integer']]);

        $campus = $campuses->firstWhere('id', (int) $data['campus_id']);
        abort_unless($campus, 403);
        return $campus;
    }

    public function index(Request $request, Institution $institution, ManualFinancialLedger $ledger): View
    {
        Gate::authorize('view', $institution);
        $campuses = $ledger->campuses($request->user(), $institution);
        abort_if($campuses->isEmpty(), 403);
        $data = $request->validate(['campus_id' => ['nullable', 'integer']]);
        $campus = isset($data['campus_id'])
            ? $campuses->firstWhere('id', (int) $data['campus_id'])
            : $campuses->first();
        abort_unless($campus, 403);

        $invoices = DB::table('student_invoices as i')
            ->join('student_profiles as students', function ($join): void {
                $join->on('students.id', '=', 'i.student_profile_id')
                    ->on('students.institution_id', '=', 'i.institution_id');
            })
            ->where('i.institution_id', $institution->id)
            ->where('i.campus_id', $campus->id)
            ->select('i.*', 'students.display_name as student_name', 'students.student_number')
            ->orderByDesc('i.id')->paginate(10)->withQueryString();

        $matches = DB::table('manual_reconciliation_entries as m')
            ->join('student_invoices as i', 'm.student_invoice_id', '=', 'i.id')
            ->where('m.institution_id', $institution->id)
            ->where('m.campus_id', $campus->id)
            ->where('i.institution_id', $institution->id)
            ->where('i.campus_id', $campus->id)
            ->select('m.*', 'i.invoice_number', 'i.currency')
            ->orderByDesc('m.id')->limit(30)->get();

        $reviewedProposals = DB::table('fee_proposals')
            ->where('institution_id', $institution->id)->where('campus_id', $campus->id)
            ->where('status', 'reviewed')
            ->orderByDesc('id')->limit(100)->get();
        $students = DB::table('student_profiles')
            ->where('institution_id', $institution->id)->where('campus_id', $campus->id)
            ->where('status', 'active')->orderBy('student_number')->limit(200)->get();

        $verifiedTotals = DB::table('manual_reconciliation_entries')
            ->where('institution_id', $institution->id)
            ->where('campus_id', $campus->id)
            ->where('status', 'verified')
            ->select('student_invoice_id', DB::raw('SUM(amount_minor) as total'))
            ->groupBy('student_invoice_id')->pluck('total', 'student_invoice_id');

        return view('dashboard.finance.index', [
            'institution' => $institution,
            'campuses' => $campuses, 'campus' => $campus,
            'students' => $students, 'reviewedProposals' => $reviewedProposals,
            'invoices' => $invoices, 'matches' => $matches, 'verifiedTotals' => $verifiedTotals,
            'canReview' => $ledger->canReview($request->user(), $institution, $campus),
        ]);
    }

    public function storeInvoice(
        Request $request, Institution $institution, ManualFinancialLedger $ledger
    ): RedirectResponse {
        $campus = $this->selectedCampus($request, $institution, $ledger);
        $data = $request->validate([
            'student_profile_id' => ['required', 'integer'],
            'fee_proposal_id' => ['required', 'integer'],
            'invoice_number' => ['required', 'string', 'max:40'],
        ]);
        $ledger->createInvoice($request->user(), $institution, $campus,
            (int) $data['student_profile_id'], (int) $data['fee_proposal_id'],
            $data['invoice_number']);

        return redirect()->route('finance.index', [
            'institution' => $institution, 'campus_id' => $campus->id,
        ])->with('status', 'Invoice draft created from an approved fee proposal. No external bill was sent.');
    }

    public function reviewInvoice(
        Request $request, Institution $institution, int $invoice, ManualFinancialLedger $ledger
    ): RedirectResponse {
        $campus = $this->selectedCampus($request, $institution, $ledger);
        $data = $request->validate(['decision' => ['required', Rule::in(['issue', 'void'])]]);
        $ledger->reviewInvoice($request->user(), $institution, $campus, $invoice, $data['decision']);

        return redirect()->route('finance.index', [
            'institution' => $institution, 'campus_id' => $campus->id,
        ])->with('status', 'Invoice review saved internally. No payment or email was sent.');
    }

    public function storeMatch(
        Request $request, Institution $institution, int $invoice, ManualFinancialLedger $ledger
    ): RedirectResponse {
        $campus = $this->selectedCampus($request, $institution, $ledger);
        $request->merge([
            'statement_reference' => strtoupper(trim((string) $request->input('statement_reference', ''))),
            'amount' => trim((string) $request->input('amount', '')),
        ]);
        $data = $request->validate([
            'statement_reference' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'string', 'max:16'],
        ]);
        $ledger->submitMatch($request->user(), $institution, $campus, $invoice,
            $data['statement_reference'], $data['amount']);

        return redirect()->route('finance.index', [
            'institution' => $institution, 'campus_id' => $campus->id,
        ])->with('status', 'Manual bank-statement match recorded for independent review; no bank settlement was verified automatically.');
    }

    public function reviewMatch(
        Request $request, Institution $institution, int $match, ManualFinancialLedger $ledger
    ): RedirectResponse {
        $campus = $this->selectedCampus($request, $institution, $ledger);
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'reject'])]]);
        $ledger->reviewMatch($request->user(), $institution, $campus, $match, $data['decision']);

        return redirect()->route('finance.index', [
            'institution' => $institution, 'campus_id' => $campus->id,
        ])->with('status', 'Independent internal reconciliation decision recorded. This is not a bank receipt.');
    }
}
