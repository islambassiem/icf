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
        Schema::create('answer_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('questions');
            $table->string('statement_en');
            $table->string('statement_ar');
            $table->string('comment_en');
            $table->string('comment_ar');
            $table->boolean('is_best')->default(false);
            $table->boolean('is_worst')->nullable()->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answer_options');
    }
};
