<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('student_number', 64);
            $table->string('display_name', 180);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'student_number']);
            $table->unique(['institution_id', 'user_id']);
            $table->unique(['id', 'institution_id']);
            $table->unique(['id', 'institution_id', 'campus_id'], 'student_tenant_campus_identity');
            $table->foreign(['user_id', 'institution_id'])
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->foreign(['campus_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->index(['institution_id', 'campus_id', 'status']);
        });

        Schema::create('student_guardian_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_profile_id');
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->restrictOnDelete();
            $table->string('relationship', 24);
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_profile_id', 'guardian_user_id'], 'unique_student_guardian');
            $table->foreign(['guardian_user_id', 'institution_id'])
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->foreign(['student_profile_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('student_profiles')->restrictOnDelete();
            $table->index(['institution_id', 'guardian_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardian_links');
        Schema::dropIfExists('student_profiles');
    }
};
