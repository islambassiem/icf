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
        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->foreignId('exam_type_id')->constrained('exam_types');
            $table->string('status')->default('In progress');
            $table->dateTime('started_at');
            $table->dateTime('part_1_ends_at')->nullable();
            $table->dateTime('break_started_at')->nullable();
            $table->dateTime('part_2_started_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
