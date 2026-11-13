<?php

declare(strict_types=1);

use App\Http\Controllers\SetLocaleController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::landing')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/locale/{locale}', SetLocaleController::class)->name('locale.switch');
