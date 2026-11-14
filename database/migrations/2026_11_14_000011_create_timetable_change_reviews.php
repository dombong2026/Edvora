<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_timetable_slots', function (Blueprint $table): void {
            $table->unique(
                ['id', 'class_section_id', 'institution_id', 'campus_id'],
                'timetable_slot_tenant_identity'
            );
        });

        Schema::create('class_timetable_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('timetable_slot_id');
            $table->unsignedTinyInteger('old_weekday');
            $table->time('old_starts_at');
            $table->time('old_ends_at');
            $table->string('old_room_name', 100)->nullable();
            $table->unsignedTinyInteger('new_weekday');
            $table->time('new_starts_at');
            $table->time('new_ends_at');
            $table->string('new_room_name', 100)->nullable();
            $table->string('reason_code', 32);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['class_section_id', 'status'], 'timetable_change_pending_index');
            $table->foreign(
                ['timetable_slot_id', 'class_section_id', 'institution_id', 'campus_id'],
                'timetable_change_slot_tenant_fk'
            )->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_timetable_slots')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable_changes');
        Schema::table('class_timetable_slots', function (Blueprint $table): void {
            $table->dropUnique('timetable_slot_tenant_identity');
        });
    }
};
