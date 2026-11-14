<?php

namespace Database\Seeders;

use App\Enums\AccountRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AccountRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role->value],
                ['name' => $role->label()],
            );
        }
    }
}
