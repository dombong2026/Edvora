<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_proposals', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id', 'campus_id'], 'fee_proposal_tenant_key');
        });
        Schema::create('student_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->unsignedBigInteger('fee_proposal_id');
            $table->foreign(['campus_id', 'institution_id'], 'invoice_campus_scope_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreign(['student_profile_id', 'institution_id', 'campus_id'], 'invoice_student_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign(['fee_proposal_id', 'institution_id', 'campus_id'], 'invoice_proposal_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('fee_proposals')->restrictOnDelete();
            $table->string('invoice_number', 40);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'invoice_number'], 'school_invoice_number_unique');
            $table->unique(['id', 'institution_id', 'campus_id'], 'invoice_tenant_key');
            $table->index(['institution_id', 'campus_id', 'status'], 'invoice_status_scope_idx');
        });
        Schema::create('manual_reconciliation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('student_invoice_id');
            $table->foreign(['student_invoice_id', 'institution_id', 'campus_id'], 'match_invoice_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('student_invoices')->restrictOnDelete();
            $table->string('statement_reference', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('pending');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'statement_reference'], 'school_bank_reference_unique');
            $table->index(['institution_id', 'campus_id', 'status'], 'manual_matches_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_reconciliation_entries');
        Schema::dropIfExists('student_invoices');
        Schema::table('fee_proposals', function (Blueprint $table): void {
            $table->dropUnique('fee_proposal_tenant_key');
        });
    }
};
