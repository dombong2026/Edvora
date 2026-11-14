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
            'profile.updated', 'password.updated',
            'student.created', 'student.account_linked', 'guardian.verified', 'guardian.revoked',
            'student.lifecycle.requested', 'student.lifecycle.approved', 'student.lifecycle.rejected',
            'staff.leave.requested', 'staff.leave.reviewed', 'staff.leave.cancelled',
            'payroll.plan.drafted', 'payroll.plan.reviewed',
            'billing.invoice.drafted', 'billing.invoice.issued', 'billing.invoice.voided',
            'billing.match.recorded', 'billing.match.reviewed',
            'exam.schedule.drafted', 'exam.schedule.submitted',
            'exam.schedule.published', 'exam.schedule.rejected',
            'academics.year.activated', 'class.section.created', 'class.section.activated',
            'class.teacher.assigned', 'class.student.enrolled',
            'class.timetable.created',
            'class.timetable.revision.requested', 'class.timetable.revision.approved',
            'class.timetable.revision.rejected', 'attendance.opened', 'attendance.marked',
            'attendance.submitted', 'attendance.approved',
            'assessment.created', 'assessment.result.recorded',
            'assessment.submitted', 'assessment.published',
            'academic.correction.requested', 'academic.correction.approved',
            'academic.correction.rejected',
            'invitation.accepted', 'invitation.revoked', 'role.granted',
            'library.item.created', 'library.loan.issued', 'library.loan.returned',
            'fee.proposal.created', 'fee.proposal.reviewed', 'fee.proposal.voided',
            'reception.visit.checked_in', 'reception.visit.checked_out',
            'admissions.enquiry.created', 'admissions.enquiry.updated',
            'operations.task.created', 'operations.task.updated',
            'help.ticket.opened', 'help.ticket.replied', 'help.ticket.closed', 'help.ticket.reopened',
            'notice.drafted', 'notice.published', 'notice.archived',
        ], true)) {
            throw new \InvalidArgumentException('Unsupported audit event.');
        }

        foreach (array_keys($metadata) as $key) {
            if (! in_array($key, ['role', 'campus_id', 'invitation_id', 'student_profile_id', 'relationship', 'academic_year_id', 'class_section_id', 'attendance_session_id', 'assessment_id', 'academic_correction_id', 'timetable_change_id', 'operational_record_id', 'operational_module', 'help_ticket_id', 'announcement_id', 'lifecycle_request_id', 'staff_leave_id', 'payroll_plan_id', 'invoice_id', 'reconciliation_id', 'exam_schedule_id'], true)) {
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
