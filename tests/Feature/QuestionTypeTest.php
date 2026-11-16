<?php

use App\Models\QuestionType;
use Tests\Support\Portal;

it('knows how many points a question of each type can earn', function (): void {
    expect(Portal::questionType('knowledge')->maxPoints())->toBe(1)     // one correct answer
        ->and(Portal::questionType('situational')->maxPoints())->toBe(2); // best + worst
});

it('tells single-answer types from best/worst types', function (): void {
    expect(Portal::questionType('knowledge')->isSingle())->toBeTrue()
        ->and(Portal::questionType('situational')->isSingle())->toBeFalse();
});

it('follows the points stored in the database, not fixed numbers', function (): void {
    $type = QuestionType::query()->create(['code' => 'bonus', 'name_en' => 'Bonus', 'name_ar' => 'إضافي', 'mode' => QuestionType::MODE_BEST_WORST, 'best_points' => 3, 'worst_points' => 2]);

    expect($type->maxPoints())->toBe(5);
});

it('shows the name in the current language', function (): void {
    $type = Portal::questionType('situational');

    app()->setLocale('en');
    expect($type->name)->toBe('Situational Judgment');

    app()->setLocale('ar');
    expect($type->name)->toBe('الحكم الموقفي');
});
