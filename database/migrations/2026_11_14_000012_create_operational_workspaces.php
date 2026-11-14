<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function campusScope(Blueprint $table): void
    {
        $table->foreignId('institution_id')->constrained()->restrictOnDelete();
        $table->unsignedBigInteger('campus_id');
        // Defend against forged cross-school campus IDs at the database layer.
        $table->foreign(['campus_id', 'institution_id'])
            ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
        $table->index(['institution_id', 'campus_id', 'id']);
    }

    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->string('code', 40);
            $table->string('title', 180);
            $table->string('author', 140)->nullable();
            $table->unsignedInteger('stock_total');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'campus_id', 'code']);
            $table->unique(['id', 'institution_id', 'campus_id']);
        });

        Schema::create('library_loans', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->unsignedBigInteger('library_item_id');
            $table->foreign(['library_item_id', 'institution_id', 'campus_id'], 'library_loans_item_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('library_items')->restrictOnDelete();
            $table->foreignId('borrower_user_id')->constrained('users')->restrictOnDelete();
            $table->date('due_on');
            $table->timestamp('loaned_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['library_item_id', 'returned_at']);
        });

        Schema::create('fee_proposals', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->string('name', 160);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'campus_id', 'status']);
        });

        Schema::create('frontdesk_visits', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->string('visitor_name', 140);
            $table->string('purpose', 180);
            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'campus_id', 'checked_out_at']);
        });

        Schema::create('admission_enquiries', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->string('contact_name', 140);
            $table->string('contact_email', 255);
            $table->string('program', 160);
            $table->string('stage', 20)->default('new');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'campus_id', 'stage']);
        });

        Schema::create('operations_tasks', function (Blueprint $table): void {
            $table->id();
            $this->campusScope($table);
            $table->string('title', 180);
            $table->string('priority', 12)->default('normal');
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'campus_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_tasks');
        Schema::dropIfExists('admission_enquiries');
        Schema::dropIfExists('frontdesk_visits');
        Schema::dropIfExists('fee_proposals');
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_items');
    }
};
