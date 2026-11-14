<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClassAssessmentResult extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id',
        'assessment_id', 'class_enrollment_id', 'points_earned', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['points_earned' => 'decimal:2'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ClassAssessment::class, 'assessment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class, 'class_enrollment_id');
    }
}
