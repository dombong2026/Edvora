<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClassAttendanceSession extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id', 'session_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ClassAttendanceMark::class, 'attendance_session_id');
    }
}
