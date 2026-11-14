<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClassAssessment extends Model
{
    protected $fillable = [
        'institution_id', 'campus_id', 'class_section_id',
        'code', 'title', 'max_points', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ClassAssessmentResult::class, 'assessment_id');
    }
}
