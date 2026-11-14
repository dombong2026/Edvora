<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionAccess;

class InstitutionPolicy
{
    public function view(User $user, Institution $institution): bool
    {
        return app(InstitutionAccess::class)->canViewInstitution($user, $institution);
    }
}
