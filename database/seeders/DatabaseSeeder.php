<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe for production: role definitions only, never demonstration users or data.
        $this->call(RoleSeeder::class);
    }
}
