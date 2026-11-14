<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperationalWorkspaces
{
    public const TABLES = [
        'library' => 'library_items',
        'cashier' => 'fee_proposals',
        'reception' => 'frontdesk_visits',
        'admissions' => 'admission_enquiries',
        'operations' => 'operations_tasks',
    ];

    public const SEARCH_COLUMNS = [
        'library' => ['title', 'code', 'author'],
        'cashier' => ['name'],
        'reception' => ['visitor_name', 'purpose'],
        'admissions' => ['contact_name', 'contact_email', 'program'],
        'operations' => ['title'],
    ];

    public function campus(User $actor, Institution $institution, int $campusId, string $module): Campus
    {
        abort_unless(isset(self::TABLES[$module]), 404);

        $campus = Campus::query()->where('institution_id', $institution->id)
            ->where('status', 'active')->with('institution')->findOrFail($campusId);
        if (! app(OperationalAccess::class)->canOperate($actor, $institution, $campus, $module)) {
            throw new AuthorizationException('This campus workspace is not assigned to your account.');
        }

        return $campus;
    }

    public function create(User $actor, Institution $institution, Campus $campus, string $module, array $input): int
    {
        $this->requireAccess($actor, $institution, $campus, $module);

        $common = [
            'institution_id' => $institution->id,
            'campus_id' => $campus->id,
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $extra = match ($module) {
            'library' => [
                'code' => strtoupper(trim($input['code'])),
                'title' => trim($input['title']),
                'author' => isset($input['author']) ? trim($input['author']) : null,
                'stock_total' => (int) $input['stock_total'],
            ],
            'cashier' => [
                'name' => trim($input['name']),
                'currency' => $input['currency'],
                'amount_minor' => $this->moneyMinor($input['amount'], $input['currency']),
                'status' => 'draft',
            ],
            'reception' => [
                'visitor_name' => trim($input['visitor_name']),
                'purpose' => trim($input['purpose']),
                'checked_in_at' => now(),
            ],
            'admissions' => [
                'contact_name' => trim($input['contact_name']),
                'contact_email' => mb_strtolower(trim($input['contact_email'])),
                'program' => trim($input['program']),
                'stage' => 'new',
            ],
            'operations' => [
                'title' => trim($input['title']),
                'priority' => $input['priority'],
                'due_on' => $input['due_on'] ?? null,
                'status' => 'open',
            ],
            default => throw new AuthorizationException('Unsupported workspace.'),
        };

        return DB::transaction(function () use ($module, $common, $extra, $actor, $institution, $campus): int {
            $id = DB::table(self::TABLES[$module])->insertGetId(array_merge($common, $extra));
            $event = match ($module) {
                'library' => 'library.item.created',
                'cashier' => 'fee.proposal.created',
                'reception' => 'reception.visit.checked_in',
                'admissions' => 'admissions.enquiry.created',
                'operations' => 'operations.task.created',
            };
            app(SecurityAudit::class)->record($event, $actor, null, $institution, [
                'campus_id' => $campus->id, 'operational_record_id' => $id, 'operational_module' => $module,
            ]);
            return $id;
        });
    }

    /**
     * No online charges, invoices, collection, receipt, or payment integration.
     * Fee proposals can be reviewed independently, but never posted as payable.
     */
    public function transition(
        User $actor,
        Institution $institution,
        Campus $campus,
        string $module,
        int $recordId,
        string $action
    ): void {
        $this->requireAccess($actor, $institution, $campus, $module);
        if ($module === 'library') {
            throw ValidationException::withMessages(['action' => 'Use issue or return loan for library circulation.']);
        }

        DB::transaction(function () use ($actor, $institution, $campus, $module, $recordId, $action): void {
            $record = DB::table(self::TABLES[$module])->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($recordId);
            abort_unless($record, 404);

            $changes = ['updated_at' => now()];
            if ($module === 'cashier') {
                if ($record->status !== 'draft' || ! in_array($action, ['review', 'void'], true)) {
                    throw ValidationException::withMessages(['action' => 'Only draft fee proposals can be reviewed or voided.']);
                }
                if ($action === 'review') {
                    if (! app(OperationalAccess::class)->canReviewFees($actor, $institution, $campus)
                        || (int) $record->created_by === (int) $actor->id) {
                        throw new AuthorizationException('An independent authorized school officer must review this proposal.');
                    }
                }
                $changes += [
                    'status' => $action === 'review' ? 'reviewed' : 'voided',
                    'reviewed_at' => now(),
                    'reviewed_by' => $actor->id,
                ];
                $event = $action === 'review' ? 'fee.proposal.reviewed' : 'fee.proposal.voided';
            } elseif ($module === 'reception') {
                if ($action !== 'checkout' || $record->checked_out_at !== null) {
                    throw ValidationException::withMessages(['action' => 'This visit is already closed or cannot be checked out.']);
                }
                $changes += ['checked_out_at' => now(), 'checked_out_by' => $actor->id];
                $event = 'reception.visit.checked_out';
            } elseif ($module === 'admissions') {
                $allowed = match ($record->stage) {
                    'new' => ['contacted', 'closed'],
                    'contacted' => ['qualified', 'closed'],
                    'qualified' => ['closed'],
                    default => [],
                };
                if (! in_array($action, $allowed, true)) {
                    throw ValidationException::withMessages(['action' => 'This admissions stage transition is not allowed.']);
                }
                $changes += ['stage' => $action, 'updated_by' => $actor->id];
                $event = 'admissions.enquiry.updated';
            } elseif ($module === 'operations') {
                $allowed = match ($record->status) {
                    'open' => ['in_progress', 'resolved'],
                    'in_progress' => ['open', 'resolved'],
                    default => [],
                };
                if (! in_array($action, $allowed, true)) {
                    throw ValidationException::withMessages(['action' => 'This task status transition is not allowed.']);
                }
                $changes += ['status' => $action, 'updated_by' => $actor->id];
                $event = 'operations.task.updated';
            } else {
                abort(404);
            }

            DB::table(self::TABLES[$module])->where('id', $recordId)->update($changes);
            app(SecurityAudit::class)->record($event, $actor, null, $institution, [
                'campus_id' => $campus->id,
                'operational_record_id' => $recordId,
                'operational_module' => $module,
            ]);
        });
    }

    public function issueLoan(
        User $actor,
        Institution $institution,
        Campus $campus,
        int $itemId,
        string $borrowerEmail,
        string $dueOn
    ): void {
        $this->requireAccess($actor, $institution, $campus, 'library');
        DB::transaction(function () use ($actor, $institution, $campus, $itemId, $borrowerEmail, $dueOn): void {
            // Lock the inventory row, not merely the active-loan count, to prevent overselling.
            $item = DB::table('library_items')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($itemId);
            abort_unless($item, 404);

            $email = mb_strtolower(trim($borrowerEmail));
            $borrower = User::query()->where('email', $email)->first();
            if (! $borrower || ! app(InstitutionAccess::class)->canViewCampus($borrower, $campus)) {
                throw ValidationException::withMessages([
                    'borrower_email' => 'An active account assigned to this campus is required.',
                ]);
            }

            $loans = DB::table('library_loans')
                ->where('institution_id', $institution->id)->where('campus_id', $campus->id)
                ->where('library_item_id', $item->id)->whereNull('returned_at');

            if ($loans->count() >= $item->stock_total) {
                throw ValidationException::withMessages(['item' => 'No copies are available for checkout.']);
            }
            if ($loans->where('borrower_user_id', $borrower->id)->exists()) {
                throw ValidationException::withMessages(['borrower_email' => 'This person already has an active loan of this item.']);
            }

            $id = DB::table('library_loans')->insertGetId([
                'institution_id' => $institution->id,
                'campus_id' => $campus->id,
                'library_item_id' => $item->id,
                'borrower_user_id' => $borrower->id,
                'due_on' => $dueOn,
                'loaned_at' => now(),
                'issued_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('library.loan.issued', $actor, null, $institution, [
                'campus_id' => $campus->id, 'operational_record_id' => $id, 'operational_module' => 'library',
            ]);
        });
    }

    public function returnLoan(User $actor, Institution $institution, Campus $campus, int $loanId): void
    {
        $this->requireAccess($actor, $institution, $campus, 'library');

        DB::transaction(function () use ($actor, $institution, $campus, $loanId): void {
            // Concurrent returns serialize by loan; second return fails without double count.
            $loan = DB::table('library_loans')->where('institution_id', $institution->id)
                ->where('campus_id', $campus->id)->lockForUpdate()->find($loanId);
            abort_unless($loan, 404);
            if ($loan->returned_at !== null) {
                throw ValidationException::withMessages(['loan' => 'This loan has already been returned.']);
            }
            DB::table('library_loans')->where('id', $loan->id)->update([
                'returned_at' => now(), 'returned_by' => $actor->id, 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('library.loan.returned', $actor, null, $institution, [
                'campus_id' => $campus->id, 'operational_record_id' => $loan->id, 'operational_module' => 'library',
            ]);
        });
    }

    private function moneyMinor(string $amount, string $currency): int
    {
        if ($currency === 'KHR') {
            if (! preg_match('/^\d{1,9}$/D', $amount) || (int) $amount < 1) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive whole-riel amount.']);
            }
            return (int) $amount;
        }
        if ($currency !== 'USD' || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid positive USD amount, with up to two decimals.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($minor < 1) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        return $minor;
    }

    private function requireAccess(User $actor, Institution $institution, Campus $campus, string $module): void
    {
        if (! app(OperationalAccess::class)->canOperate($actor, $institution, $campus, $module)) {
            throw new AuthorizationException('Your account is not authorized for this campus operation.');
        }
    }
}
