<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Services\OperationalAccess;
use App\Services\OperationalWorkspaces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OperationalWorkspaceController extends Controller
{
    public function index(Request $request, Institution $institution, string $module, OperationalAccess $access): View
    {
        abort_unless(isset(OperationalWorkspaces::TABLES[$module]), 404);
        Gate::authorize('view', $institution);

        $campuses = $access->campuses($request->user(), $institution, $module);
        abort_if($campuses->isEmpty(), 403);

        $input = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $campus = isset($input['campus_id'])
            ? $campuses->firstWhere('id', (int) $input['campus_id'])
            : $campuses->first();
        abort_unless($campus, 403);

        $query = trim($input['q'] ?? '');
        $recordsQuery = DB::table(OperationalWorkspaces::TABLES[$module])
            ->where('institution_id', $institution->id)
            ->where('campus_id', $campus->id);

        if ($query !== '') {
            $columns = OperationalWorkspaces::SEARCH_COLUMNS[$module];
            $recordsQuery->where(function ($builder) use ($columns, $query): void {
                foreach ($columns as $column) {
                    $builder->orWhere($column, 'like', '%'.$query.'%');
                }
            });
        }

        $records = $recordsQuery->orderByDesc('id')->paginate(10)->withQueryString();
        $loans = collect();
        $available = [];
        if ($module === 'library') {
            // Bound queries retain both tenant and campus scope, including joined identities.
            $loans = DB::table('library_loans as loans')
                ->join('library_items as items', 'loans.library_item_id', '=', 'items.id')
                ->join('users as borrowers', 'loans.borrower_user_id', '=', 'borrowers.id')
                ->where('loans.institution_id', $institution->id)
                ->where('loans.campus_id', $campus->id)
                ->where('items.institution_id', $institution->id)
                ->where('items.campus_id', $campus->id)
                ->whereNull('loans.returned_at')
                ->select('loans.id', 'loans.library_item_id', 'loans.due_on', 'items.title', 'borrowers.name', 'borrowers.email')
                ->orderByDesc('loans.id')->limit(30)->get();

            foreach ($records as $record) {
                $active = DB::table('library_loans')->where('institution_id', $institution->id)
                    ->where('campus_id', $campus->id)->where('library_item_id', $record->id)
                    ->whereNull('returned_at')->count();
                $available[$record->id] = max(0, $record->stock_total - $active);
            }
        }

        return view('dashboard.workspaces.index', [
            'institution' => $institution,
            'module' => $module,
            'title' => OperationalAccess::TITLES[$module],
            'campuses' => $campuses,
            'campus' => $campus,
            'query' => $query,
            'records' => $records,
            'loans' => $loans,
            'available' => $available,
            'canReviewFees' => $module === 'cashier'
                && $access->canReviewFees($request->user(), $institution, $campus),
        ]);
    }

    public function store(
        Request $request,
        Institution $institution,
        string $module,
        OperationalWorkspaces $workspaces
    ): RedirectResponse {
        abort_unless(isset(OperationalWorkspaces::TABLES[$module]), 404);
        Gate::authorize('view', $institution);
        $data = $request->validate(array_merge(['campus_id' => ['required', 'integer']], match ($module) {
            'library' => [
                'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/D',
                    Rule::unique('library_items')->where(fn ($q) => $q
                        ->where('institution_id', $institution->id)->where('campus_id', $request->input('campus_id')))],
                'title' => ['required', 'string', 'min:2', 'max:180'],
                'author' => ['nullable', 'string', 'max:140'],
                'stock_total' => ['required', 'integer', 'min:1', 'max:1000'],
            ],
            'cashier' => [
                'name' => ['required', 'string', 'min:2', 'max:160'],
                'currency' => ['required', Rule::in(['USD', 'KHR'])],
                'amount' => ['required', 'string', 'max:16'],
            ],
            'reception' => [
                'visitor_name' => ['required', 'string', 'min:2', 'max:140'],
                'purpose' => ['required', 'string', 'min:2', 'max:180'],
            ],
            'admissions' => [
                'contact_name' => ['required', 'string', 'min:2', 'max:140'],
                'contact_email' => ['required', 'email', 'max:255'],
                'program' => ['required', 'string', 'min:2', 'max:160'],
            ],
            'operations' => [
                'title' => ['required', 'string', 'min:3', 'max:180'],
                'priority' => ['required', Rule::in(['normal', 'high'])],
                'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            ],
        }));

        $campus = $workspaces->campus($request->user(), $institution, (int) $data['campus_id'], $module);
        $workspaces->create($request->user(), $institution, $campus, $module, $data);

        return redirect()->route('workspaces.index', [
            'institution' => $institution, 'module' => $module, 'campus_id' => $campus->id,
        ])->with('status', match ($module) {
            'library' => 'Library item saved.',
            'cashier' => 'Draft fee proposal saved. No charge or payment was created.',
            'reception' => 'Visitor checked in.',
            'admissions' => 'Admissions enquiry saved. No message was sent.',
            'operations' => 'Operations task created.',
        });
    }

    public function transition(
        Request $request,
        Institution $institution,
        string $module,
        int $record,
        OperationalWorkspaces $workspaces
    ): RedirectResponse {
        abort_unless(isset(OperationalWorkspaces::TABLES[$module]), 404);
        Gate::authorize('view', $institution);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'max:40'],
        ]);
        $campus = $workspaces->campus($request->user(), $institution, (int) $data['campus_id'], $module);
        $workspaces->transition($request->user(), $institution, $campus, $module, $record, $data['action']);

        return redirect()->route('workspaces.index', [
            'institution' => $institution, 'module' => $module, 'campus_id' => $campus->id,
        ])->with('status', 'Workspace record updated and logged.');
    }

    public function issueLoan(
        Request $request,
        Institution $institution,
        int $item,
        OperationalWorkspaces $workspaces
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'borrower_email' => ['required', 'email', 'max:255'],
            'due_on' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
        ]);
        $campus = $workspaces->campus($request->user(), $institution, (int) $data['campus_id'], 'library');
        $workspaces->issueLoan($request->user(), $institution, $campus, $item, $data['borrower_email'], $data['due_on']);

        return redirect()->route('workspaces.index', [
            'institution' => $institution, 'module' => 'library', 'campus_id' => $campus->id,
        ])->with('status', 'Library loan issued to the verified campus member.');
    }

    public function returnLoan(
        Request $request,
        Institution $institution,
        int $loan,
        OperationalWorkspaces $workspaces
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $data = $request->validate(['campus_id' => ['required', 'integer']]);
        $campus = $workspaces->campus($request->user(), $institution, (int) $data['campus_id'], 'library');
        $workspaces->returnLoan($request->user(), $institution, $campus, $loan);

        return redirect()->route('workspaces.index', [
            'institution' => $institution, 'module' => 'library', 'campus_id' => $campus->id,
        ])->with('status', 'Library item returned and inventory availability updated.');
    }
}
