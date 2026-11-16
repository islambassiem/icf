<?php

declare(strict_types=1);

namespace App\Services\Exams;

use App\Exceptions\ExamNotConfiguredException;
use App\Models\ExamPart;
use App\Models\ExamType;

/**
 * The shape of an exam, read from the database (exam_parts, exam_part_sections, question_types).
 * Change those rows and the exam changes. No code, no deploy.
 *
 * Shape of the result:
 *  parts[]    part, minutes, break_minutes, sections[]
 *  sections[] type_id, code, mode, name_en, name_ar, count, best_points, worst_points
 */
final class ExamBlueprint
{
    /**
     * @return array{
     *   parts: list<array{part: int, minutes: int, break_minutes: int, sections: list<array{type_id: int, code: string, mode: string, name_en: string, name_ar: string, count: int, best_points: int, worst_points: int}>}>,
     *   total_questions: int,
     *   total_minutes: int,
     *   max_points: int
     * }
     *
     * @throws ExamNotConfiguredException
     */
    public static function for(ExamType $type): array
    {
        $parts = ExamPart::query()
            ->where('exam_type_id', $type->id)
            ->orderBy('part_number')
            ->with('sections.questionType')
            ->get();

        // The attempt table models exactly two parts (part_1_ends_at, part_2_started_at, ...).
        if ($parts->pluck('part_number')->values()->all() !== [1, 2]) {
            throw new ExamNotConfiguredException("{$type->name_en} must have exactly part 1 and part 2.");
        }

        $result = ['parts' => [], 'total_questions' => 0, 'total_minutes' => 0, 'max_points' => 0];

        foreach ($parts as $part) {
            $sections = [];

            foreach ($part->sections as $section) {
                $questionType = $section->questionType;

                if ($section->question_count < 1) {
                    continue;
                }

                $sections[] = [
                    'type_id' => $questionType->id,
                    'code' => $questionType->code,
                    'mode' => $questionType->mode,
                    'name_en' => $questionType->name_en,
                    'name_ar' => $questionType->name_ar,
                    'count' => $section->question_count,
                    'best_points' => $questionType->best_points,
                    'worst_points' => $questionType->worst_points,
                ];

                $result['total_questions'] += $section->question_count;
                $result['max_points'] += $section->question_count * $questionType->maxPoints();
            }

            if ($sections === [] || $part->minutes < 1) {
                throw new ExamNotConfiguredException("{$type->name_en} part {$part->part_number} needs questions and a time limit.");
            }

            $result['parts'][] = [
                'part' => $part->part_number,
                'minutes' => $part->minutes,
                'break_minutes' => $part->break_minutes,
                'sections' => $sections,
            ];

            $result['total_minutes'] += $part->minutes + $part->break_minutes;
        }

        // No break after the last part.
        $last = array_key_last($result['parts']);

        if ($last !== null) {
            $result['total_minutes'] -= $result['parts'][$last]['break_minutes'];
        }

        return $result;
    }

    /**
     * What an attempt remembers about the rules it started with.
     *
     * @param array{
     *     parts: list<array{
     *         part: int,
     *         minutes: int,
     *         break_minutes: int,
     *         sections: list<array{
     *             code: string,
     *             count: int
     *         }>
     *     }>,
     *     total_questions: int,
     *     total_minutes: int,
     *     max_points: int
     * } $blueprint
     * @return array<string, mixed>
     */
    public static function snapshot(array $blueprint): array
    {
        return [
            'parts' => collect($blueprint['parts'])->map(fn (array $part): array => [
                'part' => $part['part'],
                'minutes' => $part['minutes'],
                'break_minutes' => $part['break_minutes'],
                'sections' => collect($part['sections'])->map(fn (array $s): array => [
                    'type' => $s['code'],
                    'count' => $s['count'],
                ])->all(),
            ])->all(),
            'total_questions' => $blueprint['total_questions'],
            'max_points' => $blueprint['max_points'],
        ];
    }
}
