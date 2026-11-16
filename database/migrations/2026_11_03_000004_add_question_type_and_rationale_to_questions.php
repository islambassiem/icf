<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('question_type_id')->nullable()->after('profile_type_id')->constrained('question_types');
            // The expert's reasoning for the whole question, shown in the report.
            $table->text('rationale_en')->nullable();
            $table->text('rationale_ar')->nullable();
        });

        // Questions that already exist were written for best/worst answers, so they become situational.
        $now = now();
        $types = [
            ['code' => 'knowledge', 'name_en' => 'Knowledge', 'name_ar' => 'معرفي', 'mode' => 'single', 'best_points' => 1, 'worst_points' => 0],
            ['code' => 'situational', 'name_en' => 'Situational Judgment', 'name_ar' => 'الحكم الموقفي', 'mode' => 'best_worst', 'best_points' => 1, 'worst_points' => 1],
        ];

        foreach ($types as $type) {
            if (! DB::table('question_types')->where('code', $type['code'])->exists()) {
                DB::table('question_types')->insert($type + ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        DB::table('questions')->whereNull('question_type_id')
            ->update(['question_type_id' => DB::table('question_types')->where('code', 'situational')->value('id')]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_type_id');
            $table->dropColumn(['rationale_en', 'rationale_ar']);
        });
    }
};
