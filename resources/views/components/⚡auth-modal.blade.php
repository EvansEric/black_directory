<?php

use App\Models\User;
use App\Services\PendingPlanSelection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    // Login fields
    public string $loginEmail = '';
    public string $loginPassword = '';

    // Register fields
    public string $registerName = '';
    public string $registerEmail = '';
    public string $registerPassword = '';
    public string $registerPassword_confirmation = '';

    // Forgot password fields
    public string $forgotEmail = '';
    public string $forgotMessage = '';

    public function requestPasswordReset(): void
    {
        $this->forgotMessage = '';

        $credentials = $this->validate([
            'forgotEmail' => ['required', 'email'],
        ], [], [
            'forgotEmail' => 'email',
        ]);

        $status = Password::sendResetLink([
            'email' => $credentials['forgotEmail'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('forgotEmail', __($status));

            return;
        }

        $this->reset(['forgotEmail']);
        $this->forgotMessage = 'If an account exists for that email, we have sent a password reset link.';
        $this->dispatch('password-reset-sent');
    }

    public function login(): void
    {
        $credentials = $this->validate([
            'loginEmail' => ['required', 'email'],
            'loginPassword' => ['required', 'string'],
        ], [], [
            'loginEmail' => 'email',
            'loginPassword' => 'password',
        ]);

        if (! Auth::attempt([
            'email' => $credentials['loginEmail'],
            'password' => $credentials['loginPassword'],
        ], true)) {
            $this->addError('loginEmail', 'These credentials do not match our records.');

            return;
        }

        Session::regenerate();

        $this->reset(['loginEmail', 'loginPassword']);

        // Force a fresh page load so every component re-evaluates auth state.
        $this->redirect($this->afterAuthenticationUrl());
    }

    public function register(): void
    {
        $data = $this->validate([
            'registerName' => ['required', 'string', 'max:255'],
            'registerEmail' => ['required', 'email', 'unique:users,email'],
            'registerPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'registerName' => 'name',
            'registerEmail' => 'email',
            'registerPassword' => 'password',
            'registerPassword_confirmation' => 'password confirmation',
        ]);

        $user = User::create([
            'name' => $data['registerName'],
            'email' => $data['registerEmail'],
            'password' => Hash::make($data['registerPassword']),
        ]);

        Auth::login($user, true);

        Session::regenerate();

        $this->reset(['registerName', 'registerEmail', 'registerPassword', 'registerPassword_confirmation']);

        $this->redirect($this->afterAuthenticationUrl());
    }

    public function cancel(): void
    {
        app(PendingPlanSelection::class)->forget();
    }

    private function afterAuthenticationUrl(): string
    {
        return app(PendingPlanSelection::class)->exists()
            ? route('pricing')
            : (request()->header('Referer') ?? '/');
    }
}; ?>

<div
    x-data="{ open: false, tab: 'login' }"
    x-on:open-auth-modal.window="open = true; tab = $event.detail?.tab || 'login'"
    x-show="open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
    style="display: none;"
    @keydown.escape.window="if (open) { open = false; $wire.cancel() }"
>
    <div @click.away="if (open) { open = false; $wire.cancel() }" class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative">
        <button @click="open = false; $wire.cancel()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        {{-- Tabs --}}
        <div class="flex items-center space-x-6 border-b border-slate-100 mb-6">
            <button
                @click="tab = 'login'"
                :class="tab === 'login' ? 'text-amber-600 border-amber-500' : 'text-slate-400 border-transparent'"
                class="pb-3 text-sm font-bold border-b-2 transition"
            >
                Log In
            </button>
            <button
                @click="tab = 'register'"
                :class="tab === 'register' ? 'text-amber-600 border-amber-500' : 'text-slate-400 border-transparent'"
                class="pb-3 text-sm font-bold border-b-2 transition"
            >
                Sign Up
            </button>
        </div>

        @if ($forgotMessage)
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ $forgotMessage }}
            </div>
        @endif

        <div x-show="tab === 'login' || tab === 'register'" class="mb-5">
                <a
                    href="{{ route('auth.google') }}"
                    data-google-auth
                    class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <svg class="h-4 w-4" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/>
                        <path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.4 0 20.1 0 24s.9 7.6 2.6 10.8l7.9-6.1z"/>
                        <path fill="#34A853" d="M24 48c6.500 0 11.900-2.100 15.900-5.800l-7.500-5.800c-2.100 1.400-4.800 2.300-8.400 2.300-6.300 0-11.600-4.100-13.500-9.800l-7.900 6.100C6.500 42.600 14.600 48 24 48z"/>
                    </svg>
                    <span x-text="tab === 'register' ? 'Sign up with Google' : 'Continue with Google'"></span>
                </a>
                <div class="mt-5 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span> or <span class="h-px flex-1 bg-slate-200"></span>
                </div>
            </div>

        {{-- Login Form --}}
        <form x-show="tab === 'login'" wire:submit="login" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                <input type="email" wire:model="loginEmail" placeholder="you@example.com"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('loginEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" wire:model="loginPassword" placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('loginPassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end">
                <button type="button" @click="tab = 'forgot'" class="text-sm font-semibold text-amber-600 hover:text-amber-500">
                    Forgot password?
                </button>
            </div>

            <button type="submit"
                class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                wire:loading.attr="disabled" wire:target="login"
            >
                <span wire:loading.remove wire:target="login">Log In</span>
                <span wire:loading wire:target="login">Logging in...</span>
            </button>
        </form>

        {{-- Forgot Password Form --}}
        <form x-show="tab === 'forgot'" wire:submit="requestPasswordReset" class="space-y-4" x-on:password-reset-sent.window="tab = 'login'">
            <div class="text-sm text-slate-600">
                Enter your email and we’ll send you a secure link to reset your password.
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                <input type="email" wire:model="forgotEmail" placeholder="you@example.com"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('forgotEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                wire:loading.attr="disabled" wire:target="requestPasswordReset"
            >
                <span wire:loading.remove wire:target="requestPasswordReset">Send Reset Link</span>
                <span wire:loading wire:target="requestPasswordReset">Sending...</span>
            </button>

            <button type="button" @click="tab = 'login'" class="w-full text-sm font-semibold text-slate-500 hover:text-slate-700">
                Back to log in
            </button>
        </form>

        {{-- Register Form --}}
        <form x-show="tab === 'register'" wire:submit="register" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
                <input type="text" wire:model="registerName" placeholder="Jordan Ellis"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('registerName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                <input type="email" wire:model="registerEmail" placeholder="you@example.com"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('registerEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" wire:model="registerPassword" placeholder="At least 8 characters"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('registerPassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Confirm Password</label>
                <input type="password" wire:model="registerPassword_confirmation" placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <button type="submit"
                class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                wire:loading.attr="disabled" wire:target="register"
            >
                <span wire:loading.remove wire:target="register">Create Account</span>
                <span wire:loading wire:target="register">Creating account...</span>
            </button>
        </form>
    </div>
</div>
