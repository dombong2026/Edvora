<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExamScheduling
{
    public function canWrite(User $actor, ClassSection $section): bool
    {
        return app(ClassAttendanceService::class)->canTake($actor, $section)
            || (app(AcademicCatalogService::class)->canManage($actor, $section->institution)
                && $section->status === 'active' && $section->campus?->status === 'active');
    }

    public function canReview(User $actor, ClassSection $section): bool
    {
        return app(ClassAttendanceService::class)->canApprove($actor, $section);
    }

    public function create(User $actor, ClassSection $section, array $data): int
    {
        if (! $this->canWrite($actor, $section)) {
            throw new AuthorizationException('Only verified teachers or academic officers may schedule exams.');
        }
        $year = $section->year;
        $date = $data['exam_date'];
        if ($year?->status !== 'active'
            || $date < $year->starts_on->toDateString()
            || $date > $year->ends_on->toDateString()
            || $date < now($section->institution->timezone ?? config('app.timezone'))->toDateString()) {
            throw ValidationException::withMessages(['exam_date' => 'Exam date must be future or today and within an active academic year.']);
        }

        $start = $data['starts_at'];
        $end = $data['ends_at'];
        if ($start >= $end) {
            throw ValidationException::withMessages(['ends_at' => 'End time must be later than start time.']);
        }
        $room = strtoupper(trim($data['room']));
        $code = strtoupper(trim($data['code']));

        return DB::transaction(function () use ($actor, $section, $data, $room, $code): int {
            if (DB::table('class_exam_schedules')->where('class_section_id', $section->id)
                ->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'Exam code already exists in this class.']);
            }
            $id = DB::table('class_exam_schedules')->insertGetId([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'code' => $code, 'title' => trim($data['title']),
                'exam_date' => $data['exam_date'],
                'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'room' => $room,
                'status' => 'draft',
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('exam.schedule.drafted', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'exam_schedule_id' => $id, 'campus_id' => $section->campus_id,
            ]);
            return $id;
        });
    }

    public function submit(User $actor, ClassSection $section, int $examId): void
    {
        if (! $this->canWrite($actor, $section)) {
            throw new AuthorizationException('Only an authorized class teacher or academic officer may submit.');
        }
        DB::transaction(function () use ($actor, $section, $examId): void {
            $exam = DB::table('class_exam_schedules')->where('institution_id', $section->institution_id)
                ->where('campus_id', $section->campus_id)->where('class_section_id', $section->id)
                ->lockForUpdate()->find($examId);
            abort_unless($exam, 404);
            if ($exam->status !== 'draft') {
                throw ValidationException::withMessages(['exam' => 'Only draft exams can be submitted.']);
            }
            DB::table('class_exam_schedules')->where('id', $exam->id)->update([
                'status' => 'submitted', 'submitted_by' => $actor->id,
                'submitted_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('exam.schedule.submitted', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'exam_schedule_id' => $exam->id,
            ]);
        });
    }

    public function review(User $actor, ClassSection $section, int $examId, string $decision): void
    {
        if (! $this->canReview($actor, $section)) {
            throw new AuthorizationException('Only MFA-verified academic leadership may review.');
        }
        if (! in_array($decision, ['publish', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose publish or reject.']);
        }
        DB::transaction(function () use ($actor, $section, $examId, $decision): void {
            Institution::query()->whereKey($section->institution_id)->lockForUpdate()->firstOrFail();
            $exam = DB::table('class_exam_schedules')->where('institution_id', $section->institution_id)
                ->where('campus_id', $section->campus_id)->where('class_section_id', $section->id)
                ->lockForUpdate()->find($examId);
            abort_unless($exam, 404);
            if ($exam->status !== 'submitted') {
                throw ValidationException::withMessages(['exam' => 'Only submitted exams can be reviewed.']);
            }
            if ((int)$exam->created_by === (int)$actor->id
                || (int)$exam->submitted_by === (int)$actor->id) {
                throw new AuthorizationException('A second authorized officer must publish the exam schedule.');
            }
            if ($decision === 'publish') {
                if ($section->status !== 'active' || $section->campus?->status !== 'active'
                    || $exam->exam_date < now($section->institution->timezone ?? config('app.timezone'))->toDateString()) {
                    throw ValidationException::withMessages(['exam' => 'The class, campus and exam date must still be active.']);
                }
                $conflict = DB::table('class_exam_schedules')
                    ->where('institution_id', $section->institution_id)
                    ->where('campus_id', $section->campus_id)
                    ->where('exam_date', $exam->exam_date)
                    ->where('room', $exam->room)
                    ->where('status', 'published')
                    ->where('id', '!=', $exam->id)
                    ->where('starts_at', '<', $exam->ends_at)
                    ->where('ends_at', '>', $exam->starts_at)
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages(['room' => 'Another published exam occupies this campus room at the selected time.']);
                }
            }
            DB::table('class_exam_schedules')->where('id', $exam->id)->update([
                'status' => $decision === 'publish' ? 'published' : 'rejected',
                'published_by' => $decision === 'publish' ? $actor->id : null,
                'published_at' => $decision === 'publish' ? now() : null,
                'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record(
                $decision === 'publish' ? 'exam.schedule.published' : 'exam.schedule.rejected',
                $actor, null, $section->institution, [
                    'class_section_id' => $section->id, 'exam_schedule_id' => $exam->id, 'campus_id' => $section->campus_id,
                ]
            );
        });
    }
}
