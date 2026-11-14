<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClassAttendanceMark extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id',
        'attendance_session_id', 'class_enrollment_id', 'status', 'recorded_by',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class, 'class_enrollment_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassAttendanceSession::class, 'attendance_session_id');
    }
}
