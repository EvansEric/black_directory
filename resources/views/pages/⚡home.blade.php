<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('RNoir Directory | Local Black Business Directory')]
class extends Component
{
    //
}; 

?>

<div>
    <livewire:navigation />

    {{-- Hero Search Area --}}
    <section class="relative bg-brand-dark text-white py-16 lg:py-24 overflow-hidden border-b border-amber-900/20">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-amber-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/2 -right-24 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-10">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 mb-4">
                    <i class="fa-solid fa-location-dot mr-1.5"></i> Support Local Black Entrepreneurs
                </span>
                <h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white mb-6 leading-tight">
                    Discover Black-Owned Businesses Near You.
                </h1>
                <p class="text-slate-300 text-base sm:text-lg">
                    Build wealth, foster community, and find extraordinary local dining, services, artisans, and professionals in your area.
                </p>
            </div>
        </div>
    </section>

    {{-- Main Content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-grow w-full">
        <livewire:business-list />
    </main>

    {{-- Footer --}}
    <footer class="bg-brand-dark text-slate-400 py-12 border-t border-amber-900/20 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <div class="md:col-span-2">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center font-serif text-lg font-bold text-white">N</div>
                    <span class="font-serif text-xl font-bold text-white tracking-tight">Noir Directory</span>
                </div>
                <p class="text-xs text-slate-400 max-w-sm leading-relaxed">
                    Connecting communities with local Black-owned businesses. Empowering economic growth, preserving culture, and promoting sustainable local support.
                </p>
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-3">Community</h4>
                <ul class="text-xs space-y-2">
                    <li>
                        <button
                            x-data="{ isAuthed: @js(auth()->check()) }"
                            @click="isAuthed ? $dispatch('open-add-modal') : $dispatch('open-auth-modal')"
                            class="hover:text-white transition"
                        >
                            Add a Business
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 border-t border-slate-800 text-center md:text-left text-xs">
            <p>&copy; {{ date('Y') }} Noir Business Directory.</p>
        </div>
    </footer>

    <livewire:auth-modal />
    <livewire:add-business-modal />
</div>
