<?php

use App\Models\Business;
use App\Services\BusinessSubscriptionManager;
use App\Services\SubscriptionGating;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?int $businessId = null;
    public ?Business $business = null;
    public string $selectedTier = 'pro';
    public string $billingCycle = 'monthly';

    #[On('open-subscription-modal')]
    public function openSubscriptionModal(?int $businessId = null, ?string $tier = null): void
    {
        if ($tier && in_array($tier, ['free', 'standard', 'pro'], true)) {
            $this->selectedTier = $tier;
        }

        if ($businessId) {
            $this->businessId = $businessId;
            $this->business = Business::find($businessId);
            if ($this->business && in_array($this->business->subscription_tier, ['standard', 'pro'], true)) {
                $this->selectedTier = $this->business->subscription_tier;
                $this->billingCycle = $this->business->billing_cycle ?? 'monthly';
            }
        } elseif (Auth::check()) {
            $userBusiness = Auth::user()->businesses()->first();
            if ($userBusiness) {
                $this->businessId = $userBusiness->id;
                $this->business = $userBusiness;
            }
        }

        $this->dispatch('show-subscription-modal');
    }

    public function selectTier(string $tier): void
    {
        if (in_array($tier, ['free', 'standard', 'pro'], true)) {
            $this->selectedTier = $tier;
        }
    }

    public function selectCycle(string $cycle): void
    {
        $this->billingCycle = $cycle;
    }

    public function checkout(BusinessSubscriptionManager $subscriptions): mixed
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal');

            return null;
        }

        if (! $this->businessId) {
            $this->dispatch('open-add-modal');

            return null;
        }

        $this->validate([
            'selectedTier' => ['required', 'in:free,standard,pro'],
            'billingCycle' => ['required', 'in:monthly,annual'],
        ]);

        $business = Business::query()->findOrFail($this->businessId);
        abort_unless(Auth::id() === $business->user_id, 403, 'Unauthorized to manage subscription for this listing.');

        if ($this->selectedTier === SubscriptionGating::TIER_FREE) {
            $subscriptions->selectFreePlan($business);

            $this->dispatch('business-updated');
            $this->dispatch('close-subscription-modal');

            return null;
        }

        $checkout = $subscriptions->createCheckout($business, $this->selectedTier, $this->billingCycle);

        return redirect()->away($checkout['url'], 303);
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:show-subscription-modal.window="open = true"
    x-on:close-subscription-modal.window="open = false"
    x-show="open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
    style="display: none;"
    @keydown.escape.window="open = false"
>
    <div @click.away="open = false" class="bg-white rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl relative">
        <button @click="open = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="text-center max-w-xl mx-auto mb-8">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 border border-amber-500/20 mb-3">
                <i class="fa-solid fa-bolt mr-1.5"></i> Supercharge Your Listing Visibility
            </span>
            <h3 class="font-serif text-3xl font-bold text-slate-900">Choose Your Listing Plan</h3>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">Upgrade your business listing to unlock premium exposure, lead generation, and custom branding.</p>
        </div>

        {{-- Billing Cycle Toggle --}}
        @if ($selectedTier !== 'free')
        <div class="flex justify-center mb-8">
            <div class="bg-slate-100 p-1 rounded-xl flex items-center space-x-1 border border-slate-200">
                <button
                    wire:click="selectCycle('monthly')"
                    type="button"
                    class="px-4 py-2 text-xs font-bold rounded-lg transition {{ $billingCycle === 'monthly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}"
                >
                    Monthly Billing
                </button>
                <button
                    wire:click="selectCycle('annual')"
                    type="button"
                    class="px-4 py-2 text-xs font-bold rounded-lg transition flex items-center gap-1.5 {{ $billingCycle === 'annual' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}"
                >
                    <span>Annual Billing</span>
                    <span class="bg-emerald-500 text-white text-[10px] px-1.5 py-0.5 rounded font-extrabold uppercase">Save 20%</span>
                </button>
            </div>
        </div>
        @endif

        {{-- Pricing Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {{-- Free Tier --}}
            <div
                wire:click="selectTier('free')"
                class="rounded-2xl p-6 border-2 transition cursor-pointer flex flex-col justify-between relative {{ $selectedTier === 'free' ? 'border-amber-500 bg-amber-50/30 shadow-lg' : 'border-slate-200 hover:border-slate-300' }}"
            >
                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Free</h4>
                    <p class="text-xs text-slate-500 mb-4">Get your business listed in the directory at no cost.</p>
                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">Free</span>
                    </div>
                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500 text-xs"></i><span>1 Business Listing</span></li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500 text-xs"></i><span>Business Profile & Directory Placement</span></li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-check text-amber-500 text-xs"></i><span>Business Contact Information</span></li>
                    </ul>
                </div>
            </div>

            {{-- Standard Tier --}}
            <div
                wire:click="selectTier('standard')"
                class="rounded-2xl p-6 border-2 transition cursor-pointer flex flex-col justify-between relative {{ $selectedTier === 'standard' ? 'border-amber-500 bg-amber-50/30 shadow-lg' : 'border-slate-200 hover:border-slate-300' }}"
            >
                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Standard</h4>
                    <p class="text-xs text-slate-500 mb-4">Essential custom branding for growing local businesses.</p>

                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">${{ $billingCycle === 'annual' ? '47.90' : '4.99' }}</span>
                        <span class="text-xs text-slate-500">/{{ $billingCycle === 'annual' ? 'year' : 'month' }}</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>1 Business Listing</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Custom Website Link</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Logo & Business Image</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Rich Business Description</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Pro Tier --}}
            <div
                wire:click="selectTier('pro')"
                class="rounded-2xl p-6 border-2 transition cursor-pointer flex flex-col justify-between relative {{ $selectedTier === 'pro' ? 'border-amber-500 bg-amber-50/40 shadow-xl ring-2 ring-amber-500/20' : 'border-slate-200 hover:border-slate-300' }}"
            >
                <div class="most-popular-badge absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider shadow-md">
                    Most Popular
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Pro</h4>
                    <p class="text-xs text-slate-500 mb-4">Priority placement & direct customer lead forms.</p>

                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">${{ $billingCycle === 'annual' ? '105.50' : '10.99' }}</span>
                        <span class="text-xs text-slate-500">/{{ $billingCycle === 'annual' ? 'year' : 'month' }}</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Up to 5 Business Listings</span>
                        </li>
                        <li class="flex items-center gap-2 font-semibold text-slate-800">
                            <i class="fa-solid fa-star text-amber-500 text-xs"></i>
                            <span>Priority Search Placement</span>
                        </li>
                        <li class="flex items-center gap-2 font-semibold text-slate-800">
                            <i class="fa-solid fa-paper-plane text-amber-500 text-xs"></i>
                            <span>Lead Generation Forms</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>All Standard Features</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-slate-500">
                <span>{{ $selectedTier === 'free' ? 'No payment required for the Free plan.' : 'Secure payment powered by Stripe. Pay with card, Apple Pay, Google Pay, or Link. Cancel anytime.' }}</span>
            </div>

            <button
                wire:click="checkout"
                type="button"
                class="w-full sm:w-auto px-8 py-3.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                wire:loading.attr="disabled" wire:target="checkout"
            >
                <span wire:loading.remove wire:target="checkout">{{ $selectedTier === 'free' ? 'Choose Free Plan' : 'Proceed to Checkout ($'.($selectedTier === 'pro' ? ($billingCycle === 'annual' ? '105.50/yr' : '10.99/mo') : ($billingCycle === 'annual' ? '47.90/yr' : '4.99/mo')).')' }}</span>
                <span wire:loading wire:target="checkout">Preparing Checkout...</span>
            </button>
        </div>
    </div>
</div>
