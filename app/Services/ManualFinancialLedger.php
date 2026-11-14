<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManualFinancialLedger
{
    public function campuses(User $actor, Institution $institution)
    {
        return app(OperationalAccess::class)->campuses($actor, $institution, 'cashier');
    }

    public function canReview(User $actor, Institution $institution, Campus $campus): bool
    {
        return app(OperationalAccess::class)->canReviewFees($actor, $institution, $campus);
    }

    private function authorize(User $actor, Institution $institution, Campus $campus): void
    {
        if (! app(OperationalAccess::class)->canOperate($actor, $institution, $campus, 'cashier')) {
            throw new AuthorizationException('Institution and campus cashier permissions are required.');
        }
    }

    public function createInvoice(
        User $actor, Institution $institution, Campus $campus, int $studentId,
        int $proposalId, string $number
    ): int {
        $this->authorize($actor, $institution, $campus);
        return DB::transaction(function () use ($actor, $institution, $campus, $studentId, $proposalId, $number): int {
            $student = DB::table('student_profiles')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->where('status', 'active')->find($studentId);
            if (! $student) {
                throw ValidationException::withMessages(['student_id' => 'Choose an active verified student in this campus.']);
            }
            $fee = DB::table('fee_proposals')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->where('status', 'reviewed')->find($proposalId);
            if (! $fee) {
                throw ValidationException::withMessages(['proposal_id' => 'Only independently reviewed campus fee proposals can be invoiced.']);
            }
            $code = strtoupper(trim($number));
            if (! preg_match('/^[A-Z0-9_-]{3,40}$/D', $code)) {
                throw ValidationException::withMessages(['invoice_number' => 'Invoice number must be 3–40 letters, numbers, dashes or underscores.']);
            }
            if (DB::table('student_invoices')->where('institution_id', $institution->id)
                ->where('invoice_number', $code)->exists()) {
                throw ValidationException::withMessages(['invoice_number' => 'Invoice number is already used at this school.']);
            }
            $id = DB::table('student_invoices')->insertGetId([
                'institution_id' => $institution->id, 'campus_id' => $campus->id,
                'student_profile_id' => $student->id, 'fee_proposal_id' => $fee->id,
                'invoice_number' => $code, 'currency' => $fee->currency,
                'amount_minor' => $fee->amount_minor, 'status' => 'draft',
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('billing.invoice.drafted', $actor, null, $institution, [
                'invoice_id' => $id, 'campus_id' => $campus->id,
            ]);
            return $id;
        });
    }

    public function reviewInvoice(
        User $actor, Institution $institution, Campus $campus, int $invoiceId, string $decision
    ): void {
        $this->authorize($actor, $institution, $campus);
        DB::transaction(function () use ($actor, $institution, $campus, $invoiceId, $decision): void {
            $invoice = DB::table('student_invoices')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($invoiceId);
            abort_unless($invoice, 404);
            if ($invoice->status !== 'draft' || ! in_array($decision, ['issue', 'void'], true)) {
                throw ValidationException::withMessages(['decision' => 'Only draft invoices may be reviewed.']);
            }
            if ($decision === 'issue' && (! $this->canReview($actor, $institution, $campus)
                || (int) $invoice->created_by === (int) $actor->id)) {
                throw new AuthorizationException('A second authorized school leader must issue the invoice.');
            }
            if ($decision === 'issue') {
                $fee = DB::table('fee_proposals')->where('institution_id', $institution->id)
                    ->where('campus_id', $campus->id)->where('status', 'reviewed')->find($invoice->fee_proposal_id);
                if (! $fee) {
                    throw ValidationException::withMessages(['decision' => 'Source proposal is no longer reviewed.']);
                }
                $student = DB::table('student_profiles')->where('institution_id', $institution->id)
                    ->where('campus_id', $campus->id)->where('status', 'active')->find($invoice->student_profile_id);
                if (! $student) {
                    throw ValidationException::withMessages(['decision' => 'Student is no longer active in this campus.']);
                }
            }
            DB::table('student_invoices')->where('id', $invoice->id)->update([
                'status' => $decision === 'issue' ? 'issued' : 'voided',
                'issued_by' => $decision === 'issue' ? $actor->id : null,
                'issued_at' => $decision === 'issue' ? now() : null,
                'voided_by' => $decision === 'void' ? $actor->id : null,
                'voided_at' => $decision === 'void' ? now() : null,
                'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record(
                $decision === 'issue' ? 'billing.invoice.issued' : 'billing.invoice.voided',
                $actor, null, $institution, ['invoice_id' => $invoice->id, 'campus_id' => $campus->id]
            );
        });
    }

    /** Offline bank statement claims remain pending until independently reviewed. */
    public function submitMatch(
        User $actor, Institution $institution, Campus $campus, int $invoiceId, string $reference, string $amount
    ): int {
        $this->authorize($actor, $institution, $campus);
        $reference = strtoupper(trim($reference));
        if (! preg_match('/^[A-Z0-9_-]{4,80}$/D', $reference)) {
            throw ValidationException::withMessages(['statement_reference' => 'Enter 4–80 characters from the approved bank statement reference.']);
        }

        return DB::transaction(function () use ($actor, $institution, $campus, $invoiceId, $reference, $amount): int {
            $invoice = DB::table('student_invoices')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($invoiceId);
            abort_unless($invoice, 404);
            if ($invoice->status !== 'issued') {
                throw ValidationException::withMessages(['invoice_id' => 'Only issued invoices can be manually matched.']);
            }
            $minor = $this->money($invoice->currency, $amount);
            $already = (int) DB::table('manual_reconciliation_entries')
                ->where('student_invoice_id', $invoice->id)
                ->whereIn('status', ['pending', 'verified'])->sum('amount_minor');
            if ($minor > (int) $invoice->amount_minor - $already) {
                throw ValidationException::withMessages(['amount' => 'The claim exceeds the remaining unmatched invoice amount.']);
            }
            if (DB::table('manual_reconciliation_entries')->where('institution_id', $institution->id)
                ->where('statement_reference', $reference)->exists()) {
                throw ValidationException::withMessages(['statement_reference' => 'This statement reference is already registered.']);
            }
            $id = DB::table('manual_reconciliation_entries')->insertGetId([
                'institution_id' => $institution->id, 'campus_id' => $campus->id,
                'student_invoice_id' => $invoice->id, 'statement_reference' => $reference,
                'amount_minor' => $minor, 'status' => 'pending', 'recorded_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('billing.match.recorded', $actor, null, $institution, [
                'invoice_id' => $invoice->id, 'reconciliation_id' => $id, 'campus_id' => $campus->id,
            ]);
            return $id;
        });
    }

    public function reviewMatch(
        User $actor, Institution $institution, Campus $campus, int $matchId, string $decision
    ): void {
        $this->authorize($actor, $institution, $campus);
        if (! $this->canReview($actor, $institution, $campus)) {
            throw new AuthorizationException('Only MFA-verified school leadership may review bank matches.');
        }

        DB::transaction(function () use ($actor, $institution, $campus, $matchId, $decision): void {
            $locator = DB::table('manual_reconciliation_entries')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->find($matchId);
            abort_unless($locator, 404);
            // All invoice modifications acquire the invoice row lock first to serialize totals.
            $invoice = DB::table('student_invoices')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($locator->student_invoice_id);
            abort_unless($invoice, 404);
            $match = DB::table('manual_reconciliation_entries')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->where('student_invoice_id', $invoice->id)
                ->lockForUpdate()->find($matchId);
            abort_unless($match, 404);
            if ($invoice->status !== 'issued' || $match->status !== 'pending'
                || ! in_array($decision, ['verify', 'reject'], true)) {
                throw ValidationException::withMessages(['decision' => 'Only pending claims against issued invoices may be reviewed.']);
            }
            if ((int) $match->recorded_by === (int) $actor->id) {
                throw new AuthorizationException('A different school officer must review the reconciliation claim.');
            }
            if ($decision === 'verify') {
                $verified = (int) DB::table('manual_reconciliation_entries')
                    ->where('student_invoice_id', $invoice->id)->where('status', 'verified')
                    ->sum('amount_minor');
                if ((int) $match->amount_minor > (int) $invoice->amount_minor - $verified) {
                    throw ValidationException::withMessages(['decision' => 'Verified matches would exceed the invoice amount.']);
                }
            }
            DB::table('manual_reconciliation_entries')->where('id', $match->id)->update([
                'status' => $decision === 'verify' ? 'verified' : 'rejected',
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('billing.match.reviewed', $actor, null, $institution, [
                'invoice_id' => $invoice->id, 'reconciliation_id' => $match->id,
                'campus_id' => $campus->id,
            ]);
        });
    }

    private function money(string $currency, string $amount): int
    {
        $pattern = $currency === 'KHR' ? '/^\d{1,10}$/D'
            : ($currency === 'USD' ? '/^\d{1,8}(?:\.\d{1,2})?$/D' : null);
        if ($pattern === null || ! preg_match($pattern, $amount)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount in the invoice currency.']);
        }
        $parts = explode('.', $amount, 2);
        $minor = $currency === 'USD'
            ? (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0')
            : (int) $parts[0];
        if ($minor < 1) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }
        return $minor;
    }
}
