<?php

use App\Models\Business;
use App\Services\BusinessSubscriptionManager;
use App\Services\Geocoder;
use App\Services\SubscriptionGating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $category = 'Dining & Food';
    public string $city = '';
    public string $state = '';
    public string $address = '';
    public string $zip = '';
    public string $phone = '';
    public string $website = '';
    public string $customSlug = '';
    public string $tags = '';
    public string $freeParking = '';
    public array $insuranceTypes = [];
    public $image;
    public string $description = '';
    public ?int $editingBusinessId = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2', 'alpha'],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'starts_with:http://,https://', 'max:255'],
            'customSlug' => [
                'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('businesses', 'custom_slug')->ignore($this->editingBusinessId),
            ],
            'tags' => ['nullable', 'string', 'max:1000'],
            'freeParking' => ['nullable', 'in:0,1'],
            'insuranceTypes' => ['array'],
            'insuranceTypes.*' => ['string', Rule::in(array_keys(Business::INSURANCE_TYPES))],
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

        if ($this->editingBusinessId) {
            abort_unless(
                Auth::id() === Business::query()->findOrFail($this->editingBusinessId)->user_id,
                403,
                'You are not authorized to edit this business.'
            );
        }

        if (! $this->editingBusinessId && ! SubscriptionGating::canAddBusiness(Auth::user())) {
            $this->addError('name', $this->limitMessage());

            return;
        }

        $data = $this->validate();
        $data['state'] = strtoupper($data['state']);
        $data['custom_slug'] = filled($data['customSlug'] ?? null) ? strtolower(trim($data['customSlug'])) : null;
        unset($data['customSlug']);
        // Parking only applies to food businesses; blank means not specified.
        $data['free_parking'] = ($data['category'] === 'Dining & Food' && $data['freeParking'] !== null && $data['freeParking'] !== '')
            ? $data['freeParking'] === '1'
            : null;
        unset($data['freeParking']);
        // Insurance types only apply to Insurance listings.
        $data['insurance_types'] = $data['category'] === 'Insurance'
            ? array_values(array_unique($data['insuranceTypes']))
            : null;
        unset($data['insuranceTypes']);
        $data['location'] = trim($data['city']).', '.$data['state'];
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingBusiness = $this->editingBusinessId
            ? Business::query()->findOrFail($this->editingBusinessId)
            : null;

        // Custom link and logo upload are Standard-tier features; strip them for free listings.
        if (! $this->canEditWebsite()) {
            $data['website'] = $existingBusiness?->website;
            $data['custom_slug'] = $existingBusiness?->custom_slug;
        }

        $data = [...$data, ...$this->coordinatesFor($data, $existingBusiness)];

        if (! SubscriptionGating::canAccessLogo($existingBusiness)) {
            $this->image = null;
        }

        if ($this->editingBusinessId) {
            $business = $existingBusiness;

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

            $business = Business::create([
                ...$data,
                'image' => $imagePath,
                'user_id' => Auth::id(),
                'rating' => 5.0,
                'reviews_count' => 0,
                'featured' => true,
            ]);

            app(BusinessSubscriptionManager::class)->claimPendingPlan(Auth::user(), $business);
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
        [$fallbackCity, $fallbackState] = array_pad(array_map('trim', explode(',', (string) $business->location, 2)), 2, '');
        $this->city = $business->city ?? $fallbackCity;
        $this->state = $business->state ?? strtoupper(substr($fallbackState, 0, 2));
        $this->address = $business->address ?? '';
        $this->zip = $business->zip ?? '';
        $this->phone = $business->phone ?? '';
        $this->website = $business->website ?? '';
        $this->customSlug = $business->custom_slug ?? '';
        $this->tags = implode(', ', $business->tags ?? []);
        $this->freeParking = $business->free_parking === null ? '' : ($business->free_parking ? '1' : '0');
        $this->insuranceTypes = $business->insurance_types ?? [];
        $this->image = null;
        $this->description = $business->description;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'city', 'state', 'address', 'zip', 'phone', 'website', 'customSlug', 'tags', 'freeParking', 'insuranceTypes', 'image', 'description', 'editingBusinessId']);
        $this->category = 'Dining & Food';
        $this->resetValidation();
    }

    /**
     * Distance search needs coordinates; look them up when the address is new or changed.
     *
     * @param  array<string, mixed>  $data
     * @return array{latitude?: float|null, longitude?: float|null}
     */
    private function coordinatesFor(array $data, ?Business $existing): array
    {
        $unchanged = $existing
            && $existing->latitude !== null
            && collect(['address', 'city', 'state', 'zip'])->every(
                fn (string $field): bool => (string) $existing->{$field} === (string) ($data[$field] ?? '')
            );

        if ($unchanged) {
            return [];
        }

        $found = app(Geocoder::class)->geocode(
            collect([$data['address'] ?? null, $data['city'], $data['state'], $data['zip'] ?? null])->filter()->implode(', ')
        );

        return $found ?? ['latitude' => null, 'longitude' => null];
    }

    public function canEditWebsite(): bool
    {
        $business = $this->currentBusiness();

        if ($business || ! Auth::check()) {
            return SubscriptionGating::canAccessCustomUrl($business);
        }

        // A new listing inherits the plan the owner already pays for.
        return SubscriptionGating::getOwnerTier(Auth::user()) !== SubscriptionGating::TIER_FREE;
    }

    public function limitMessage(): string
    {
        $user = Auth::user();
        $limit = SubscriptionGating::businessLimit($user);
        $plan = SubscriptionGating::TIERS[SubscriptionGating::getOwnerTier($user)]['name'];
        $noun = $limit === 1 ? 'listing' : 'listings';
        $upgrade = $limit === 1 ? 'Upgrade to Pro to list up to 5 businesses.' : 'Upgrade to Spotlight for unlimited listings.';

        return "Your {$plan} plan allows only {$limit} business {$noun}. {$upgrade}";
    }

    public function atBusinessLimit(): bool
    {
        return ! $this->editingBusinessId
            && Auth::check()
            && ! SubscriptionGating::canAddBusiness(Auth::user());
    }

    public function canEditLogo(): bool
    {
        return SubscriptionGating::canAccessLogo($this->currentBusiness());
    }

    private function currentBusiness(): ?Business
    {
        return $this->editingBusinessId ? Business::find($this->editingBusinessId) : null;
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
    class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm overflow-y-auto"
    style="display: none;"
    @keydown.escape.window="open = false"
>
    <div class="flex min-h-full items-center justify-center p-4">
    <div @click.away="open = false" class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative">
        <button @click="open = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="mb-6">
            <h3 class="font-serif text-2xl font-bold text-slate-900">{{ $editingBusinessId ? 'Edit Your Business' : 'List Your Business' }}</h3>
            <p class="text-slate-500 text-xs mt-1">{{ $editingBusinessId ? 'Keep your listing details current for customers.' : 'Join the directory and connect with customers in your neighborhood.' }}</p>
        </div>

        <form wire:submit="save" class="space-y-4">
            @if ($this->atBusinessLimit())
                <div class="flex items-start gap-3 rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 text-amber-900" role="alert" data-form-locked>
                    <i class="fa-solid fa-lock mt-0.5 text-lg text-amber-600"></i>
                    <div class="text-xs">
                        <p class="text-sm font-bold">This form is locked</p>
                        <p class="mt-0.5" data-business-limit>{{ $this->limitMessage() }}</p>
                        <button type="button" x-data
                                @click="$dispatch('open-subscription-modal', { tier: 'pro', businessId: {{ Auth::user()->businesses()->value('id') ?? 'null' }} })"
                                class="mt-2 rounded-lg bg-amber-600 px-3 py-1.5 font-bold text-white shadow hover:bg-amber-500">
                            <i class="fa-solid fa-bolt mr-1"></i> Upgrade to Pro to unlock
                        </button>
                    </div>
                </div>
            @endif

            {{-- A disabled fieldset locks every input inside it until the owner upgrades. --}}
            <div class="relative">
            @if ($this->atBusinessLimit())
                <div class="absolute inset-0 z-10 flex cursor-not-allowed items-start justify-center rounded-xl bg-slate-200/40 pt-16" data-form-lock-overlay>
                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-lg">
                        <i class="fa-solid fa-lock"></i> Locked until you upgrade to Pro
                    </span>
                </div>
            @endif
            <fieldset @disabled($this->atBusinessLimit()) data-form-fields class="m-0 min-w-0 space-y-4 border-0 p-0 transition-opacity disabled:cursor-not-allowed disabled:opacity-40 disabled:grayscale">
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
                        <option value="Insurance">Insurance</option>
                        <option value="Security & Protection Services">Security & Protection Services</option>
                        <option value="Marketing & Media">Marketing & Media</option>
                        <option value="Education & Instruction">Education & Instruction</option>
                        <option value="Travel & Lodging">Travel & Lodging</option>
                        <option value="Sports & Recreation">Sports & Recreation</option>
                        <option value="Entertainment & Events">Entertainment & Events</option>
                        <option value="Non-Profit & Community">Non-Profit & Community</option>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">City *</label>
                        <input type="text" wire:model="city" placeholder="e.g. Atlanta"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        @error('city') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">State *</label>
                        <select wire:model="state"
                                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="">--</option>
                            @foreach (['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $abbr)
                                <option value="{{ $abbr }}">{{ $abbr }}</option>
                            @endforeach
                        </select>
                        @error('state') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
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
                @if ($this->canEditWebsite())
                    <input type="url" wire:model="website" placeholder="https://example.com"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('website') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                    <label class="block text-xs font-semibold text-slate-700 mt-3 mb-1">Custom link (optional)</label>
                    <div class="flex items-center rounded-xl border border-slate-200 text-sm focus-within:ring-2 focus-within:ring-amber-500">
                        <span class="pl-3.5 pr-1 text-slate-400 whitespace-nowrap">{{ rtrim(url('/b'), '/') }}/</span>
                        <input type="text" wire:model="customSlug" placeholder="my-business" maxlength="60"
                               class="w-full py-2.5 pr-3.5 bg-transparent border-0 text-sm focus:outline-none focus:ring-0">
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Visitors see this link, and it forwards to your website. Use lowercase letters, numbers and dashes.</p>
                    @error('customSlug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                @else
                    <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-lock text-slate-400"></i> Custom link is a Standard-tier feature</span>
                        <button type="button" x-data @click="$dispatch('open-subscription-modal', { tier: 'standard', businessId: {{ $editingBusinessId ?? 'null' }} })" class="font-bold text-amber-600 hover:text-amber-500">Upgrade</button>
                    </div>
                @endif
            </div>

            <div x-data x-show="$wire.category === 'Insurance'" data-insurance-types-field>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Insurance types offered</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Models\Business::INSURANCE_TYPES as $key => $label)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 cursor-pointer hover:border-amber-400">
                            <input type="checkbox" wire:model="insuranceTypes" value="{{ $key }}" class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('insuranceTypes.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-data x-show="$wire.category === 'Dining & Food'" data-free-parking-field>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Free parking</label>
                    <select wire:model="freeParking" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="">Not specified</option>
                        <option value="1">Yes, free parking available</option>
                        <option value="0">No free parking</option>
                    </select>
                    @error('freeParking') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tags</label>
                <input type="text" wire:model="tags" placeholder="e.g. Black-owned, Family-friendly, Local"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <p class="text-xs text-slate-500 mt-1">Separate multiple tags with commas.</p>
                @error('tags') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Business Image</label>
                @if ($this->canEditLogo())
                    <input type="file" wire:model="image" accept="image/*"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm file:mr-4 file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 focus:outline-none">
                    <div wire:loading wire:target="image" class="text-xs text-amber-600 mt-1">Uploading preview...</div>
                    @if ($editingBusinessId)
                        <p class="text-xs text-slate-500 mt-1">Choose a new image only to replace the current one.</p>
                    @endif
                    @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                @else
                    <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-lock text-slate-400"></i> Logo upload is a Standard-tier feature</span>
                        <button type="button" x-data @click="$dispatch('open-subscription-modal', { tier: 'standard', businessId: {{ $editingBusinessId ?? 'null' }} })" class="font-bold text-amber-600 hover:text-amber-500">Upgrade</button>
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Business Description *</label>
                <textarea wire:model="description" rows="3" placeholder="Tell customers about your mission, services, or products..."
                          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            </fieldset>
            </div>

            <div class="pt-2">
                <button type="submit" @disabled($this->atBusinessLimit())
                        class="w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-amber-900/20 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                        wire:loading.attr="disabled" wire:target="save, image"
                >
                    <span wire:loading.remove wire:target="save, image">{{ $this->atBusinessLimit() ? 'Upgrade to Pro to add another business' : ($editingBusinessId ? 'Save Changes' : 'Submit Listing') }}</span>
                    <span wire:loading wire:target="save, image">{{ $editingBusinessId ? 'Saving...' : 'Submitting...' }}</span>
                </button>
            </div>
        </form>
    </div>
    </div>
</div>
