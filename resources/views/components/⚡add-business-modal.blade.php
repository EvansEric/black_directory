<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $category = 'Dining & Food';
    public string $location = '';
    public string $phone = '';
    public string $website = '';
    public string $image = '';
    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'url', 'max:2048'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }

    public function save(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal');

            return;
        }

        $data = $this->validate();

        Business::create([
            ...$data,
            'user_id' => Auth::id(),
            'address' => $data['location'],
            'rating' => 5.0,
            'reviews_count' => 0,
            'featured' => true,
            'tags' => ['New Listing', 'Local'],
        ]);

        $this->reset(['name', 'location', 'phone', 'website', 'image', 'description']);
        $this->category = 'Dining & Food';

        $this->dispatch('business-updated');
        $this->dispatch('business-added');
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:open-add-modal.window="open = true"
    x-on:business-added.window="open = false"
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
    <div @click.away="open = false" class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative">
        <button @click="open = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="mb-6">
            <h3 class="font-serif text-2xl font-bold text-slate-900">List Your Business</h3>
            <p class="text-slate-500 text-xs mt-1">Join the directory and connect with customers in your neighborhood.</p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Business Name *</label>
                <input type="text" wire:model="name" placeholder="e.g. Harmony Organic Cafe"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Category *</label>
                    <select wire:model="category" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="Dining & Food">Dining & Food</option>
                        <option value="Beauty & Barber">Beauty & Barber</option>
                        <option value="Professional Services">Professional Services</option>
                        <option value="Retail & Fashion">Retail & Fashion</option>
                        <option value="Health & Wellness">Health & Wellness</option>
                        <option value="Arts & Creative">Arts & Creative</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">City, State *</label>
                    <input type="text" wire:model="location" placeholder="e.g. Atlanta, GA"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                    <input type="tel" wire:model="phone" placeholder="(555) 000-0000"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Website URL</label>
                    <input type="url" wire:model="website" placeholder="https://example.com"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('website') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Image URL (Unsplash or Direct Link)</label>
                <input type="url" wire:model="image" placeholder="https://images.unsplash.com/..."
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Business Description *</label>
                <textarea wire:model="description" rows="3" placeholder="Tell customers about your mission, services, or products..."
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20"
                    wire:loading.attr="disabled" wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">Submit Listing</span>
                    <span wire:loading wire:target="save">Submitting...</span>
                </button>
            </div>
        </form>
    </div>
</div>
