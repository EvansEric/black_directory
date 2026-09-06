<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $category = 'Dining & Food';
    public string $location = '';
    public string $address = '';
    public string $zip = '';
    public string $phone = '';
    public string $website = '';
    public $image;
    public string $description = '';
    public ?int $editingBusinessId = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        if ($this->editingBusinessId) {
            $business = Business::query()->findOrFail($this->editingBusinessId);

            if (Auth::id() !== $business->user_id) {
                abort(403, 'You are not authorized to edit this business.');
            }

            unset($data['image']);

            if ($this->image) {
                $imagePath = $this->image->store('businesses', 'public');
                $previousImage = $business->image;
                $data['image'] = $imagePath;
            }

            $business->update($data);

            if (isset($previousImage) && $previousImage && ! str_starts_with($previousImage, 'http')) {
                Storage::disk('public')->delete($previousImage);
            }
        } else {
            $imagePath = null;
            if ($this->image) {
                $imagePath = $this->image->store('businesses', 'public');
            }

            Business::create([
                ...$data,
                'image' => $imagePath,
                'user_id' => Auth::id(),
                'rating' => 5.0,
                'reviews_count' => 0,
                'featured' => true,
                'tags' => ['New Listing', 'Local'],
            ]);
        }

        $this->resetForm();
        $this->dispatch('business-updated');
        $this->dispatch('business-saved');
    }

    #[On('open-add-modal')]
    public function startCreate(): void
    {
        $this->resetForm();
    }

    #[On('edit-business')]
    public function editBusiness(int $businessId): void
    {
        if (! Auth::check()) {
            abort(403, 'You must be signed in to edit a business.');
        }

        $business = Business::query()->findOrFail($businessId);

        if (Auth::id() !== $business->user_id) {
            abort(403, 'You are not authorized to edit this business.');
        }

        $this->editingBusinessId = $business->id;
        $this->name = $business->name;
        $this->category = $business->category;
        $this->location = $business->location;
        $this->address = $business->address ?? '';
        $this->zip = $business->zip ?? '';
        $this->phone = $business->phone ?? '';
        $this->website = $business->website ?? '';
        $this->image = null;
        $this->description = $business->description;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'location', 'address', 'zip', 'phone', 'website', 'image', 'description', 'editingBusinessId']);
        $this->category = 'Dining & Food';
        $this->resetValidation();
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:open-add-modal.window="open = true"
    x-on:edit-business.window="open = true"
    x-on:business-saved.window="open = false"
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
            <h3 class="font-serif text-2xl font-bold text-slate-900">{{ $editingBusinessId ? 'Edit Your Business' : 'List Your Business' }}</h3>
            <p class="text-slate-500 text-xs mt-1">{{ $editingBusinessId ? 'Keep your listing details current for customers.' : 'Join the directory and connect with customers in your neighborhood.' }}</p>
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
                        <option value="Automotive & Transport">Automotive & Transport</option>
                        <option value="Home & Trades">Home & Trades</option>
                        <option value="Real Estate & Property">Real Estate & Property</option>
                        <option value="Pet Care & Services">Pet Care & Services</option>
                        <option value="Cleaning & Maintenance">Cleaning & Maintenance</option>
                        <option value="IT & Tech Solutions">IT & Tech Solutions</option>
                        <option value="Legal & Financial">Legal & Financial</option>
                        <option value="Marketing & Media">Marketing & Media</option>
                        <option value="Education & Instruction">Education & Instruction</option>
                        <option value="Travel & Lodging">Travel & Lodging</option>
                        <option value="Sports & Recreation">Sports & Recreation</option>
                        <option value="Entertainment & Events">Entertainment & Events</option>
                        <option value="Non-Profit & Community">Non-Profit & Community</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">City, State *</label>
                    <input type="text" wire:model="location" placeholder="e.g. Atlanta, GA"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Street Address</label>
                <input type="text" wire:model="address" placeholder="e.g. 123 Main St"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">ZIP Code</label>
                    <input type="text" wire:model="zip" placeholder="e.g. 30303"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('zip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                    <input type="tel" wire:model="phone" placeholder="(555) 000-0000"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Website URL</label>
                <input type="url" wire:model="website" placeholder="https://example.com"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('website') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Business Image</label>
                <input type="file" wire:model="image" accept="image/*"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 focus:outline-none">
                <div wire:loading wire:target="image" class="text-xs text-amber-600 mt-1">Uploading preview...</div>
                @if ($editingBusinessId)
                    <p class="text-xs text-slate-500 mt-1">Choose a new image only to replace the current one.</p>
                @endif
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
                        wire:loading.attr="disabled" wire:target="save, image"
                >
                    <span wire:loading.remove wire:target="save, image">{{ $editingBusinessId ? 'Save Changes' : 'Submit Listing' }}</span>
                    <span wire:loading wire:target="save, image">{{ $editingBusinessId ? 'Saving...' : 'Submitting...' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
