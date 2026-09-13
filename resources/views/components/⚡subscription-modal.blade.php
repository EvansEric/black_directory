<?php

use App\Models\Business;
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
    public function openSubscriptionModal(?int $businessId = null): void
    {
        if ($businessId) {
            $this->businessId = $businessId;
            $this->business = Business::find($businessId);
            if ($this->business && $this->business->subscription_tier !== 'free') {
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
        $this->selectedTier = $tier;
    }

    public function selectCycle(string $cycle): void
    {
        $this->billingCycle = $cycle;
    }

    public function checkout(): mixed
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal');

            return null;
        }

        if (! $this->businessId) {
            $this->dispatch('open-add-modal');

            return null;
        }

        $controller = new \App\Http\Controllers\SubscriptionController();
        $request = new \Illuminate\Http\Request([
            'business_id' => $this->businessId,
            'tier' => $this->selectedTier,
            'billing_cycle' => $this->billingCycle,
        ]);

        $response = $controller->checkout($request);

        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            return redirect($response->getTargetUrl());
        }

        $data = json_decode($response->getContent(), true);
        if (isset($data['checkout_url'])) {
            return redirect($data['checkout_url']);
        }

        $this->dispatch('business-updated');
        $this->dispatch('close-subscription-modal');

        return null;
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

        {{-- Pricing Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {{-- Standard Tier --}}
            <div
                wire:click="selectTier('standard')"
                class="rounded-2xl p-6 border-2 transition cursor-pointer flex flex-col justify-between relative {{ $selectedTier === 'standard' ? 'border-amber-500 bg-amber-50/30 shadow-lg' : 'border-slate-200 hover:border-slate-300' }}"
            >
                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Basic / Standard</h4>
                    <p class="text-xs text-slate-500 mb-4">Essential custom branding for growing local businesses.</p>

                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">${{ $billingCycle === 'annual' ? '190' : '19' }}</span>
                        <span class="text-xs text-slate-500">/{{ $billingCycle === 'annual' ? 'year' : 'month' }}</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
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
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-slate-950 font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider shadow">
                    Most Popular
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Featured / Pro</h4>
                    <p class="text-xs text-slate-500 mb-4">Priority placement & direct customer lead forms.</p>

                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">${{ $billingCycle === 'annual' ? '490' : '49' }}</span>
                        <span class="text-xs text-slate-500">/{{ $billingCycle === 'annual' ? 'year' : 'month' }}</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
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
                            <span>All Basic Features</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Spotlight Tier --}}
            <div
                wire:click="selectTier('spotlight')"
                class="rounded-2xl p-6 border-2 transition cursor-pointer flex flex-col justify-between relative {{ $selectedTier === 'spotlight' ? 'border-amber-500 bg-amber-50/30 shadow-lg' : 'border-slate-200 hover:border-slate-300' }}"
            >
                <div>
                    <h4 class="font-bold text-slate-900 text-base mb-1">Homepage Spotlight</h4>
                    <p class="text-xs text-slate-500 mb-4">Maximum reach with top banner & homepage rotation.</p>

                    <div class="mb-4">
                        <span class="text-3xl font-bold text-slate-900">${{ $billingCycle === 'annual' ? '1990' : '199' }}</span>
                        <span class="text-xs text-slate-500">/{{ $billingCycle === 'annual' ? 'year' : 'month' }}</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                        <li class="flex items-center gap-2 font-semibold text-slate-900">
                            <i class="fa-solid fa-crown text-amber-500 text-xs"></i>
                            <span>Top Banner & Homepage Rotation</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Priority Placement</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>Lead Generation Forms</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-amber-500 text-xs"></i>
                            <span>All Pro Features</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-slate-500">
                <span>Secure payment powered by Stripe. Cancel anytime.</span>
            </div>

            <button
                wire:click="checkout"
                type="button"
                class="w-full sm:w-auto px-8 py-3.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                wire:loading.attr="disabled" wire:target="checkout"
            >
                <span wire:loading.remove wire:target="checkout">Proceed to Checkout (${{ match($selectedTier) { 'spotlight' => ($billingCycle === 'annual' ? '1,990/yr' : '199/mo'), 'pro' => ($billingCycle === 'annual' ? '490/yr' : '49/mo'), default => ($billingCycle === 'annual' ? '190/yr' : '19/mo') } }})</span>
                <span wire:loading wire:target="checkout">Preparing Checkout...</span>
            </button>
        </div>
    </div>
</div>
