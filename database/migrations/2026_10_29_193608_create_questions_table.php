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
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->string('statement_en');
            $table->string('statement_ar');
            $table->foreignId('exam_type_id')->constrained('exam_types');
            $table->foreignId('question_area_id')->constrained('question_areas');
            $table->foreignId('profile_type_id')->constrained('profile_types');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
