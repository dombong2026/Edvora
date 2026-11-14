<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SecurityAudit
{
    /** Deliberately small allowlist; never log passwords, TOTP seeds, tokens or recovery codes. */
    public function record(
        string $event,
        ?User $actor = null,
        ?User $subject = null,
        ?Institution $institution = null,
        array $metadata = []
    ): void {
        if (! in_array($event, [
            'mfa.enabled', 'mfa.recovery.used', 'invitation.sent',
            'student.created', 'student.account_linked', 'guardian.verified', 'guardian.revoked',
            'academics.year.activated', 'class.section.created', 'class.section.activated',
            'class.teacher.assigned', 'class.student.enrolled',
            'invitation.accepted', 'invitation.revoked', 'role.granted',
        ], true)) {
            throw new \InvalidArgumentException('Unsupported audit event.');
        }

        foreach (array_keys($metadata) as $key) {
            if (! in_array($key, ['role', 'campus_id', 'invitation_id', 'student_profile_id', 'relationship', 'academic_year_id', 'class_section_id'], true)) {
                throw new \InvalidArgumentException('Unsupported audit metadata.');
            }
        }

        DB::table('security_events')->insert([
            'institution_id' => $institution?->id,
            'actor_user_id' => $actor?->id,
            'subject_user_id' => $subject?->id,
            'event_type' => $event,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
