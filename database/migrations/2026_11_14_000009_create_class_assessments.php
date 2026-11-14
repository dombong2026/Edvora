<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->string('code', 40);
            $table->string('title', 150);
            $table->decimal('max_points', 8, 2);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['class_section_id', 'code'], 'assessment_code_per_section');
            $table->unique(['id', 'class_section_id', 'institution_id', 'campus_id'], 'assessment_tenant_identity');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'assessment_section_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
        });

        Schema::create('class_assessment_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_enrollment_id');
            $table->decimal('points_earned', 8, 2);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['assessment_id', 'class_enrollment_id'], 'assessment_enrollment_unique');
            $table->foreign(['assessment_id', 'class_section_id', 'institution_id', 'campus_id'], 'grade_assessment_tenant_fk')
                ->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_assessments')->restrictOnDelete();
            $table->foreign(['class_enrollment_id', 'class_section_id', 'institution_id', 'campus_id'], 'grade_enrollment_tenant_fk')
                ->references(['id', 'class_section_id', 'institution_id', 'campus_id'])
                ->on('class_enrollments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assessment_results');
        Schema::dropIfExists('class_assessments');
    }
};
