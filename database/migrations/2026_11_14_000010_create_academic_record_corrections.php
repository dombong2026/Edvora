<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_attendance_marks', function (Blueprint $table): void {
            $table->unique(['id', 'class_section_id', 'institution_id', 'campus_id'], 'attendance_mark_tenant_identity');
        });
        Schema::table('class_assessment_results', function (Blueprint $table): void {
            $table->unique(['id', 'class_section_id', 'institution_id', 'campus_id'], 'assessment_result_tenant_identity');
        });

        Schema::create('academic_record_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('attendance_mark_id')->nullable();
            $table->unsignedBigInteger('assessment_result_id')->nullable();
            $table->string('kind', 20);
            $table->string('from_value', 32);
            $table->string('to_value', 32);
            $table->string('reason_code', 32);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['class_section_id', 'kind', 'status'], 'correction_section_status_idx');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'correction_section_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
            $table->foreign(
                ['attendance_mark_id', 'class_section_id', 'institution_id', 'campus_id'],
                'correction_attendance_tenant_fk'
            )->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_attendance_marks')->restrictOnDelete();
            $table->foreign(
                ['assessment_result_id', 'class_section_id', 'institution_id', 'campus_id'],
                'correction_result_tenant_fk'
            )->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_assessment_results')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_record_corrections');
        Schema::table('class_assessment_results', function (Blueprint $table): void {
            $table->dropUnique('assessment_result_tenant_identity');
        });
        Schema::table('class_attendance_marks', function (Blueprint $table): void {
            $table->dropUnique('attendance_mark_tenant_identity');
        });
    }
};
