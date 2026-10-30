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
        Schema::create('exam_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_question_id')->unique()->constrained('exam_attempt_questions');
            $table->foreignId('best_answer_option_id')->constrained('answer_options');
            $table->foreignId('worst_answer_option_id')->nullable()->constrained('answer_options');
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_responses');
    }
};
