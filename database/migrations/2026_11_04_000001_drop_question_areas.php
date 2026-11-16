<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * question_areas (Situational / Behavioural) duplicated what question_types now describes
 * (Knowledge / Situational Judgment). Questions are classified by question_types only.
 *
 * If you have not deployed yet you can instead delete the question_areas migration and remove the
 * question_area_id line from your questions migration, then run migrate:fresh. Do not do both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_area_id');
        });

        Schema::dropIfExists('question_areas');
    }

    public function down(): void
    {
        Schema::create('question_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('question_area_id')->nullable()->constrained('question_areas');
        });
    }
};
