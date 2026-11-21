<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the interface between English and Urdu. Saved on the user, so it follows them to any device.
 */
class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        return back();
    }
}
