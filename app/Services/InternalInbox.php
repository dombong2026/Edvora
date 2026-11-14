<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InternalInbox
{
    private const EVENTS = [
        'statement_issued' => 'Academic results statement issued',
        'statement_revoked' => 'Academic results statement revoked',
        'staff_leave_approved' => 'Your leave request was approved',
        'staff_leave_rejected' => 'Your leave request was not approved',
    ];

    public function send(
        Institution $institution,
        User $recipient,
        string $event,
        string $source,
        int $sourceId,
        string $path
    ): void {
        if (! isset(self::EVENTS[$event])
            || ! in_array($source, ['statement', 'leave'], true)
            || $sourceId < 1
            || ! str_starts_with($path, '/dashboard/institutions/'.$institution->slug.'/')) {
            throw new \InvalidArgumentException('Unsupported inbox event or internal destination.');
        }

        if (! app(InstitutionAccess::class)->canViewInstitution($recipient, $institution)) {
            return;
        }

        DB::table('internal_inbox_messages')->insertOrIgnore([
            'institution_id' => $institution->id,
            'recipient_user_id' => $recipient->id,
            'event_key' => $event,
            'source_type' => $source,
            'source_id' => $sourceId,
            'title' => self::EVENTS[$event],
            'path' => $path,
            'created_at' => now(),
        ]);
    }

    public function statementEvent(StudentProfile $student, int $id, string $event, int $requesterId): void
    {
        $institution = $student->institution;
        $path = route('students.statements.index', [
            'institution' => $institution, 'student' => $student->id,
        ], false);
        $ids = collect([$requesterId, $student->user_id])->filter()->unique();
        foreach ($ids as $userId) {
            $recipient = User::query()->find($userId);
            if ($recipient && app(StudentRegistryService::class)->canViewProfile($recipient, $student)) {
                $this->send($institution, $recipient, $event, 'statement', $id, $path);
            }
        }
    }

    public function leaveEvent(Institution $institution, int $id, int $staffId, string $status): void
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Unsupported leave decision.');
        }
        $recipient = User::query()->find($staffId);
        if ($recipient) {
            $this->send($institution, $recipient, 'staff_leave_'.$status, 'leave', $id,
                route('staff.leave.index', $institution, false));
        }
    }

    public function list(User $user, Institution $institution, bool $unread = false)
    {
        $this->authorize($user, $institution);
        return DB::table('internal_inbox_messages')
            ->where('institution_id', $institution->id)
            ->where('recipient_user_id', $user->id)
            ->whereNull('archived_at')
            ->when($unread, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id')->paginate(10);
    }

    public function unreadCount(User $user, Institution $institution): int
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return 0;
        }
        return DB::table('internal_inbox_messages')->where('institution_id', $institution->id)
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')->whereNull('archived_at')->count();
    }

    public function mark(User $user, Institution $institution, int $id, string $action): void
    {
        $this->authorize($user, $institution);
        if (! in_array($action, ['read', 'archive'], true)) {
            throw ValidationException::withMessages(['action' => 'Choose a supported inbox action.']);
        }
        DB::transaction(function () use ($user, $institution, $id, $action): void {
            $query = DB::table('internal_inbox_messages')->where('institution_id', $institution->id)
                ->where('recipient_user_id', $user->id)->whereNull('archived_at')
                ->lockForUpdate();
            $record = $query->find($id);
            abort_unless($record, 404);
            if ($action === 'read') {
                if ($record->read_at === null) {
                    DB::table('internal_inbox_messages')->where('id', $record->id)
                        ->update(['read_at' => now()]);
                }
            } else {
                DB::table('internal_inbox_messages')->where('id', $record->id)
                    ->update(['archived_at' => now(), 'read_at' => $record->read_at ?? now()]);
            }
        });
    }

    private function authorize(User $actor, Institution $institution): void
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($actor, $institution)) {
            throw new AuthorizationException('An active institution account is required.');
        }
    }
}
