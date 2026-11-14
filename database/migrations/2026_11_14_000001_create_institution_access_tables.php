<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->string('timezone', 64)->default('Asia/Phnom_Penh');
            $table->string('locale', 12)->default('en');
            $table->timestamps();
        });

        Schema::create('campuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'slug']);
            $table->unique(['id', 'institution_id']);
        });

        Schema::create('institution_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('invited');
            $table->timestamps();
            $table->unique(['user_id', 'institution_id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('institution_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id');
            $table->foreignId('user_id');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Prevent assigning a campus from a different institution.
            $table->foreign(['campus_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            // Role assignment is only possible for a member of the same institution.
            $table->foreign(['user_id', 'institution_id'])
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->unique(['institution_id', 'user_id', 'role_id', 'campus_id'], 'role_assignment_unique');
            $table->index(['institution_id', 'campus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_role_assignments');
        Schema::dropIfExists('platform_administrators');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('institution_memberships');
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('institutions');
    }
};
