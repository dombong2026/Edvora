<?php

namespace App\Console\Commands;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformAdministrator;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapEdvora extends Command
{
    protected $signature = 'edvora:bootstrap';
    protected $description = 'Interactively initialize the first institution and administrator on an empty installation';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Interactive terminal required; passwords are never accepted as command-line arguments.');
            return self::FAILURE;
        }
        if (User::query()->exists() || Institution::query()->exists() || PlatformAdministrator::query()->exists()) {
            $this->error('Bootstrap is only allowed on an empty installation. No changes made.');
            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Institution name'),
            'slug' => $this->ask('Institution slug (lowercase letters, digits, hyphens)'),
            'campus' => $this->ask('First campus name'),
            'email' => $this->ask('Initial administrator email'),
            'admin_name' => $this->ask('Initial administrator name'),
            'password' => $this->secret('Initial administrator password (12+ characters)'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:100'],
            'campus' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }
        if (! $this->confirm('Create the first administrator and institution?', false)) {
            $this->warn('Bootstrap cancelled.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($data): void {
            (new RoleSeeder)->run();
            $institution = Institution::create(['name' => $data['name'], 'slug' => $data['slug'], 'status' => 'active']);
            Campus::create(['institution_id' => $institution->id, 'name' => $data['campus'], 'slug' => 'main', 'status' => 'active']);
            $user = User::create(['name' => $data['admin_name'], 'email' => $data['email'], 'password' => $data['password']]);
            PlatformAdministrator::create(['user_id' => $user->id]);
            InstitutionMembership::create(['user_id' => $user->id, 'institution_id' => $institution->id, 'status' => 'active']);
            InstitutionRoleAssignment::create([
                'user_id' => $user->id, 'institution_id' => $institution->id,
                'role_id' => Role::where('code', AccountRole::Director->value)->firstOrFail()->id,
                'campus_id' => null, 'assigned_by' => $user->id,
            ]);
        });
        $this->info('Edvora initial institution created. Store your access credentials securely.');
        return self::SUCCESS;
    }
}
