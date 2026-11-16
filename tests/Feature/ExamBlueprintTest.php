<?php

use App\Exceptions\ExamNotConfiguredException;
use App\Models\ExamPart;
use App\Models\ExamPartSection;
use App\Models\ExamType;
use App\Services\Exams\ExamBlueprint;
use Tests\Support\Portal;

it('reads the default ICF structure from the database', function (): void {
    $blueprint = ExamBlueprint::for(Portal::examType());

    expect($blueprint['parts'])->toHaveCount(2)
        ->and($blueprint['total_questions'])->toBe(80)          // 2 x (18 + 22)
        ->and($blueprint['total_minutes'])->toBe(150)           // 70 + 10 break + 70
        ->and($blueprint['max_points'])->toBe(124)              // 36 knowledge + 44 situational x 2
        ->and($blueprint['parts'][0]['minutes'])->toBe(70)
        ->and($blueprint['parts'][0]['break_minutes'])->toBe(10)
        ->and($blueprint['parts'][1]['break_minutes'])->toBe(0);
});

it('lists the sections of each part in order', function (): void {
    $sections = ExamBlueprint::for(Portal::examType())['parts'][0]['sections'];

    expect(collect($sections)->pluck('code')->all())->toBe(['knowledge', 'situational'])
        ->and(collect($sections)->pluck('count')->all())->toBe([18, 22])
        ->and(collect($sections)->pluck('mode')->all())->toBe(['single', 'best_worst']);
});

it('follows changes made in the database', function (): void {
    $type = Portal::examType('PCC', ['minutes' => 45, 'break' => 5, 'knowledge' => 10, 'situational' => 5]);

    $blueprint = ExamBlueprint::for($type);

    expect($blueprint['total_questions'])->toBe(30)
        ->and($blueprint['total_minutes'])->toBe(95)
        ->and($blueprint['max_points'])->toBe(2 * (10 + 5 * 2));
});

it('keeps each exam type independent', function (): void {
    $acc = Portal::examType('ACC');
    $pcc = Portal::examType('PCC', ['knowledge' => 5]);

    expect(ExamBlueprint::for($acc)['total_questions'])->toBe(80)
        ->and(ExamBlueprint::for($pcc)['total_questions'])->toBe(2 * (5 + 22));
});

it('ignores a section whose question count is zero', function (): void {
    $type = Portal::examType();
    ExamPartSection::query()->whereHas('part', fn ($q) => $q->where('exam_type_id', $type->id))
        ->where('position', 1)->update(['question_count' => 0]);

    expect(ExamBlueprint::for($type)['total_questions'])->toBe(44);
});

it('fails for an exam type with no structure', function (): void {
    $type = ExamType::query()->create(['name_en' => 'EMPTY', 'name_ar' => 'EMPTY']);

    ExamBlueprint::for($type);
})->throws(ExamNotConfiguredException::class);

it('fails when a part is missing', function (): void {
    $type = Portal::examType();
    ExamPart::query()->where('exam_type_id', $type->id)->where('part_number', 2)->first()->sections()->delete();
    ExamPart::query()->where('exam_type_id', $type->id)->where('part_number', 2)->delete();

    ExamBlueprint::for($type);
})->throws(ExamNotConfiguredException::class);

it('fails when a part has no questions', function (): void {
    $type = Portal::examType();
    ExamPart::query()->where('exam_type_id', $type->id)->where('part_number', 1)->first()->sections()->delete();

    ExamBlueprint::for($type);
})->throws(ExamNotConfiguredException::class);

it('fails when a part has no time limit', function (): void {
    $type = Portal::examType();
    ExamPart::query()->where('exam_type_id', $type->id)->where('part_number', 1)->update(['minutes' => 0]);

    ExamBlueprint::for($type);
})->throws(ExamNotConfiguredException::class);

it('builds a compact snapshot for the attempt', function (): void {
    $snapshot = ExamBlueprint::snapshot(ExamBlueprint::for(Portal::examType()));

    expect($snapshot['total_questions'])->toBe(80)
        ->and($snapshot['max_points'])->toBe(124)
        ->and($snapshot['parts'][0]['sections'][0])->toBe(['type' => 'knowledge', 'count' => 18]);
});
