<?php

use App\Models\Business;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Noir Directory | Local Black Business Directory')]
class extends Component
{
    #[Computed]
    public function spotlightBusinesses()
    {
        return Business::query()->spotlight()->latest()->take(3)->get();
    }
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
                <p class="text-slate-300 text-base sm:text-lg mb-6">
                    Build wealth, foster community, and find extraordinary local dining, services, artisans, and professionals in your area.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <button
                        x-data
                        @click="$dispatch('open-subscription-modal')"
                        class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs sm:text-sm transition shadow-lg shadow-amber-500/20 flex items-center gap-2"
                    >
                        <i class="fa-solid fa-bolt"></i>
                        <span>Promote Your Listing</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Homepage Spotlight Section --}}
    @if ($this->spotlightBusinesses->count() > 0)
        <section class="bg-amber-950/20 py-10 border-b border-amber-900/20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <span class="text-amber-500 text-xs font-extrabold uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-crown"></i> Featured Banner Slots
                        </span>
                        <h2 class="text-2xl font-serif font-bold text-slate-900 mt-0.5">Homepage Spotlight</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->spotlightBusinesses as $spotlight)
                        <div class="bg-white rounded-2xl border-2 border-amber-500/50 p-5 shadow-xl hover:shadow-2xl transition flex flex-col justify-between relative overflow-hidden">
                            <div class="absolute top-0 right-0 bg-amber-500 text-slate-950 font-extrabold text-[10px] px-3 py-1 rounded-bl-xl uppercase tracking-wider shadow">
                                <i class="fa-solid fa-crown text-[9px]"></i> Spotlight
                            </div>
                            <div>
                                <div class="flex items-center space-x-3 mb-3">
                                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center font-bold text-amber-700 overflow-hidden shrink-0">
                                        @if ($spotlight->canAccessLogo() && $spotlight->image)
                                            <img src="{{ asset('storage/' . $spotlight->image) }}" class="w-full h-full object-cover">
                                        @else
                                            <span>{{ substr($spotlight->name, 0, 1) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-base line-clamp-1">{{ $spotlight->name }}</h3>
                                        <p class="text-xs text-amber-600 font-semibold">{{ $spotlight->category }} • {{ $spotlight->location }}</p>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-600 line-clamp-2 mb-4 leading-relaxed">{{ $spotlight->description }}</p>
                            </div>
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                @if ($spotlight->canAccessCustomUrl() && $spotlight->website)
                                    <a href="{{ $spotlight->website }}" target="_blank" class="text-xs font-bold text-amber-600 hover:underline">
                                        Visit Website <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">Verified Business</span>
                                @endif
                                <button
                                    x-data
                                    @click="$dispatch('open-lead-modal', { businessId: {{ $spotlight->id }} })"
                                    class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold rounded-lg transition"
                                >
                                    Contact Business
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Pricing / Subscription Tiers Section --}}
    <section id="pricing" class="bg-white py-16 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 border border-amber-500/20 mb-3">
                    <i class="fa-solid fa-bolt mr-1.5"></i> Grow Your Reach
                </span>
                <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900 mb-3">Listing Plans & Pricing</h2>
                <p class="text-slate-500 text-sm sm:text-base">
                    Choose the plan that fits your business. Upgrade anytime to unlock more visibility, branding, and lead generation tools.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Standard Tier --}}
                <div class="rounded-2xl p-6 border-2 border-slate-200 hover:border-amber-300 transition flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg mb-1">Basic / Standard</h3>
                        <p class="text-xs text-slate-500 mb-4">Essential custom branding for growing local businesses.</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-slate-900">$19</span>
                            <span class="text-xs text-slate-500">/month</span>
                            <span class="block text-xs text-slate-400 mt-0.5">or $190/year</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> Custom Website Link</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> Logo & Business Image</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> Rich Business Description</li>
                        </ul>
                    </div>
                    <button
                        x-data
                        @click="$dispatch('open-subscription-modal')"
                        class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition"
                    >
                        Choose Standard
                    </button>
                </div>

                {{-- Pro Tier --}}
                <div class="rounded-2xl p-6 border-2 border-amber-500 bg-amber-50/40 shadow-xl ring-2 ring-amber-500/20 flex flex-col justify-between relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-slate-950 font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider shadow">
                        Most Popular
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg mb-1">Featured / Pro</h3>
                        <p class="text-xs text-slate-500 mb-4">Priority placement & direct customer lead forms.</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-slate-900">$49</span>
                            <span class="text-xs text-slate-500">/month</span>
                            <span class="block text-xs text-slate-400 mt-0.5">or $490/year</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                            <li class="flex items-center gap-2 font-semibold text-slate-800"><i class="fa-solid fa-star text-amber-500"></i> Priority Search Placement</li>
                            <li class="flex items-center gap-2 font-semibold text-slate-800"><i class="fa-solid fa-paper-plane text-amber-500"></i> Lead Generation Forms</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> All Basic Features</li>
                        </ul>
                    </div>
                    <button
                        x-data
                        @click="$dispatch('open-subscription-modal')"
                        class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                    >
                        Choose Pro
                    </button>
                </div>

                {{-- Spotlight Tier --}}
                <div class="rounded-2xl p-6 border-2 border-slate-200 hover:border-amber-300 transition flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg mb-1">Homepage Spotlight</h3>
                        <p class="text-xs text-slate-500 mb-4">Maximum reach with top banner & homepage rotation.</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-slate-900">$199</span>
                            <span class="text-xs text-slate-500">/month</span>
                            <span class="block text-xs text-slate-400 mt-0.5">or $1,990/year</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                            <li class="flex items-center gap-2 font-semibold text-slate-900"><i class="fa-solid fa-crown text-amber-500"></i> Top Banner & Homepage Rotation</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> Priority Placement</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> Lead Generation Forms</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500"></i> All Pro Features</li>
                        </ul>
                    </div>
                    <button
                        x-data
                        @click="$dispatch('open-subscription-modal')"
                        class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition"
                    >
                        Choose Spotlight
                    </button>
                </div>
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
                    <li>
                        <button
                            x-data
                            @click="$dispatch('open-subscription-modal')"
                            class="hover:text-white transition"
                        >
                            Pricing & Listing Plans
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
    <livewire:subscription-modal />
    <livewire:lead-form-modal />
</div>
