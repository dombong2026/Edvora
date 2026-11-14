<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_help_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->foreign(['campus_id', 'institution_id'])
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 30);
            $table->string('title', 160);
            $table->string('status', 24)->default('open');
            $table->timestamp('last_activity_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'institution_id', 'campus_id'], 'help_tickets_scope_unique');
            $table->index(['institution_id', 'campus_id', 'status']);
            $table->index(['created_by', 'last_activity_at']);
        });

        Schema::create('institution_help_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('campus_id');
            $table->foreign(['ticket_id', 'institution_id', 'campus_id'], 'help_replies_ticket_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('institution_help_tickets')
                ->restrictOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['ticket_id', 'id']);
        });

        Schema::create('institution_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->foreign(['campus_id', 'institution_id'], 'announcements_campus_scope_fk')
                ->references(['id', 'institution_id'])->on('campuses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 24)->default('draft');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'status', 'published_at'], 'announcements_published_idx');
            $table->index(['institution_id', 'campus_id', 'status'], 'announcements_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_announcements');
        Schema::dropIfExists('institution_help_replies');
        Schema::dropIfExists('institution_help_tickets');
    }
};
