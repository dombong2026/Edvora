<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Services\InternalInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class InternalInboxController extends Controller
{
    public function index(Request $request, Institution $institution, InternalInbox $inbox): View
    {
        Gate::authorize('view', $institution);
        $data = $request->validate(['filter' => ['nullable', Rule::in(['all', 'unread'])]]);
        $unreadOnly = ($data['filter'] ?? 'all') === 'unread';

        return view('dashboard.inbox.index', [
            'institution' => $institution,
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $inbox->unreadCount($request->user(), $institution),
            'notifications' => $inbox->list($request->user(), $institution, $unreadOnly)->withQueryString(),
        ]);
    }

    public function mark(
        Request $request, Institution $institution, int $notification, InternalInbox $inbox
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $data = $request->validate(['action' => ['required', Rule::in(['read', 'archive'])]]);
        $inbox->mark($request->user(), $institution, $notification, $data['action']);

        return redirect()->route('inbox.index', $institution)
            ->with('status', $data['action'] === 'read' ? 'Notification marked as read.' : 'Notification archived.');
    }
}
