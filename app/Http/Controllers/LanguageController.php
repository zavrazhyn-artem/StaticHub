<?php

namespace App\Http\Controllers;

use App\Services\Auth\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function switch(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');

        if (!in_array($locale, ['en', 'uk'])) {
            $locale = 'en';
        }

        session(['locale' => $locale]);

        if (Auth::check()) {
            $this->userService->updateLocale(Auth::user(), $locale);
        }

        return redirect()->back();
    }
}
