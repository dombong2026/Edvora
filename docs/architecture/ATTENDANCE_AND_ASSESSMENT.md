# Edvora timetable, attendance and assessment foundation

Status: **synthetic CI-tested development only**. No production timetable,
real attendance or examination data is activated.

## Timetable slots

The institution's timezone (IANA name, initially Asia/Phnom_Penh) defines
recurring weekday and clock times. Only MFA-enabled institutional leadership
can create slots for active class sections. Transactions lock the institution
row, then check same-class overlaps, same-campus room conflicts and
assigned-teacher conflicts across its classrooms. Timetable slots carry
institution, campus and section composite foreign keys.

The current scope has no timetable edit/delete, calendar holidays,
cross-institution teacher conflict guarantee, substitute-teacher workflow,
room capacity engine, academic-term recurrence dates or independent
approval of staff reassignments. Do not use it for a live school timetable.

## Attendance

1. Assigned MFA-enabled teacher opens a single date-scoped attendance register
   for an active class, with date within the active academic year and not after
   the institution-local current date.
2. Teacher records one of present/absent/late/excused for each eligible
   active enrollment. Enrollment and class must match by composite tenant FK.
3. Submission is refused if any eligible active enrollment has no mark.
4. Submission locks marks. A distinct MFA-enabled institution officer
   independently approves the register. A creator/submitting officer cannot
   approve their own work.
5. Only approved registers can become visible to enrolled students and
   individually verified guardians, and only their own records. Classmates'
   rows are never supplied through the family-facing access path.

No notes, medical details or absence reasons are collected in this baseline.
Statuses are neither included in the general security-event metadata nor sent
through email notifications.

### Outstanding

Approved attendance is intentionally immutable via the application API.
Controlled teacher-requested changes with separate officer approval and private
before/after snapshots are implemented. There is no full correction appeal,
withdrawal, historical backdating policy,
teacher replacement, absence excuse evidence, attendance appeal, signed
attendance report or verified attendance export yet. Test these processes
independently before production.

## Assessment scorebook

1. Assigned MFA-enabled teacher creates a draft class assessment with a
   unique code/title and a positive maximum up to 10,000 points.
2. Teacher enters scores for active class enrollments using fixed two-decimal
   precision, allowing zero but rejecting negative and over-maximum values.
3. The draft cannot be submitted until every active enrollment has an
   explicit score; a missing score is never silently treated as zero.
4. Submission locks results. A distinct MFA-enabled school officer reviews
   and publishes the assessment; all actions produce secret-free audit events.
5. Students and verified guardians may view only scores from published
   assessments for their own linked, active enrollments. Unrelated staff,
   families and institutions are denied.

These records are individual assessment results, **not final academic
transcripts, graduation grades or verified report cards**.

### Outstanding

A teacher-requested score correction with two-person approval and retained
before/after snapshots is implemented. Full appeal, student notification,
marking schemes, weighted categories, rubric
support, ungraded/absent students, reassessment, moderation, student appeals,
full transcripts, parental acknowledgment, import/export, retention rules,
and real institutional academic-policy review remain required.

## Privacy and authorization assumptions

- Institution and campus membership are always checked server-side.
- Subject-specific records retain composite institution/campus/class linkage.
- Access to a class alone does not grant access to all enrolled student marks.
- Teacher reads require a real current teacher assignment and MFA.
- Parent reads require explicit active guardian links for the relevant child.
- Class and student access are invalidated if relevant memberships are suspended.
- No public REST API, third-party notification or payment integration is enabled.

CI verifies common unauthorized paths and schema constraints with synthetic
accounts only. Dedicated threat modeling, privacy/custody review, physical
device tests and an isolated aaPanel/MySQL 8 restore rehearsal are all
production blockers.
