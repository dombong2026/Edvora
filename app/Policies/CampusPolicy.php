<?php

namespace App\Policies;

use App\Models\Campus;
use App\Models\User;
use App\Services\InstitutionAccess;

class CampusPolicy
{
    public function view(User $user, Campus $campus): bool
    {
        return app(InstitutionAccess::class)->canViewCampus($user, $campus);
    }
}
