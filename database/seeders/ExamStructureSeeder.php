<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExamPart;
use App\Models\ExamPartSection;
use App\Models\ExamType;
use App\Models\QuestionType;
use Illuminate\Database\Seeder;

/**
 * The default exam: two parts, each 18 knowledge questions followed by 22 situational judgment
 * questions, 70 minutes per part and a 10 minute break.
 *
 *   php artisan db:seed --class=ExamStructureSeeder
 *
 * It only INSERTS what is missing, so it is safe to run again and never overwrites changes you
 * made in the database. To change the exam later, edit the rows in question_types, exam_parts
 * and exam_part_sections.
 */
class ExamStructureSeeder extends Seeder
{
    public function run(): void
    {
        $knowledge = QuestionType::query()->firstOrCreate(['code' => 'knowledge'], [
            'name_en' => 'Knowledge', 'name_ar' => 'معرفي',
            'mode' => QuestionType::MODE_SINGLE, 'best_points' => 1, 'worst_points' => 0,
        ]);

        $situational = QuestionType::query()->firstOrCreate(['code' => 'situational'], [
            'name_en' => 'Situational Judgment', 'name_ar' => 'الحكم الموقفي',
            'mode' => QuestionType::MODE_BEST_WORST, 'best_points' => 1, 'worst_points' => 1,
        ]);

        foreach (ExamType::all() as $type) {
            foreach ([1, 2] as $number) {
                $part = ExamPart::query()->firstOrCreate(['exam_type_id' => $type->id, 'part_number' => $number], ['minutes' => 70, 'break_minutes' => $number === 1 ? 10 : 0]);

                ExamPartSection::query()->firstOrCreate(['exam_part_id' => $part->id, 'position' => 1], ['question_type_id' => $knowledge->id, 'question_count' => 18]);
                ExamPartSection::query()->firstOrCreate(['exam_part_id' => $part->id, 'position' => 2], ['question_type_id' => $situational->id, 'question_count' => 22]);
            }
        }
    }
}
