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

<header class="sticky top-0 z-40 bg-brand-dark/95 backdrop-blur-md text-white border-b border-amber-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            {{-- Logo --}}
            <a href="/" class="flex items-center space-x-3">
                 <div class="w-11 h-11 rounded-2xl bg-amber-500 flex items-center justify-center font-serif text-2xl font-black text-noir-950 shadow-lg shadow-gold-500/20 group-hover:scale-105 transition-transform duration-300">
                        N
                    </div>
                    <div>
                        <span class="font-serif text-2xl font-bold tracking-tight text-white flex items-center gap-1.5">
                            Noir<span class="gold-gradient-text">Directory</span>
                        </span>
                        <span class="block text-[10px] tracking-[0.2em] text-gold-400/80 font-bold uppercase">Local Black-Owned Excellence</span>
                    </div>
            </a>

            {{-- Desktop Nav --}}
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium" x-data>
                <button
                    @click="$store.favorites.reset()"
                    :class="!$store.favorites.showOnly ? 'text-amber-400 font-semibold' : 'text-slate-300 hover:text-white'"
                    class="transition-colors"
                >
                    Explore Directory
                </button>
                <a href="#about" class="text-slate-300 hover:text-white transition-colors">Mission</a>
                <button
                    @click="$store.favorites.showOnly = !$store.favorites.showOnly"
                    :class="$store.favorites.showOnly ? 'text-amber-400 font-semibold' : 'text-slate-300 hover:text-white'"
                    class="transition-colors flex items-center space-x-1.5"
                >
                    <i class="fa-solid fa-heart text-amber-500 text-xs"></i>
                    <span>Saved (<span x-text="$store.favorites.ids.length">0</span>)</span>
                </button>
            </nav>

            {{-- Actions --}}
            <div class="flex items-center space-x-4" x-data="{ userMenuOpen: false }">
                <button
                    x-data="{ isAuthed: @js(auth()->check()) }"
                    @click="isAuthed ? $dispatch('open-add-modal') : $dispatch('open-auth-modal')"
                    class="bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition shadow-lg shadow-amber-900/30 flex items-center space-x-2"
                >
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>List Your Business</span>
                </button>

                @auth
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="w-10 h-10 rounded-full bg-amber-500 text-slate-900 font-bold flex items-center justify-center hover:bg-amber-400 transition">
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
                        class="text-slate-200 hover:text-white text-sm font-semibold px-3 py-2.5 rounded-xl transition"
                    >
                        Login / Sign Up
                    </button>
                @endauth
            </div>
        </div>
    </div>
</header>