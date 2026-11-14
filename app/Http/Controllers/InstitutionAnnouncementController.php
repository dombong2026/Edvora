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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class InstitutionAnnouncementController extends Controller
{
    public function index(Request $request, Institution $institution, InstitutionEngagement $access): View
    {
        Gate::authorize('view', $institution);
        $canManage = $access->canManage($request->user(), $institution);

        $query = DB::table('institution_announcements')->where('institution_id', $institution->id);
        if (! $canManage) {
            $campusIds = $access->campuses($request->user(), $institution)->pluck('id')->all();
            $query->where('status', 'published')->whereNotNull('published_at')
                ->where(function ($builder) use ($campusIds): void {
                    $builder->whereNull('campus_id');
                    if ($campusIds !== []) {
                        $builder->orWhereIn('campus_id', $campusIds);
                    }
                });
        }

        $announcements = $query->orderByDesc('id')->paginate(10);
        $readIds = DB::table('announcement_read_receipts')
            ->where('institution_id', $institution->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('announcement_id', $announcements->getCollection()->pluck('id')->all())
            ->pluck('announcement_id')->all();

        return view('dashboard.announcements.index', [
            'readIds' => $readIds,
            'institution' => $institution,
            'canManage' => $canManage,
            'campuses' => $canManage ? $access->campuses($request->user(), $institution) : collect(),
            'announcements' => $announcements,
        ]);
    }

    public function markRead(
        Request $request,
        Institution $institution,
        int $announcement,
        InstitutionEngagement $access
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $record = DB::table('institution_announcements')
            ->where('institution_id', $institution->id)->find($announcement);
        abort_unless($record, 404);
        // A read receipt is never created for drafts or archived/unassigned notices.
        abort_unless($record->status === 'published' && $record->published_at !== null
            && $access->canReadAnnouncement($request->user(), $institution, $record), 403);

        DB::table('announcement_read_receipts')->updateOrInsert([
            'institution_id' => $institution->id,
            'announcement_id' => $record->id,
            'user_id' => $request->user()->id,
        ], ['read_at' => now()]);

        return redirect()->route('announcements.index', $institution)
            ->with('status', 'Announcement marked as read.');
    }

    public function store(
        Request $request,
        Institution $institution,
        InstitutionEngagement $access
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($access->canManage($request->user(), $institution), 403);
        $request->merge([
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
        ]);
        $data = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $campusId = null;
        if (isset($data['campus_id'])) {
            $campus = Campus::query()->where('institution_id', $institution->id)
                ->where('status', 'active')->findOrFail($data['campus_id']);
            abort_unless($access->campuses($request->user(), $institution)
                ->contains('id', $campus->id), 403);
            $campusId = $campus->id;
        }

        DB::transaction(function () use ($request, $institution, $data, $campusId): void {
            $id = DB::table('institution_announcements')->insertGetId([
                'institution_id' => $institution->id,
                'campus_id' => $campusId,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('notice.drafted', $request->user(), null, $institution, [
                'announcement_id' => $id, 'campus_id' => $campusId,
            ]);
        });

        return redirect()->route('announcements.index', $institution)
            ->with('status', 'Announcement saved as a draft for independent publication review.');
    }

    public function show(
        Request $request,
        Institution $institution,
        int $announcement,
        InstitutionEngagement $access
    ): View {
        Gate::authorize('view', $institution);
        $record = DB::table('institution_announcements')
            ->where('institution_id', $institution->id)->find($announcement);
        abort_unless($record, 404);
        abort_unless($access->canReadAnnouncement($request->user(), $institution, $record), 403);

        return view('dashboard.announcements.show', [
            'institution' => $institution,
            'announcement' => $record,
            'canManage' => $access->canManage($request->user(), $institution),
        ]);
    }

    public function transition(
        Request $request,
        Institution $institution,
        int $announcement,
        InstitutionEngagement $access
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        abort_unless($access->canManage($request->user(), $institution), 403);
        $data = $request->validate(['action' => ['required', 'in:publish,archive']]);

        DB::transaction(function () use ($request, $institution, $announcement, $data, $access): void {
            $record = DB::table('institution_announcements')->where('institution_id', $institution->id)
                ->lockForUpdate()->find($announcement);
            abort_unless($record, 404);
            $publish = $data['action'] === 'publish';
            if ($publish) {
                if ($record->status !== 'draft') {
                    throw ValidationException::withMessages(['action' => 'Only drafts can be published.']);
                }
                if ((int) $record->created_by === (int) $request->user()->id) {
                    abort(403, 'A second authorized officer must approve publication.');
                }
                if ($record->campus_id !== null) {
                    $campus = Campus::query()->where('institution_id', $institution->id)
                        ->where('status', 'active')->find($record->campus_id);
                    if (! $campus) {
                        throw ValidationException::withMessages(['action' => 'Campus is no longer active.']);
                    }
                }
                $fields = ['status' => 'published', 'published_at' => now(),
                    'published_by' => $request->user()->id, 'updated_at' => now()];
                $event = 'notice.published';
            } else {
                if ($record->status !== 'published') {
                    throw ValidationException::withMessages(['action' => 'Only published announcements can be archived.']);
                }
                $fields = ['status' => 'archived', 'archived_at' => now(),
                    'archived_by' => $request->user()->id, 'updated_at' => now()];
                $event = 'notice.archived';
            }
            DB::table('institution_announcements')->where('id', $record->id)->update($fields);
            app(SecurityAudit::class)->record($event, $request->user(), null, $institution, [
                'announcement_id' => $record->id, 'campus_id' => $record->campus_id,
            ]);
        });

        return redirect()->route('announcements.show', [$institution, $announcement])
            ->with('status', 'Announcement review decision saved.');
    }
}
