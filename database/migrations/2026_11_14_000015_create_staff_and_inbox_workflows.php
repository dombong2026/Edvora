<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->foreign(['campus_id', 'institution_id'], 'staff_leave_campus_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->restrictOnDelete();
            $table->foreign(['staff_user_id', 'institution_id'], 'staff_leave_member_fk')
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->string('category', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'campus_id', 'status'], 'staff_leave_review_idx');
            $table->index(['staff_user_id', 'starts_on'], 'staff_leave_employee_idx');
        });

        Schema::create('payroll_plan_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->foreign(['campus_id', 'institution_id'], 'payroll_campus_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->restrictOnDelete();
            $table->foreign(['staff_user_id', 'institution_id'], 'payroll_member_fk')
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->string('period', 7);
            $table->string('currency', 3);
            $table->unsignedBigInteger('gross_minor');
            $table->unsignedBigInteger('deductions_minor');
            $table->unsignedBigInteger('net_minor');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'staff_user_id', 'period'], 'payroll_one_staff_per_period');
            $table->index(['institution_id', 'campus_id', 'status'], 'payroll_review_idx');
        });

        Schema::create('announcement_read_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('announcement_id')->constrained('institution_announcements')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreign(['user_id', 'institution_id'], 'notice_read_member_fk')
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id'], 'notice_one_read_per_user');
            $table->index(['institution_id', 'user_id'], 'notice_read_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_read_receipts');
        Schema::dropIfExists('payroll_plan_entries');
        Schema::dropIfExists('staff_leave_requests');
    }
};
