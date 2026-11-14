<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_result_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->foreign(['student_profile_id', 'institution_id', 'campus_id'], 'statement_student_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign(['academic_year_id', 'institution_id'], 'statement_year_scope_fk')
                ->references(['id', 'institution_id'])->on('academic_years')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('statement_number', 48)->nullable();
            $table->longText('snapshot_json')->nullable();
            $table->char('snapshot_sha256', 64)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'statement_number'], 'school_statement_unique');
            $table->index(['institution_id', 'student_profile_id', 'academic_year_id', 'status'], 'statement_student_year_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_result_statements');
    }
};
