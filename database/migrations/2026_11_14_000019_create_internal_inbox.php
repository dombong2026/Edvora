<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreign(['recipient_user_id', 'institution_id'], 'inbox_member_tenant_fk')
                ->references(['user_id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->string('event_key', 40);
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('title', 160);
            $table->string('path', 255);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(
                ['institution_id', 'recipient_user_id', 'event_key', 'source_type', 'source_id'],
                'inbox_event_unique'
            );
            $table->index(['institution_id', 'recipient_user_id', 'archived_at', 'read_at'], 'inbox_account_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_inbox_messages');
    }
};
