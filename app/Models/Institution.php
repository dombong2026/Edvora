<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'timezone', 'locale'];

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(InstitutionMembership::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(InstitutionRoleAssignment::class);
    }
}
