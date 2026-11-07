<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::landing')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/locale/{locale}', function (string $locale): RedirectResponse {
    abort_unless(\in_array($locale, ['en', 'ar'], true), 400);

    session()->put('locale', $locale);

    return back();
})->name('locale.switch');
