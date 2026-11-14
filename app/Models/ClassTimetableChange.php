<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClassTimetableChange extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id', 'timetable_slot_id',
        'old_weekday', 'old_starts_at', 'old_ends_at', 'old_room_name',
        'new_weekday', 'new_starts_at', 'new_ends_at', 'new_room_name',
        'reason_code', 'requested_by',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ClassTimetableSlot::class, 'timetable_slot_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
