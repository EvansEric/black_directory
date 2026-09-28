<?php

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
            'key' => 'standard',
            'name' => 'Basic / Standard',
            'description' => 'Essential custom branding for growing local businesses.',
            'monthly' => '9.99',
            'annual' => '95.90',
            'features' => ['Custom Website Link', 'Logo & Business Image', 'Rich Business Description'],
        ],
        [
            'key' => 'pro',
            'name' => 'Featured / Pro',
            'description' => 'Priority placement and direct customer lead forms.',
            'monthly' => '19.99',
            'annual' => '191.90',
            'features' => ['Priority Search Placement', 'Lead Generation Forms', 'All Basic Features'],
            'popular' => true,
        ],
        [
            'key' => 'spotlight',
            'name' => 'Homepage Spotlight',
            'description' => 'Maximum reach with top banner and homepage rotation.',
            'monthly' => '99.99',
            'annual' => '959.90',
            'features' => ['Top Banner & Homepage Rotation', 'Priority Placement', 'Lead Generation Forms', 'All Pro Features'],
        ],
    ];
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
                        @if ($plan['popular'] ?? false)
                            <div class="most-popular-badge absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider shadow-md">
                                Most Popular
                            </div>
                        @endif

                        <div>
                            <h2 class="mb-1 text-lg font-bold text-slate-900">{{ $plan['name'] }}</h2>
                            <p class="mb-5 text-xs text-slate-500">{{ $plan['description'] }}</p>
                            <div class="mb-5">
                                <span class="text-4xl font-bold text-slate-900">${{ $plan['monthly'] }}</span>
                                <span class="text-xs text-slate-500">/month</span>
                                <span class="mt-1 block text-xs text-slate-400">or ${{ $plan['annual'] }}/year</span>
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
                            Choose {{ $plan['name'] }}
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
