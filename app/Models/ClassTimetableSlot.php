<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClassTimetableSlot extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id',
        'weekday', 'starts_at', 'ends_at', 'room_name', 'created_by',
    ];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }
}
