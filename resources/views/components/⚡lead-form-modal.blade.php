<?php

use App\Models\Business;
use App\Services\SubscriptionGating;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?int $businessId = null;
    public string $businessName = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public bool $sent = false;

    #[On('open-lead-modal')]
    public function openLeadModal(int $businessId): void
    {
        $business = Business::find($businessId);

        if (! $business || ! SubscriptionGating::canAccessLeadForms($business)) {
            return;
        }

        $this->reset(['name', 'email', 'phone', 'message', 'sent']);
        $this->resetValidation();

        $this->businessId = $business->id;
        $this->businessName = $business->name;

        $this->dispatch('show-lead-modal');
    }

    public function submitLead(): void
    {
        if (! $this->businessId) {
            return;
        }

        $business = Business::findOrFail($this->businessId);

        if (! SubscriptionGating::canAccessLeadForms($business)) {
            $this->addError('message', 'This business listing is not currently eligible for lead inquiries.');

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $business->leads()->create($validated);

        $this->sent = true;
        $this->reset(['name', 'email', 'phone', 'message']);
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:show-lead-modal.window="open = true"
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
    <div @click.away="open = false" class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative">
        <button @click="open = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="mb-6">
            <div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 mb-2">
                <i class="fa-solid fa-paper-plane mr-1.5"></i> Send Direct Inquiry
            </div>
            <h3 class="font-serif text-2xl font-bold text-slate-900">Contact {{ $businessName }}</h3>
            <p class="text-slate-500 text-xs mt-1">Fill out the form below to get a quote or message this business owner directly.</p>
        </div>

        @if ($sent)
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs space-y-2 text-center mb-4">
                <i class="fa-solid fa-circle-check text-2xl text-emerald-500 block"></i>
                <p class="font-bold text-sm">Inquiry Sent Successfully!</p>
                <p>The business owner will receive your contact details and reach back out shortly.</p>
                <button @click="open = false" class="mt-2 px-4 py-2 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-500">
                    Done
                </button>
            </div>
        @else
            <form wire:submit="submitLead" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Your Name *</label>
                    <input type="text" wire:model="name" placeholder="Jane Doe"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" wire:model="email" placeholder="jane@example.com"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                        <input type="tel" wire:model="phone" placeholder="(555) 000-0000"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Message / Request *</label>
                    <textarea wire:model="message" rows="4" placeholder="How can this business help you? Ask for pricing, availability, or general details..."
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                    @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                        wire:loading.attr="disabled" wire:target="submitLead"
                >
                    <span wire:loading.remove wire:target="submitLead">Send Message</span>
                    <span wire:loading wire:target="submitLead">Sending...</span>
                </button>
            </form>
        @endif
    </div>
</div>
