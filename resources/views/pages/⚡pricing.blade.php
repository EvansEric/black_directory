<?php

use App\Services\SubscriptionGating;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Listing Plans & Pricing | Noir Directory')]
class extends Component
{
    public array $plans = [
        [
            'key' => 'free',
            'name' => 'Free',
            'description' => 'Get your business listed in the directory at no cost.',
            'monthly' => '0.00',
            'annual' => null,
            'features' => ['1 Business Listing', 'Business Profile & Directory Placement', 'Business Contact Information'],
        ],
        [
            'key' => 'standard',
            'name' => 'Standard',
            'description' => 'Essential custom branding for growing local businesses.',
            'monthly' => '4.99',
            'annual' => '47.90',
            'features' => ['1 Business Listing', 'Custom Website Link', 'Logo & Business Image', 'Rich Business Description'],
        ],
        [
            'key' => 'pro',
            'name' => 'Pro',
            'description' => 'Priority placement and direct customer lead forms.',
            'monthly' => '10.99',
            'annual' => '105.50',
            'features' => ['Up to 5 Business Listings', 'Priority Search Placement', 'Lead Generation Forms', 'All Standard Features'],
            'popular' => true,
        ],
    ];

    public function currentTier(): ?string
    {
        return Auth::check() ? SubscriptionGating::getOwnerTier(Auth::user()) : null;
    }
}; ?>

<div class="flex min-h-screen flex-col bg-white">
    <livewire:navigation />

    <main class="grow">
        <section class="border-b border-amber-900/10 bg-brand-50 px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <span class="mb-3 inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-600">
                    <i class="fa-solid fa-bolt mr-1.5"></i> Grow Your Reach
                </span>
                <h1 class="mb-4 font-serif text-4xl font-bold text-slate-900 sm:text-5xl">Listing Plans & Pricing</h1>
                <p class="text-sm leading-relaxed text-slate-600 sm:text-base">
                    Choose the plan that fits your business. Upgrade anytime to unlock more visibility, branding, and lead generation tools.
                </p>
            </div>
        </section>

        <section class="bg-white px-4 py-10 sm:px-6 sm:py-16 lg:px-8">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 md:grid-cols-3">
                @foreach ($plans as $plan)
                    <article class="relative flex flex-col justify-between rounded-2xl border-2 p-6 transition {{ ($plan['popular'] ?? false) ? 'border-amber-500 bg-amber-50/40 shadow-xl ring-2 ring-amber-500/20' : 'border-slate-200 hover:border-amber-300' }}">
                        @if ($this->currentTier() === $plan['key'])
                            <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-emerald-600 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow" data-current-plan-badge>Current plan</div>
                        @endif

                        @if ($plan['popular'] ?? false)
                            <div class="most-popular-badge absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider shadow-md">
                                Most Popular
                            </div>
                        @endif

                        <div>
                            <h2 class="mb-1 text-lg font-bold text-slate-900">{{ $plan['name'] }}</h2>
                            <p class="mb-5 text-xs text-slate-500">{{ $plan['description'] }}</p>
                            <div class="mb-5">
                                @if ($plan['key'] === 'free')
                                    <span class="text-4xl font-bold text-slate-900">Free</span>
                                @else
                                    <span class="text-4xl font-bold text-slate-900">${{ $plan['monthly'] }}</span>
                                    <span class="text-xs text-slate-500">/month</span>
                                    <span class="mt-1 block text-xs text-slate-400">or ${{ $plan['annual'] }}/year</span>
                                @endif
                            </div>
                            <ul class="mb-8 space-y-3 text-xs text-slate-600">
                                @foreach ($plan['features'] as $feature)
                                    <li class="flex items-start gap-2">
                                        <i class="fa-solid fa-check mt-0.5 text-amber-500"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <button
                            x-data
                            @click="$dispatch('open-subscription-modal', { tier: '{{ $plan['key'] }}' })"
                            class="w-full rounded-xl py-3 text-sm font-bold transition {{ ($plan['popular'] ?? false) ? 'bg-amber-600 text-white shadow-lg shadow-amber-900/20 hover:bg-amber-500' : 'bg-slate-900 text-white hover:bg-slate-800' }}"
                        >
                            {{ $this->currentTier() === $plan['key'] ? 'Manage '.$plan['name'].' (current)' : 'Choose '.$plan['name'] }}
                        </button>
                    </article>
                @endforeach
            </div>
        </section>
    </main>

    <x-footer />

    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
</div>
