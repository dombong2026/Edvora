<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Required by the three-column composite FK on attendance marks.
        Schema::table('class_enrollments', function (Blueprint $table): void {
            $table->unique(['id', 'class_section_id', 'institution_id', 'campus_id'], 'enrollment_tenant_identity');
        });

        Schema::create('class_timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room_name', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['class_section_id', 'weekday', 'starts_at'], 'section_slot_start_unique');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'timetable_section_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
            $table->index(['institution_id', 'campus_id', 'weekday'], 'timetable_campus_day_idx');
        });

        Schema::create('class_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->date('session_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['class_section_id', 'session_date'], 'class_attendance_one_date');
            $table->unique(['id', 'class_section_id', 'institution_id', 'campus_id'], 'attendance_session_tenant_identity');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'attendance_session_section_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
        });

        Schema::create('class_attendance_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('attendance_session_id');
            $table->unsignedBigInteger('class_enrollment_id');
            $table->string('status', 16);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'class_enrollment_id'], 'attendance_unique_mark');
            $table->foreign(
                ['attendance_session_id', 'class_section_id', 'institution_id', 'campus_id'],
                'attendance_mark_session_tenant_fk'
            )->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_attendance_sessions')->restrictOnDelete();
            $table->foreign(
                ['class_enrollment_id', 'class_section_id', 'institution_id', 'campus_id'],
                'attendance_mark_enrollment_tenant_fk'
            )->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_enrollments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_attendance_marks');
        Schema::dropIfExists('class_attendance_sessions');
        Schema::dropIfExists('class_timetable_slots');
        Schema::table('class_enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollment_tenant_identity');
        });
    }
};
