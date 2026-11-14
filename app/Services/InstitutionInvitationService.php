<?php

namespace App\Services;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class InstitutionInvitationService
{
    public function invite(
        User $actor,
        Institution $institution,
        string $email,
        AccountRole $role,
        ?Campus $campus
    ): InstitutionInvitation {
        // Reuse the existing authorization service; do not create broad role grants here.
        $access = app(InstitutionAccess::class);
        if (! $access->canViewInstitution($actor, $institution)
            || ! $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($q) => $q->whereIn('code', ['director', 'administrator']))
                ->exists()) {
            throw new AuthorizationException('Institution invitations require director or administrator privileges.');
        }

        if ($role === AccountRole::Admin) {
            throw ValidationException::withMessages(['role' => 'Platform Admin cannot be invited into an institution role.']);
        }

        if ($role === AccountRole::Director
            && ! $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($q) => $q->where('code', 'director'))
                ->exists()) {
            throw new AuthorizationException('Only directors can invite directors.');
        }

        if ($campus && ((int) $campus->institution_id !== (int) $institution->id || $campus->status !== 'active')) {
            throw ValidationException::withMessages(['campus_id' => 'Select an active campus in the specified institution.']);
        }

        if (! in_array($role->value, AccountRole::institutionWideCodes(), true) && ! $campus) {
            throw ValidationException::withMessages(['campus_id' => 'This role requires a specific campus.']);
        }

        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        $token = bin2hex(random_bytes(32));
        $invitation = InstitutionInvitation::create([
            'institution_id' => $institution->id,
            'campus_id' => $campus?->id,
            'role_id' => Role::where('code', $role->value)->firstOrFail()->id,
            'invited_by' => $actor->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
        ]);

        // Build from configured canonical APP_URL, never an untrusted request Host header.
        $url = rtrim(config('app.url'), '/').'/invites/'.$token;
        $message = "You were invited to ".$institution->name." on Edvora.\n\n"
            ."Role: ".$role->label()."\n"
            ."Invitation (expires in 48 hours): ".$url."\n\n"
            ."If you did not request this invitation, do not open the link.";

        try {
            Mail::raw($message, fn ($mail) => $mail->to($email)->subject('Invitation to '.$institution->name.' | Edvora'));
        } catch (\Throwable $exception) {
            $invitation->forceFill(['revoked_at' => now()])->save();
            throw $exception;
        }

        return $invitation;
    }

    /** Atomic, one-time invitation acceptance. Never reactivate suspended members. */
    public function accept(string $token, User $user): InstitutionInvitation
    {
        return DB::transaction(function () use ($token, $user): InstitutionInvitation {
            $invitation = InstitutionInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->firstOrFail();

            $invitation->load(['institution', 'role', 'campus']);
            if (! $invitation->isRedeemable() || ! hash_equals($invitation->email, mb_strtolower(trim($user->email)))) {
                throw new AuthorizationException('Invitation expired, used, revoked, or assigned to another account.');
            }

            if ($invitation->campus && ($invitation->campus->status !== 'active'
                || $invitation->campus->institution_id !== $invitation->institution_id)) {
                throw new AuthorizationException('The invited campus is no longer active.');
            }

            $membership = InstitutionMembership::query()->firstOrCreate(
                ['institution_id' => $invitation->institution_id, 'user_id' => $user->id],
                ['status' => 'active'],
            );
            if ($membership->status !== 'active') {
                throw new AuthorizationException('Suspended or revoked memberships require administrator review.');
            }

            $query = InstitutionRoleAssignment::query()
                ->where('institution_id', $invitation->institution_id)
                ->where('user_id', $user->id)
                ->where('role_id', $invitation->role_id);
            $invitation->campus_id ? $query->where('campus_id', $invitation->campus_id) : $query->whereNull('campus_id');
            if (! $query->exists()) {
                InstitutionRoleAssignment::create([
                    'institution_id' => $invitation->institution_id,
                    'user_id' => $user->id,
                    'role_id' => $invitation->role_id,
                    'campus_id' => $invitation->campus_id,
                    'assigned_by' => $invitation->invited_by,
                ]);
            }

            $invitation->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();

            return $invitation;
        });
    }
}
