<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class SetLocaleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(\in_array($locale, ['en', 'ar'], true), 400);

        session()->put('locale', $locale);

        return back();
    }
}
