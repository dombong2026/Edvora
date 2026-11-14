<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campus extends Model
{
    protected $fillable = ['institution_id', 'name', 'slug', 'status'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
