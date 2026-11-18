<?php

use Livewire\Livewire;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->type = Portal::examType('ACC');
    $this->user = Portal::subscriber();
});

it('sends guests to the login page', function (): void {
    $this->get(route('attempts.index'))->assertRedirect(route('login'));
});

it('shows an empty state before the first exam', function (): void {
    $this->actingAs($this->user)->get(route('attempts.index'))->assertOk()->assertSee('You have not taken an exam yet.');
});

it("lists the user's attempts with the right action for each", function (): void {
    $done = Portal::rawAttempt($this->user, $this->type, ['completed_at' => now(), 'score' => 82, 'started_at' => now()->subHours(3)]);
    $running = Portal::rawAttempt($this->user, $this->type);
    $expired = Portal::rawAttempt($this->user, $this->type, ['expires_at' => now()->subMinute(), 'started_at' => now()->subDays(1)]);

    $this->actingAs($this->user)->get(route('attempts.index'))
        ->assertOk()
        ->assertSee('Completed')->assertSee('82%')->assertSee(route('attempts.show', $done))
        ->assertSee('In progress')->assertSee(route('exams.take', $running))
        ->assertSee('Expired')->assertSee(route('attempts.show', $expired));   // opening it closes and scores it
});

it("never shows another user's attempts", function (): void {
    $pcc = Portal::examType('PCC');
    $other = Portal::subscriber();
    Portal::rawAttempt($other, $pcc, ['completed_at' => now(), 'score' => 99]);

    $this->actingAs($this->user)->get(route('attempts.index'))->assertDontSee('PCC')->assertDontSee('99%');
});

it('shows the totals of finished exams only', function (): void {
    Portal::rawAttempt($this->user, $this->type, ['completed_at' => now(), 'score' => 80]);
    Portal::rawAttempt($this->user, $this->type, ['completed_at' => now(), 'score' => 60]);
    Portal::rawAttempt($this->user, $this->type);   // still running: not counted

    $stats = Livewire::actingAs($this->user)->test('pages::attempts.index')->instance()->stats;

    expect($stats)->toBe(['count' => 2, 'best' => 80.0, 'average' => 70.0]);
});

it('shows ten attempts per page', function (): void {
    foreach (range(1, 12) as $i) {
        Portal::rawAttempt($this->user, $this->type, ['started_at' => now()->subMinutes($i)]);
    }

    $attempts = Livewire::actingAs($this->user)->test('pages::attempts.index')->instance()->attempts;

    expect($attempts->total())->toBe(12)->and($attempts->count())->toBe(10);
});

it('is shown in Arabic', function (): void {
    $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('attempts.index'))->assertOk()->assertSee('محاولاتي');
});
