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
    <section class="relative overflow-hidden border-b border-amber-900/10 bg-brand-50 py-12 text-slate-900 sm:py-16 lg:py-24">
        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-amber-200/40 blur-3xl sm:h-96 sm:w-96"></div>
        <div class="pointer-events-none absolute -right-24 top-1/2 h-72 w-72 rounded-full bg-emerald-100/60 blur-3xl sm:h-96 sm:w-96"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mb-10 max-w-3xl text-center">
                <span class="mb-4 inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[10px] font-semibold text-amber-400 sm:text-xs">
                    <i class="fa-solid fa-location-dot mr-1.5"></i> Support Local Black Entrepreneurs
                </span>
                <h1 class="mb-4 font-serif text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                    Discover Black-Owned Businesses Near You.
                </h1>
                <p class="mx-auto max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base lg:text-lg">
                    Build wealth, foster community, and find extraordinary local dining, services, artisans, and professionals in your area.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        x-data
                        @click="$dispatch('open-subscription-modal')"
                        class="promote-listing-button flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-xs font-bold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 sm:w-auto sm:text-sm"
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
        <section class="border-b border-amber-900/10 bg-amber-50/70 py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <span class="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-amber-500">
                            <i class="fa-solid fa-crown"></i> Featured Banner Slots
                        </span>
                        <h2 class="mt-0.5 font-serif text-2xl font-bold text-slate-900">Homepage Spotlight</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
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

    {{-- Main Content --}}
    <main class="mx-auto w-full max-w-7xl grow px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <livewire:business-list />
    </main>

    <x-footer />

    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
    <livewire:lead-form-modal />
</div>
