<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\ClassSection;
use App\Models\ClassTimetableSlot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassTimetableService
{
    public function canCreate(User $user, ClassSection $section): bool
    {
        return $section->campus?->status === 'active'
            && $section->status === 'active'
            && $section->year?->status === 'active'
            && app(ClassroomService::class)->canManage($user, $section->institution);
    }

    public function create(User $actor, ClassSection $section, array $data): ClassTimetableSlot
    {
        if (! $this->canCreate($actor, $section)) {
            throw new AuthorizationException('Only MFA-verified school leadership can schedule active classes.');
        }

        if (! in_array((int) ($data['weekday'] ?? 0), range(1, 7), true)
            || ! preg_match('/^\d{2}:\d{2}$/D', $data['starts_at'] ?? '')
            || ! preg_match('/^\d{2}:\d{2}$/D', $data['ends_at'] ?? '')
            || $data['starts_at'] >= $data['ends_at']) {
            throw ValidationException::withMessages(['starts_at' => 'Enter a valid weekday and start/end time.']);
        }

        return DB::transaction(function () use ($actor, $section, $data): ClassTimetableSlot {
            // Institution-level row lock serializes room and assigned-teacher scheduling across campuses.
            Institution::query()->whereKey($section->institution_id)->lockForUpdate()->firstOrFail();

            $overlap = fn ($query) => $query
                ->where('weekday', $data['weekday'])
                ->where('starts_at', '<', $data['ends_at'])
                ->where('ends_at', '>', $data['starts_at']);

            $sameSection = $overlap(ClassTimetableSlot::query()
                ->where('class_section_id', $section->id))->exists();
            if ($sameSection) {
                throw ValidationException::withMessages(['starts_at' => 'Class already has an overlapping timetable slot.']);
            }

            if ($section->teacher_user_id !== null) {
                $teacherConflict = $overlap(ClassTimetableSlot::query()
                    ->join('class_sections', 'class_sections.id', '=', 'class_timetable_slots.class_section_id')
                    ->where('class_sections.institution_id', $section->institution_id)
                    ->where('class_sections.teacher_user_id', $section->teacher_user_id))
                    ->exists();
                if ($teacherConflict) {
                    throw ValidationException::withMessages(['starts_at' => 'The assigned teacher already has a class during that period.']);
                }
            }

            $room = trim($data['room_name'] ?? '');
            if ($room !== '') {
                $roomConflict = $overlap(ClassTimetableSlot::query()
                    ->where('campus_id', $section->campus_id)
                    ->where('room_name', $room))->exists();
                if ($roomConflict) {
                    throw ValidationException::withMessages(['room_name' => 'This room is already scheduled at that time.']);
                }
            }

            $slot = ClassTimetableSlot::create([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'weekday' => $data['weekday'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'room_name' => $room === '' ? null : $room,
                'created_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('class.timetable.created', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'campus_id' => $section->campus_id,
            ]);

            return $slot;
        });
    }
}
