<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('academic_subject_id');
            $table->string('code', 40);
            $table->string('name', 150);
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('teacher_user_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'campus_id', 'academic_year_id', 'code'], 'section_code_in_year');
            $table->unique(['id', 'institution_id', 'campus_id'], 'section_tenant_campus_key');
            $table->foreign(['campus_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreign(['academic_year_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('academic_years')->restrictOnDelete();
            $table->foreign(['academic_subject_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('academic_subjects')->restrictOnDelete();
            $table->foreign(['teacher_user_id', 'institution_id'])
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
        });

        Schema::create('class_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->foreignId('enrolled_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamps();

            $table->unique(['class_section_id', 'student_profile_id'], 'section_student_unique');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'enrollment_section_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
            $table->foreign(['student_profile_id', 'institution_id', 'campus_id'], 'enrollment_student_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('student_profiles')->restrictOnDelete();
            $table->index(['institution_id', 'student_profile_id', 'status'], 'enrollment_student_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('class_sections');
    }
};
