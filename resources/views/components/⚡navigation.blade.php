<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="sticky top-0 z-40 border-b border-amber-900/10 bg-white/95 text-slate-900 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-2 h-16 sm:h-20">
            {{-- Logo --}}
            <a href="/" class="flex min-w-0 items-center gap-2 sm:gap-3">
                <div class="noir-logo-mark flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 font-serif text-lg font-black text-slate-950 shadow-lg shadow-amber-500/20 sm:h-11 sm:w-11 sm:text-2xl">
                    N
                </div>
                <div class="min-w-0">
                    <span class="noir-logo-wordmark font-serif text-base font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Noir<span class="text-amber-600">Directory</span>
                    </span>
                    <span class="hidden text-[9px] tracking-[0.18em] text-amber-700/80 font-bold uppercase sm:block">
                        Local Black-Owned Excellence
                    </span>
                </div>
            </a>

            {{-- Mobile/Nav actions --}}
            <div class="flex items-center gap-2 sm:gap-3" x-data="{ userMenuOpen: false }">
                <nav class="hidden md:flex items-center gap-6 text-sm font-medium" x-data>
                    <a href="{{ route('pricing') }}" class="text-slate-600 hover:text-slate-900 transition-colors">Pricing</a>
                    <a href="#about" class="text-slate-600 hover:text-slate-900 transition-colors">Mission</a>
                    <button
                        @click="$store.favorites.showOnly = !$store.favorites.showOnly"
                        :class="$store.favorites.showOnly ? 'text-amber-700 font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        class="transition-colors flex items-center space-x-1.5"
                    >
                        <i class="fa-solid fa-heart text-amber-500 text-xs"></i>
                        <span>Saved (<span x-text="$store.favorites.ids.length">0</span>)</span>
                    </button>
                </nav>

                <button
                    type="button"
                    @click="$store.theme.toggle()"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-amber-300 hover:text-amber-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-amber-500 dark:hover:text-amber-400"
                    :title="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'"
                    :aria-label="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'"
                >
                    <i :class="$store.theme.dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon'" class="text-sm"></i>
                </button>

                <button
                    x-data="{ isAuthed: @js(auth()->check()) }"
                    @click="isAuthed ? $dispatch('open-add-modal') : $dispatch('open-auth-modal')"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-amber-900/30 transition hover:from-amber-500 hover:to-amber-400 sm:px-4 sm:py-2.5 sm:text-sm"
                >
                    <i class="fa-solid fa-plus text-[10px] sm:text-xs"></i>
                    <span class="hidden sm:inline sm:ml-2">List Your Business</span>
                </button>

                @auth
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-slate-900 transition hover:bg-amber-400 sm:h-10 sm:w-10">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </button>

                        <div
                            x-show="userMenuOpen"
                            @click.away="userMenuOpen = false"
                            x-transition
                            style="display: none;"
                            class="absolute right-0 mt-2 w-48 bg-white text-slate-800 rounded-xl shadow-2xl py-2 z-50"
                        >
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs font-semibold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <button
                                wire:click="logout"
                                wire:confirm="Are you sure you want to log out?"
                                class="w-full text-left px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 transition"
                            >
                                <i class="fa-solid fa-right-from-bracket mr-2"></i> Logout
                            </button>
                        </div>
                    </div>
                @else
                    <button
                        @click="$dispatch('open-auth-modal')"
                        class="hidden text-sm font-semibold text-slate-700 transition hover:text-slate-950 sm:inline-flex"
                    >
                        Login / Sign Up
                    </button>
                @endauth
            </div>
        </div>

        <nav class="flex flex-wrap items-center justify-center gap-3 pb-3 text-xs font-medium md:hidden" x-data>
            <a href="{{ route('pricing') }}" class="text-slate-600 hover:text-slate-900 transition-colors">Pricing</a>
            <a href="#about" class="text-slate-600 hover:text-slate-900 transition-colors">Mission</a>
            <button
                @click="$store.favorites.showOnly = !$store.favorites.showOnly"
                :class="$store.favorites.showOnly ? 'text-amber-700 font-semibold' : 'text-slate-600 hover:text-slate-900'"
                class="transition-colors flex items-center space-x-1.5"
            >
                <i class="fa-solid fa-heart text-amber-500 text-[10px]"></i>
                <span>Saved (<span x-text="$store.favorites.ids.length">0</span>)</span>
            </button>
        </nav>
    </div>
</header>
