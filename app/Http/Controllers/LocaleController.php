<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    /**
     * Set the application locale.
     *
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setLocale($locale)
    {
        if (in_array($locale, ['en', 'vi'])) {
            Session::put('locale', $locale);
            App::setLocale($locale);
        }

        return redirect()->back();
    }
}
