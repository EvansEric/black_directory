<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new
#[Layout('layouts.app')]
#[Title('Noir Directory | Local Black Business Directory')]
class extends Component
{
    public function canPromoteListing(): bool
    {
        $user = Auth::user();

        return ! $user || \App\Services\SubscriptionGating::ownerHasVerifiedBusiness($user);
    }
};

?>

<div>
    <livewire:navigation />

    @if (session('status'))
        <x-toastr-message :message="session('status')" />
    @endif

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
                @if (! auth()->check() || (! auth()->user()->is_admin && ! auth()->user()->is_super_admin))
                    <div class="mt-6 flex items-center justify-center gap-3">
                        @if ($this->canPromoteListing())
                            <button
                                x-data
                                @click="$dispatch('open-subscription-modal')"
                                class="promote-listing-button flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-xs font-bold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400 sm:w-auto sm:text-sm"
                            >
                                <i class="fa-solid fa-bolt"></i>
                                <span>Promote Your Listing</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Main Content --}}
    <main class="mx-auto w-full max-w-7xl grow px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <div class="grid grid-cols-1 items-start gap-8 {{ config('app.advertisements_enabled') ? 'xl:grid-cols-[minmax(0,1fr)_18rem]' : '' }}">
            <livewire:business-list />

            @if (config('app.advertisements_enabled'))
            <aside class="w-full xl:sticky xl:top-24" aria-label="Advertisement">
                <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Advertisement</h2>
                <div
                    data-ad-slot="directory-sidebar"
                    class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center xl:min-h-[37.5rem]"
                >
                    <i class="fa-solid fa-rectangle-ad mb-4 text-3xl text-amber-500" aria-hidden="true"></i>
                    <p class="font-semibold text-slate-700">Your ad could be here</p>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">A dedicated space to reach people supporting local businesses.</p>
                </div>
            </aside>
            @endif
        </div>
    </main>

    <x-footer />

    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
    <livewire:lead-form-modal />
    <livewire:business-details-modal />
</div>
