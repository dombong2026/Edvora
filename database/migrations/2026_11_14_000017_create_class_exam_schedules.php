<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('class_section_id');
            $table->foreign(['class_section_id', 'institution_id', 'campus_id'], 'exam_schedule_class_scope_fk')
                ->references(['id', 'institution_id', 'campus_id'])->on('class_sections')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('title', 150);
            $table->date('exam_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 80);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['class_section_id', 'code'], 'exam_class_code_unique');
            $table->index(['institution_id', 'campus_id', 'exam_date', 'status'], 'exam_room_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam_schedules');
    }
};
