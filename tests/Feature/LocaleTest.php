<?php

describe('switching language', function (): void {
    it('remembers the choice and returns to the page the user was on', function (): void {
        $this->from('/somewhere')->get(route('locale.switch', 'ar'))->assertRedirect('/somewhere');

        expect(session('locale'))->toBe('ar');
    });

    it('goes to the home page when there is no previous page', function (): void {
        $this->get(route('locale.switch', 'ar'))->assertRedirect(route('home'));
    });

    it('can switch back to English', function (): void {
        $this->withSession(['locale' => 'ar'])->get(route('locale.switch', 'en'));

        expect(session('locale'))->toBe('en');
    });

    it('refuses a language the portal does not have', function (string $locale): void {
        $this->get(route('locale.switch', $locale))->assertNotFound();

        expect(session('locale'))->toBe('en');
    })->with(['fr', 'xx', 'arabic', '1']);
});

describe('which language a page uses', function (): void {
    it('uses English by default', function (): void {
        $this->get('/')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
    });

    it('uses the saved choice, with right-to-left layout for Arabic', function (): void {
        $this->withSession(['locale' => 'ar'])->get('/')
            ->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
    });

    it('follows the browser language the first time', function (): void {
        $this->withHeaders(['Accept-Language' => 'ar-SA,ar;q=0.9,en;q=0.5'])->get('/')->assertSee('dir="rtl"', false);
    });

    it('falls back to English for a browser language it does not have', function (): void {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])->get('/')->assertSee('dir="ltr"', false);
    });

    it('prefers the saved choice over the browser language', function (): void {
        $this->withSession(['locale' => 'en'])->withHeaders(['Accept-Language' => 'ar'])->get('/')->assertSee('dir="ltr"', false);
    });

    it('ignores a nonsense value in the session', function (): void {
        $this->withSession(['locale' => 'klingon'])->get('/')->assertOk()->assertSee('dir="ltr"', false);
    });
});

describe('the language button', function (): void {
    it('offers Arabic on the English page and English on the Arabic page', function (): void {
        $this->get('/')->assertSee(route('locale.switch', 'ar'))->assertSee('العربية');
        $this->withSession(['locale' => 'ar'])->get('/')->assertSee(route('locale.switch', 'en'))->assertSee('English');
    });
});
