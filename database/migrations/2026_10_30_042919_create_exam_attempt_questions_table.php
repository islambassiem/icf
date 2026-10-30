<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts');
            $table->foreignId('question_id')->constrained('questions');
            $table->unsignedTinyInteger('part_number');
            $table->unsignedInteger('position');
            $table->json('question_snapshot');
            $table->timestamps();

            $table->unique(
                ['attempt_id', 'question_id'],
                'attempt_question_unique'
            );

            $table->unique(
                ['attempt_id', 'part_number', 'position'],
                'attempt_part_position_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
    }
};
