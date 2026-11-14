<?php

// routes/web.php
use App\Http\Controllers\FakeGatewayController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SetLocaleController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::landing')->name('home');

Route::get('/locale/{locale}', SetLocaleController::class)->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    // ---- Payments ----
    Route::livewire('subscription', 'pages::subscription.index')->name('subscription.index');
    Route::livewire('subscription/checkout/{plan}', 'pages::subscription.checkout')->name('subscription.checkout');
    Route::livewire('subscription/return/{payment}', 'pages::subscription.return')->name('subscription.return');
    Route::livewire('payments/{payment}/receipt', 'pages::payments.receipt')->name('payments.receipt');

    // ---- Exams ----
    // The sidebar's "Take an exam" goes to the exam picker on the dashboard.
    Route::get('exams', fn () => redirect()->to(route('dashboard').'#start-exam'))->name('exams.index');
    Route::livewire('exams/{attempt}', 'pages::exams.take')->name('exams.take');

    // ---- Attempts and reports ----
    Route::livewire('attempts', 'pages::attempts.index')->name('attempts.index');
    Route::livewire('attempts/{attempt}', 'pages::attempts.show')->name('attempts.show');
});

// The provider calls this directly: no login, no CSRF (see bootstrap-app.php.snippet).
Route::post('webhooks/payments', PaymentWebhookController::class)->name('webhooks.payments');

// The built-in test gateway's payment page. Never registered in production.
if (config('payments.driver') === 'fake' && ! app()->isProduction()) {
    Route::middleware(['auth'])->group(function (): void {
        Route::get('fake-gateway/{payment}', [FakeGatewayController::class, 'show'])->name('fake-gateway.show');
        Route::post('fake-gateway/{payment}', [FakeGatewayController::class, 'complete'])->name('fake-gateway.complete');
    });
}

require __DIR__.'/settings.php';
