<?php

namespace App\Providers;

use App\Models\Campus;
use App\Models\Institution;
use App\Policies\CampusPolicy;
use App\Policies\InstitutionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Institution::class, InstitutionPolicy::class);
        Gate::policy(Campus::class, CampusPolicy::class);
    }
}
