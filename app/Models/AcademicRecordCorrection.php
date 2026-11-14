<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AcademicRecordCorrection extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id',
        'attendance_mark_id', 'assessment_result_id', 'kind',
        'from_value', 'to_value', 'reason_code', 'requested_by',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function attendanceMark(): BelongsTo
    {
        return $this->belongsTo(ClassAttendanceMark::class, 'attendance_mark_id');
    }

    public function assessmentResult(): BelongsTo
    {
        return $this->belongsTo(ClassAssessmentResult::class, 'assessment_result_id');
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
