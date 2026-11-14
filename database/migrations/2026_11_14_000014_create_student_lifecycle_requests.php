<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lifecycle_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->string('kind', 20);
            $table->unsignedBigInteger('target_campus_id')->nullable();
            $table->unsignedBigInteger('target_section_id')->nullable();
            $table->string('reason', 255);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            // A transfer changes the student's campus, so the source-campus snapshot
            // cannot participate in the profile FK. Institution identity is immutable.
            $table->foreign(['student_profile_id', 'institution_id'], 'lifecycle_student_tenant_fk')
                ->references(['id', 'institution_id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign(['campus_id', 'institution_id'], 'lifecycle_campus_tenant_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreign(['target_campus_id', 'institution_id'], 'lifecycle_dest_tenant_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreign(['target_section_id', 'institution_id', 'campus_id'], 'lifecycle_section_tenant_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
            $table->index(['institution_id', 'campus_id', 'status'], 'lifecycle_review_lookup');
            $table->index(['student_profile_id', 'status'], 'lifecycle_student_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lifecycle_requests');
    }
};
