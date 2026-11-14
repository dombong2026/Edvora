<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\ClassTimetableChange;
use App\Models\ClassTimetableSlot;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassTimetableChangeService
{
    public const REASONS = [
        'room_change',
        'staff_scheduling',
        'conflict_resolution',
        'operational_adjustment',
    ];

    public function canChange(User $actor, ClassSection $section): bool
    {
        return app(ClassTimetableService::class)->canCreate($actor, $section);
    }

    private function time(string $time): string
    {
        $clock = substr($time, 0, 5);
        if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $clock)
            || strlen($time) > 8) {
            throw ValidationException::withMessages(['starts_at' => 'Use a valid 24-hour HH:MM time.']);
        }

        return $clock;
    }

    private function normalized(array $data): array
    {
        $day = (int) ($data['weekday'] ?? 0);
        if ($day < 1 || $day > 7) {
            throw ValidationException::withMessages(['weekday' => 'Choose a valid weekday.']);
        }

        $start = $this->time((string) ($data['starts_at'] ?? ''));
        $end = $this->time((string) ($data['ends_at'] ?? ''));
        if ($start >= $end) {
            throw ValidationException::withMessages(['starts_at' => 'End time must be after start time.']);
        }

        $room = trim((string) ($data['room_name'] ?? ''));
        if (mb_strlen($room) > 100) {
            throw ValidationException::withMessages(['room_name' => 'Room name must not exceed 100 characters.']);
        }

        return [
            'weekday' => $day, 'starts_at' => $start, 'ends_at' => $end,
            'room_name' => $room === '' ? null : $room,
        ];
    }

    private function snapshot(ClassTimetableSlot $slot): array
    {
        return [
            'weekday' => (int) $slot->weekday,
            'starts_at' => $this->time((string) $slot->starts_at),
            'ends_at' => $this->time((string) $slot->ends_at),
            'room_name' => $slot->room_name,
        ];
    }

    private function lockedSlot(ClassSection $section, int $slotId): ClassTimetableSlot
    {
        return ClassTimetableSlot::query()
            ->where('institution_id', $section->institution_id)
            ->where('campus_id', $section->campus_id)
            ->where('class_section_id', $section->id)
            ->lockForUpdate()->findOrFail($slotId);
    }

    public function request(
        User $actor,
        ClassSection $section,
        int $slotId,
        array $data
    ): ClassTimetableChange {
        if (! $this->canChange($actor, $section)) {
            throw new AuthorizationException('Only MFA-verified institution leadership can request schedule revisions.');
        }

        $new = $this->normalized($data);
        if (! in_array($data['reason_code'] ?? null, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason_code' => 'Choose an approved schedule change reason.']);
        }

        return DB::transaction(function () use ($actor, $section, $slotId, $data, $new): ClassTimetableChange {
            // Both timetable creation and revision serialize on this institution lock.
            Institution::query()->whereKey($section->institution_id)->lockForUpdate()->firstOrFail();
            $slot = $this->lockedSlot($section, $slotId);
            $old = $this->snapshot($slot);

            if ($old === $new) {
                throw ValidationException::withMessages(['timetable' => 'The proposed timetable is unchanged.']);
            }

            if (ClassTimetableChange::query()
                ->where('timetable_slot_id', $slot->id)
                ->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['timetable' => 'This slot has a pending revision awaiting review.']);
            }

            $change = ClassTimetableChange::create([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'timetable_slot_id' => $slot->id,
                'old_weekday' => $old['weekday'],
                'old_starts_at' => $old['starts_at'],
                'old_ends_at' => $old['ends_at'],
                'old_room_name' => $old['room_name'],
                'new_weekday' => $new['weekday'],
                'new_starts_at' => $new['starts_at'],
                'new_ends_at' => $new['ends_at'],
                'new_room_name' => $new['room_name'],
                'reason_code' => $data['reason_code'],
                'requested_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('class.timetable.revision.requested', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'timetable_change_id' => $change->id,
            ]);

            return $change->fresh();
        });
    }

    private function checkConflicts(ClassSection $section, ClassTimetableSlot $slot, array $proposed): void
    {
        $overlap = fn ($query) => $query
            ->where('class_timetable_slots.id', '!=', $slot->id)
            ->where('class_timetable_slots.weekday', $proposed['weekday'])
            ->where('class_timetable_slots.starts_at', '<', $proposed['ends_at'])
            ->where('class_timetable_slots.ends_at', '>', $proposed['starts_at']);

        if ($overlap(ClassTimetableSlot::query()
            ->where('class_section_id', $section->id))->exists()) {
            throw ValidationException::withMessages(['timetable' => 'The proposed time conflicts with another period in this class.']);
        }

        if ($section->teacher_user_id !== null && $overlap(ClassTimetableSlot::query()
            ->join('class_sections', 'class_sections.id', '=', 'class_timetable_slots.class_section_id')
            ->where('class_sections.institution_id', $section->institution_id)
            ->where('class_sections.teacher_user_id', $section->teacher_user_id))->exists()) {
            throw ValidationException::withMessages(['timetable' => 'Assigned teacher is already scheduled during the proposed time.']);
        }

        if ($proposed['room_name'] !== null && $overlap(ClassTimetableSlot::query()
            ->where('campus_id', $section->campus_id)
            ->where('room_name', $proposed['room_name']))->exists()) {
            throw ValidationException::withMessages(['timetable' => 'The proposed room is occupied at that time.']);
        }
    }

    public function review(
        User $reviewer,
        ClassSection $section,
        ClassTimetableChange $change,
        bool $approve
    ): ClassTimetableChange {
        if (! $this->canChange($reviewer, $section)
            || (int) $change->institution_id !== (int) $section->institution_id
            || (int) $change->campus_id !== (int) $section->campus_id
            || (int) $change->class_section_id !== (int) $section->id
            || (int) $change->requested_by === (int) $reviewer->id) {
            throw new AuthorizationException('Schedule revisions require a separate MFA-enabled institution officer.');
        }

        return DB::transaction(function () use ($reviewer, $section, $change, $approve): ClassTimetableChange {
            Institution::query()->whereKey($section->institution_id)->lockForUpdate()->firstOrFail();
            $slot = $this->lockedSlot($section, $change->timetable_slot_id);
            $locked = ClassTimetableChange::query()->lockForUpdate()->findOrFail($change->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['timetable' => 'This revision has already been reviewed.']);
            }

            if ($approve) {
                $old = [
                    'weekday' => (int) $locked->old_weekday,
                    'starts_at' => $this->time((string) $locked->old_starts_at),
                    'ends_at' => $this->time((string) $locked->old_ends_at),
                    'room_name' => $locked->old_room_name,
                ];
                if ($this->snapshot($slot) !== $old) {
                    throw ValidationException::withMessages(['timetable' => 'The schedule changed; a new revision must be requested.']);
                }

                $proposed = $this->normalized([
                    'weekday' => $locked->new_weekday,
                    'starts_at' => $locked->new_starts_at,
                    'ends_at' => $locked->new_ends_at,
                    'room_name' => $locked->new_room_name,
                ]);
                $this->checkConflicts($section, $slot, $proposed);

                $slot->forceFill($proposed)->save();
            }

            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record(
                $approve ? 'class.timetable.revision.approved' : 'class.timetable.revision.rejected',
                $reviewer, null, $section->institution,
                ['class_section_id' => $section->id, 'timetable_change_id' => $locked->id]
            );

            return $locked;
        });
    }
}
