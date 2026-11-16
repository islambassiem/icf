<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How long each part of an exam lasts. Currently an exam has exactly two parts (1 and 2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_type_id')->constrained('exam_types');
            $table->unsignedTinyInteger('part_number');
            $table->unsignedSmallInteger('minutes');                          // time allowed for this part
            $table->unsignedSmallInteger('break_minutes')->default(0);        // break AFTER this part
            $table->timestamps();

            $table->unique(['exam_type_id', 'part_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_parts');
    }
};
