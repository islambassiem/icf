<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a part is made of, in order: e.g. 18 knowledge questions, then 22 situational questions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_part_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_part_id')->constrained('exam_parts')->cascadeOnDelete();
            $table->foreignId('question_type_id')->constrained('question_types');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedTinyInteger('position');                          // order inside the part
            $table->timestamps();

            $table->unique(['exam_part_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_part_sections');
    }
};
