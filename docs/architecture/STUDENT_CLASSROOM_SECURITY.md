# Student, guardian and classroom security boundary

Status: development foundation, synthetic QA only. No real pupil records
or live parent data may be imported before formal school-data acceptance.

## Student profiles

StudentProfile holds institution_id, campus_id, a campus-issued student number,
a display name, optional verified User link, status and creation provenance.
It intentionally omits dates of birth, home addresses, medical, disciplinary
and safeguarding records. Future sensitive-data domains require separate
retention, authorization and encryption acceptance.

Institution+campus composite foreign keys prevent a record being attached to
a campus outside its institution. A student-user link must reference an active
student role in the same campus; a second record cannot silently claim the
same student account in an institution.

## Guardian relationships

Only MFA-enabled school director or institution administrator can independently
verify a parent-role account for an active campus, never their own account.
Relationships record verification actor/time, and revocation actor/time.
A revoked relationship cannot be self-reactivated. Existing parent/student
memberships must be active for record viewing.

The verification UI is not documentary proof of custody. Before real-world
activation, require a school-approved evidence review and restricted handling
of custody changes, parent rights, and exceptional legal circumstances.

## Academic classes

A class section belongs to one institution, campus, active academic year and
subject. Creation starts in draft. Activation requires an assigned teacher
with an active campus teaching/professor role. Capacity-bound enrollment is
only allowed into active classes for students in the same campus.

Composite foreign keys enforce institution/campus match between enrollment,
class and student independently of controller validation. Unauthorized users
cannot enumerate unrelated sections from the class index or direct ID routes.

Teacher class rosters require an explicit teacher assignment and active campus
teaching role, plus MFA enrollment. Parents and students can see only their
own enrolled section details and cannot view other classmates or rosters.
Institution-wide authorized leadership may review its institution's classes.

Implemented as limited foundations: weekly timetable slots, class attendance
registers, independently approved student attendance, and independently
published assessment scores. These workflows enforce class/campus/tenant
relationships and restrict families to their own student records.

Not implemented: automatic schedule rescheduling, attendance correction
workflows, consolidated grades/transcripts, class promotion, student
withdrawal and reenrollment after transfer, concurrent co-teachers,
formal academic-year closeout, student evidence retention, or custody approval.

Security review must test concurrency, tenant ownership, row-level visibility
and status transitions against synthetic data before production acceptance.
