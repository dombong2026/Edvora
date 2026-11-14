# Controlled timetable revisions

Status: synthetic CI-tested foundation; no live class scheduling.

## Safe change workflow

- Only MFA-enabled institution leadership may request a revision for an
  existing active class timetable slot.
- The current slot remains active while review is pending; each request
  retains original and proposed weekday, start/end time and room.
- Reason codes are constrained to: room_change, staff_scheduling,
  conflict_resolution, operational_adjustment.
- A distinct MFA-enabled institution officer must approve or reject.
  The requesting officer cannot approve their own change.
- A transaction locks the institution and the original slot, then validates
  the original snapshot and all applicable current schedule conflicts.
  Approval updates one slot and keeps the review record; rejection leaves
  the slot untouched.
- Concurrent timetable slot creation, timetable revisions, and teacher
  reassignment are serialized on the institution row. A proposed teacher
  assignment is denied if the teacher already has overlapping sessions
  in another class of that institution.
- Composite foreign keys ensure change history can only reference slots
  in the matching institution, campus, and class.

## Boundaries

No public direct slot update/delete route exists. Pending requests, officer
names and old/new snapshots are visible only inside authorized leadership
workspaces. Classmates, parents and students see only the current slot,
not its private change history. Security events contain opaque change IDs,
not room names or clock times.

Not yet implemented: room capacity, co-teaching, academic holidays, weekly
exceptions, attendance impacts on reschedules, explicit stakeholder
notifications, term-specific schedules, room catalog, timetable bulk edits,
complete change-report export and third-party calendar sync.

Approval is a configuration change, not a verified notice to affected
students or teachers. Independent usability, scheduling-policy, and school
communication acceptance are required before live deployment.
