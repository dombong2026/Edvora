<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use App\Services\InstitutionEngagement;
use App\Services\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class InstitutionHelpdeskController extends Controller
{
    public function index(Request $request, Institution $institution, InstitutionEngagement $access): View
    {
        Gate::authorize('view', $institution);

        $user = $request->user();
        $campuses = $access->campuses($user, $institution);
        $officer = $access->canManage($user, $institution);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'answered', 'closed'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = DB::table('institution_help_tickets')
            ->where('institution_id', $institution->id);

        if (! $officer) {
            $query->where('created_by', $user->id)->whereIn('campus_id', $campuses->pluck('id')->all());
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        $search = trim($data['q'] ?? '');
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return view('dashboard.helpdesk.index', [
            'institution' => $institution,
            'campuses' => $campuses,
            'officer' => $officer,
            'tickets' => $query->orderByDesc('last_activity_at')->orderByDesc('id')
                ->paginate(10)->withQueryString(),
            'categories' => InstitutionEngagement::HELP_CATEGORIES,
            'statusFilter' => $data['status'] ?? '',
            'search' => $search,
        ]);
    }

    public function store(Request $request, Institution $institution, InstitutionEngagement $access): RedirectResponse
    {
        Gate::authorize('view', $institution);
        $request->merge([
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
        ]);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(InstitutionEngagement::HELP_CATEGORIES))],
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:4000'],
        ]);
        $campus = $access->campuses($request->user(), $institution)
            ->firstWhere('id', (int) $data['campus_id']);
        abort_unless($campus instanceof Campus, 403);

        $id = DB::transaction(function () use ($request, $institution, $campus, $data): int {
            $now = now();
            $ticketId = DB::table('institution_help_tickets')->insertGetId([
                'institution_id' => $institution->id,
                'campus_id' => $campus->id,
                'created_by' => $request->user()->id,
                'category' => $data['category'],
                'title' => $data['title'],
                'status' => 'open',
                'last_activity_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('institution_help_replies')->insert([
                'ticket_id' => $ticketId,
                'institution_id' => $institution->id,
                'campus_id' => $campus->id,
                'author_user_id' => $request->user()->id,
                'body' => $data['body'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            app(SecurityAudit::class)->record('help.ticket.opened', $request->user(), null, $institution, [
                'help_ticket_id' => $ticketId,
                'campus_id' => $campus->id,
            ]);
            return $ticketId;
        });

        return redirect()->route('helpdesk.show', [$institution, $id])
            ->with('status', 'Your support request was saved in your institution. No external message was sent.');
    }

    public function show(
        Request $request,
        Institution $institution,
        int $ticket,
        InstitutionEngagement $access
    ): View {
        Gate::authorize('view', $institution);

        $record = DB::table('institution_help_tickets')->where('institution_id', $institution->id)->find($ticket);
        abort_unless($record, 404);
        abort_unless($access->canReadTicket($request->user(), $institution, $record), 403);

        $replies = DB::table('institution_help_replies as replies')
            ->join('users as authors', 'replies.author_user_id', '=', 'authors.id')
            ->where('replies.institution_id', $institution->id)
            ->where('replies.campus_id', $record->campus_id)
            ->where('replies.ticket_id', $record->id)
            ->select('replies.id', 'replies.body', 'replies.created_at', 'replies.author_user_id', 'authors.name')
            ->orderBy('replies.id')->limit(200)->get();

        return view('dashboard.helpdesk.show', [
            'institution' => $institution,
            'ticket' => $record,
            'replies' => $replies,
            'isOfficer' => $access->canManage($request->user(), $institution),
        ]);
    }

    public function reply(
        Request $request,
        Institution $institution,
        int $ticket,
        InstitutionEngagement $access
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $request->merge(['body' => trim((string) $request->input('body', ''))]);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:4000']]);
        $record = DB::table('institution_help_tickets')->where('institution_id', $institution->id)->find($ticket);
        abort_unless($record, 404);
        abort_unless($access->canReadTicket($request->user(), $institution, $record), 403);

        DB::transaction(function () use ($request, $institution, $ticket, $data, $access): void {
            $locked = DB::table('institution_help_tickets')
                ->where('institution_id', $institution->id)->lockForUpdate()->find($ticket);
            abort_unless($locked && $access->canReadTicket($request->user(), $institution, $locked), 403);
            if ($locked->status === 'closed') {
                throw ValidationException::withMessages(['body' => 'Reopen this ticket before replying.']);
            }
            $now = now();
            DB::table('institution_help_replies')->insert([
                'ticket_id' => $locked->id,
                'institution_id' => $institution->id,
                'campus_id' => $locked->campus_id,
                'author_user_id' => $request->user()->id,
                'body' => $data['body'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $officerReply = (int) $request->user()->id !== (int) $locked->created_by
                && $access->canManage($request->user(), $institution);
            DB::table('institution_help_tickets')->where('id', $locked->id)->update([
                'status' => $officerReply ? 'answered' : 'open',
                'last_activity_at' => $now,
                'updated_at' => $now,
            ]);
            app(SecurityAudit::class)->record('help.ticket.replied', $request->user(), null, $institution, [
                'help_ticket_id' => $locked->id, 'campus_id' => $locked->campus_id,
            ]);
        });

        return redirect()->route('helpdesk.show', [$institution, $ticket])
            ->with('status', 'Reply saved to the private support thread.');
    }

    public function status(
        Request $request,
        Institution $institution,
        int $ticket,
        InstitutionEngagement $access
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $data = $request->validate(['action' => ['required', Rule::in(['close', 'reopen'])]]);
        $record = DB::table('institution_help_tickets')->where('institution_id', $institution->id)->find($ticket);
        abort_unless($record, 404);
        abort_unless($access->canReadTicket($request->user(), $institution, $record), 403);

        DB::transaction(function () use ($request, $institution, $ticket, $data, $access): void {
            $record = DB::table('institution_help_tickets')->where('institution_id', $institution->id)
                ->lockForUpdate()->find($ticket);
            abort_unless($record && $access->canReadTicket($request->user(), $institution, $record), 403);
            $isClose = $data['action'] === 'close';
            if (($isClose && $record->status === 'closed')
                || (! $isClose && $record->status !== 'closed')) {
                throw ValidationException::withMessages(['action' => 'This status transition is not available.']);
            }
            $now = now();
            DB::table('institution_help_tickets')->where('id', $record->id)->update([
                'status' => $isClose ? 'closed' : 'open',
                'closed_at' => $isClose ? $now : null,
                'closed_by' => $isClose ? $request->user()->id : null,
                'last_activity_at' => $now,
                'updated_at' => $now,
            ]);
            app(SecurityAudit::class)->record($isClose ? 'help.ticket.closed' : 'help.ticket.reopened',
                $request->user(), null, $institution, [
                    'help_ticket_id' => $record->id, 'campus_id' => $record->campus_id,
                ]);
        });

        return redirect()->route('helpdesk.show', [$institution, $ticket])
            ->with('status', 'Support request status updated.');
    }
}
