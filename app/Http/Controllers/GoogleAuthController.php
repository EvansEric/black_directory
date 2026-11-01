<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SocialiteRedirect;

class GoogleAuthController extends Controller
{
    public function redirect(): SocialiteRedirect
    {
        if (! $this->isConfigured()) {
            return $this->notConfigured();
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(GoogleAuthenticator $authenticator): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->notConfigured();
        }

        try {
            $user = $authenticator->resolveUser(Socialite::driver('google')->user());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('home')->with('status', 'Google sign-in failed. Please try again.');
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function notConfigured(): RedirectResponse
    {
        return redirect()->route('home')->with('status', 'Google sign-in is not set up yet.');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
