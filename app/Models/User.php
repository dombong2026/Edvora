<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_pending_secret' => 'encrypted',
            'mfa_pending_expires_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_last_counter' => 'integer',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(InstitutionMembership::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(InstitutionRoleAssignment::class);
    }

    public function platformAdministrator(): HasOne
    {
        return $this->hasOne(PlatformAdministrator::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platformAdministrator()->exists();
    }
}
