<?php

namespace App\Services;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MfaService
{
    public function __construct(private readonly Totp $totp) {}

    public function begin(User $user): string
    {
        if ($user->mfa_enabled_at) {
            throw ValidationException::withMessages(['mfa' => 'Two-factor authentication is already active.']);
        }

        if ($user->mfa_pending_secret && $user->mfa_pending_expires_at?->isFuture()) {
            return $user->mfa_pending_secret;
        }

        $user->forceFill([
            'mfa_pending_secret' => $this->totp->newSecret(),
            'mfa_pending_expires_at' => now()->addMinutes(15),
        ])->save();

        return $user->mfa_pending_secret;
    }

    /** Enable MFA only after password re-entry and possession of the authenticator code. */
    public function enable(User $user, string $password, string $code): array
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'Current password is invalid.']);
        }

        return DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->mfa_enabled_at || ! $locked->mfa_pending_secret || ! $locked->mfa_pending_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['code' => 'Setup expired or already completed. Start again.']);
            }

            $step = $this->totp->verify($locked->mfa_pending_secret, $code, null);
            if ($step === null) {
                throw ValidationException::withMessages(['code' => 'Invalid authentication code.']);
            }

            $locked->forceFill([
                'mfa_secret' => $locked->mfa_pending_secret,
                'mfa_pending_secret' => null,
                'mfa_pending_expires_at' => null,
                'mfa_enabled_at' => now(),
                'mfa_last_counter' => $step,
            ])->save();

            // Recovery codes are unique high-entropy tokens, hashed and shown once.
            $codes = [];
            for ($i = 0; $i < 8; $i++) {
                $plain = strtoupper(bin2hex(random_bytes(12)));
                $codes[] = $plain;
                MfaRecoveryCode::create([
                    'user_id' => $locked->id,
                    'code_hash' => hash('sha256', $plain),
                ]);
            }

            return $codes;
        });
    }

    /** Consume exactly one fresh TOTP time step or one unused recovery code. */
    public function verifyAndConsume(User $user, string $input): bool
    {
        return DB::transaction(function () use ($user, $input): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $locked->mfa_enabled_at || ! $locked->mfa_secret) {
                return false;
            }

            $step = $this->totp->verify($locked->mfa_secret, trim($input), $locked->mfa_last_counter);
            if ($step !== null) {
                $locked->forceFill(['mfa_last_counter' => $step])->save();
                return true;
            }

            $normalized = strtoupper(str_replace(['-', ' '], '', trim($input)));
            if (! preg_match('/^[A-F0-9]{24}$/D', $normalized)) {
                return false;
            }

            $code = MfaRecoveryCode::query()
                ->where('user_id', $locked->id)
                ->where('code_hash', hash('sha256', $normalized))
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();
            if (! $code) {
                return false;
            }

            $code->forceFill(['used_at' => now()])->save();
            return true;
        });
    }
}
