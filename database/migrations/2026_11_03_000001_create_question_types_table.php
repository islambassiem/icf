<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kinds of question an exam can contain.
 *   single     = the candidate picks ONE option; the option flagged is_best is the correct answer (knowledge).
 *   best_worst = the candidate picks a BEST and a WORST option (situational judgment).
 * Points are set here, so scoring can change without touching code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();          // knowledge, situational
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('mode');                    // single | best_worst
            $table->unsignedTinyInteger('best_points')->default(1);   // points for the correct / best answer
            $table->unsignedTinyInteger('worst_points')->default(0);  // points for the worst answer (best_worst only)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_types');
    }
};
