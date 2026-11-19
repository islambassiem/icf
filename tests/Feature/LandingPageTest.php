<?php

use Livewire\Livewire;

describe('the page', function (): void {
    it('is open to everyone', function (): void {
        $this->get('/')->assertOk();
    });

    it('explains why to practise and why to choose the portal', function (): void {
        $this->get('/')
            ->assertSee('Pass the ICF exam')
            ->assertSee('What a mock exam does that studying alone can')
            ->assertSee('Unlimited attempts')
            ->assertSee(url('/register'))
            ->assertSee(url('/login'));
    });

    it('describes the exam as knowledge and situational judgment questions', function (): void {
        $this->get('/')->assertSee('knowledge and situational judgment')->assertDontSee('behavioural');
    });

    it('is shown in Arabic', function (): void {
        $this->withSession(['locale' => 'ar'])->get('/')->assertOk()->assertSee('اجتز اختبار ICF')->assertDontSee('Pass the ICF exam');
    });
});

describe('the sample question', function (): void {
    it('starts with the ACC question', function (): void {
        Livewire::test('pages::landing')->assertSet('level', 'ACC')->assertSee('promotion interview');
    });

    it('switches to the PCC question', function (): void {
        Livewire::test('pages::landing')->call('setLevel', 'PCC')->assertSet('level', 'PCC')->assertSee('goes silent');
    });

    it('ignores a level that does not exist', function (): void {
        Livewire::test('pages::landing')->call('setLevel', 'PCC')->call('setLevel', 'XYZ')->assertSet('level', 'PCC');
    });

    it('shows the sample question in Arabic', function (): void {
        $this->withSession(['locale' => 'ar'])->get('/')->assertSee('مقابلة الترقية');
    });
});
