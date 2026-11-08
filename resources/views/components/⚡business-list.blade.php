<?php

use App\Models\Business;
use App\Services\Geocoder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $location = '';
    #[Url]
    public string $category = 'All';
    #[Url]
    public string $sortVal = 'featured';
    #[Url]
    public string $parking = 'any';

    #[Url(as: 'miles')]
    public string $distance = 'any';

    // The visitor's position, supplied by the browser; never stored.
    public ?float $userLat = null;
    public ?float $userLng = null;

    public const DISTANCES = [5, 10, 25, 50, 100];

    #[Url(as: 'insurance')]
    public string $insuranceType = 'any';
    #[Url(as: 'saved')]
    public bool $savedOnly = false;

    public array $categories = [
        'All',
        'Dining & Food',
        'Beauty & Barber',
        'Professional Services',
        'Retail & Fashion',
        'Health & Wellness',
        'Arts & Creative',
        'Automotive & Transport',
        'Home & Trades',
        'Real Estate & Property',
        'Pet Care & Services',
        'Cleaning & Maintenance',
        'IT & Tech Solutions',
        'Legal & Financial',
        'Insurance',
        'Security & Protection Services',
        'Marketing & Media',
        'Education & Instruction',
        'Travel & Lodging',
        'Sports & Recreation',
        'Entertainment & Events',
        'Non-Profit & Community',

    ];

    #[Computed]
    public function businesses()
    {
        $query = Business::query()->with('user.businesses')->visibleTo(Auth::user());

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        if ($this->savedOnly && Auth::check()) {
            $query->whereIn('businesses.id', Auth::user()->savedBusinesses()->select('businesses.id'));
        }

        if ($this->category === 'Insurance' && array_key_exists($this->insuranceType, Business::INSURANCE_TYPES)) {
            $query->offeringInsurance($this->insuranceType);
        }

        if ($this->category === 'Dining & Food' && in_array($this->parking, ['yes', 'no'], true)) {
            $query->withFreeParking($this->parking === 'yes');
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        $center = $this->searchCenter();

        if (trim($this->location) !== '' && ! $this->zipIsCenter()) {
            $raw = trim($this->location);
            $term = '%' . $raw . '%';
            $city = app(Geocoder::class)->cityForZip($raw);

            $query->where(function ($q) use ($term, $raw, $city) {
                $q->where('location', 'like', $term)
                    ->orWhere('address', 'like', $term)
                    ->orWhere('zip', $raw);

                if ($city) {
                    $q->orWhere('location', 'like', '%' . $city . '%');
                }
            });
        }

        if ($center) {
            $query->nearby($center['latitude'], $center['longitude'], $this->radius());
        }

        if ($center && ($this->sortVal === 'nearest' || ($this->zipIsCenter() && $this->sortVal === 'featured'))) {
            $query->orderedByDistance();

            return $query->paginate(6);
        }

        return $query->orderedByTier($this->sortVal)->paginate(6);
    }

    /** A typed zip code is the distance origin when a radius is chosen. */
    private function zipIsCenter(): bool
    {
        return $this->zipCoordinates() !== null;
    }

    /** @return array{latitude: float, longitude: float}|null */
    private function zipCoordinates(): ?array
    {
        $zip = trim($this->location);

        if ($this->radius() === null || ! preg_match('/^\d{5}$/', $zip)) {
            return null;
        }

        return app(Geocoder::class)->coordinatesForZip($zip);
    }

    /** @return array{latitude: float, longitude: float}|null */
    private function searchCenter(): ?array
    {
        if ($zip = $this->zipCoordinates()) {
            return $zip;
        }

        return $this->hasLocation() ? ['latitude' => $this->userLat, 'longitude' => $this->userLng] : null;
    }

    public function hasLocation(): bool
    {
        return $this->userLat !== null && $this->userLng !== null;
    }

    private function radius(): ?int
    {
        return in_array((int) $this->distance, self::DISTANCES, true) ? (int) $this->distance : null;
    }

    /** Called by the browser once the visitor allows location access. */
    public function setLocation(float $lat, float $lng): void
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $this->userLat = $lat;
        $this->userLng = $lng;

        // Show the closest businesses first unless the visitor picked another order.
        if ($this->sortVal === 'featured') {
            $this->sortVal = 'nearest';
        }

        $this->resetPage();
    }

    public function clearLocation(): void
    {
        $this->userLat = null;
        $this->userLng = null;
        $this->distance = 'any';

        if ($this->sortVal === 'nearest') {
            $this->sortVal = 'featured';
        }

        $this->resetPage();
    }

    /**
     * IDs of the businesses the current user has saved (empty for guests).
     *
     * @return array<int, int>
     */
    #[Computed]
    public function savedIds(): array
    {
        return Auth::check()
            ? Auth::user()->savedBusinesses()->pluck('businesses.id')->all()
            : [];
    }

    public function toggleSaved(int $businessId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal', tab: 'login');

            return;
        }

        Auth::user()->savedBusinesses()->toggle(Business::query()->findOrFail($businessId)->getKey());

        unset($this->savedIds, $this->businesses);
        $this->dispatch('favorites-updated');
    }

    #[On('toggle-saved-filter')]
    public function toggleSavedFilter(): void
    {
        $this->savedOnly = Auth::check() && ! $this->savedOnly;
        $this->resetPage();
    }

    public function showAllBusinesses(): void
    {
        $this->savedOnly = false;
        $this->resetPage();
        $this->dispatch('saved-filter-changed', active: false);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'location', 'category', 'sortVal', 'parking', 'insuranceType', 'distance'], true)) {
            $this->resetPage();
        }
    }

    public function selectCategory(string $category): void
    {
        $this->category = $category;
        $this->parking = 'any';
        $this->insuranceType = 'any';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'location', 'category', 'sortVal', 'parking', 'insuranceType', 'distance', 'savedOnly']);
        $this->resetPage();
        $this->dispatch('saved-filter-changed', active: false);
    }

    #[On('business-updated')]
    public function refreshBusinesses(): void
    {
        unset($this->businesses);
    }

    public function editBusiness(int $id): void
    {
        $business = Business::query()->findOrFail($id);

        if (! Auth::check() || Auth::id() !== $business->user_id) {
            abort(403, 'You are not authorized to edit this business.');
        }

        $this->dispatch('edit-business', businessId: $business->id);
    }

    public function deleteBusiness(int $id): void
    {
        $business = Business::query()->findOrFail($id);

        if (! Auth::check() || Auth::id() !== $business->user_id) {
            abort(403, 'You are not authorized to delete this business.');
        }

        $business->delete();

        $this->dispatch('business-updated');
    }
}; ?>

<div id="directory" x-data="directorySearch">
    {{-- Search Box --}}
    <div class="bg-white p-3 sm:p-4 rounded-2xl shadow-2xl max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-3 text-slate-800 -mt-10 relative z-10 mb-10">
        <div class="md:col-span-5 relative flex items-center">
            <i class="fa-solid fa-magnifying-glass text-slate-400 absolute left-4 text-sm"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="What are you looking for? (e.g. Coffee, Lawyer, Bakery)"
                class="w-full pl-11 pr-4 py-3 bg-slate-50 md:bg-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
        </div>
        <div class="md:col-span-4 relative flex items-center border-t md:border-t-0 md:border-l border-slate-200 pt-2 md:pt-0">
            <button
                type="button"
                @click="askLocation(true)"
                class="absolute left-4 text-slate-400 hover:text-amber-600 transition-colors focus:outline-none"
                :title="locating ? 'Detecting location...' : 'Use my current location'"
            >
                <i :class="locating ? 'fa-solid fa-spinner fa-spin text-amber-500' : 'fa-solid fa-location-crosshairs'" class="text-sm"></i>
            </button>
            <input type="text" wire:model.live.debounce.300ms="location" placeholder="City or Zip Code"
                class="w-full pl-11 pr-4 py-3 bg-slate-50 md:bg-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
        </div>
        <div class="md:col-span-3">
            <div class="w-full h-full min-h-12 bg-slate-900 text-white font-semibold rounded-xl text-sm flex items-center justify-center space-x-2">
                <i class="fa-solid fa-search"></i>
                <span>Search Directory</span>
            </div>
        </div>
    </div>

    {{-- Category Pills --}}
    <div class="flex flex-wrap items-center justify-center gap-1.5 mb-6 sm:mb-8 text-[11px] sm:text-xs">
        @foreach ($categories as $cat)
            <button
                wire:click="selectCategory('{{ $cat }}')"
                class="px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-full font-medium whitespace-nowrap transition duration-200 border {{ $category === $cat ? 'bg-amber-500 text-slate-900 border-amber-500 font-bold shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-amber-50 hover:text-slate-900 hover:border-amber-200' }}"
            >
                {{ $cat }}
            </button>
        @endforeach
    </div>

    {{-- Filter Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 pb-4 border-b border-slate-200">
        <div>
            <h2 class="text-xl font-bold text-slate-900">
                @if($category !== 'All') {{ $category }} Businesses @else All Local Businesses @endif
            </h2>
            <p class="text-xs text-slate-500 mt-1">Showing {{ $this->businesses->count() }} of {{ $this->businesses->total() }} business{{ $this->businesses->total() === 1 ? '' : 'es' }}</p>
        </div>

        <div class="flex items-center space-x-3">
            @if ($category === 'Dining & Food')
                <label for="parking-select" class="text-xs font-semibold text-slate-500"><i class="fa-solid fa-square-parking mr-1 text-amber-600"></i>Parking:</label>
                <select id="parking-select" wire:model.live="parking" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <option value="any">Any</option>
                    <option value="yes">Free parking</option>
                    <option value="no">No free parking</option>
                </select>
            @endif
            @if ($category === 'Insurance')
                <label for="insurance-type-select" class="text-xs font-semibold text-slate-500"><i class="fa-solid fa-shield-halved mr-1 text-amber-600"></i>Type:</label>
                <select id="insurance-type-select" wire:model.live="insuranceType" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <option value="any">All types</option>
                    @foreach (Business::INSURANCE_TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
            @if ($category !== 'All' || trim($search) !== '' || trim($location) !== '')
                <button type="button" wire:click="resetFilters"
                        class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-amber-50 hover:text-slate-900 transition">
                    <i class="fa-solid fa-xmark mr-1"></i> Clear Filters
                </button>
            @endif
            <div x-data="nearMe" class="flex items-center space-x-2" data-near-me>
                <label for="distance-select" class="text-xs font-semibold text-slate-500"><i class="fa-solid fa-location-crosshairs mr-1 text-amber-600"></i>Distance:</label>
                <select id="distance-select" wire:model.live="distance" @change="if ($event.target.value !== 'any' && $wire.userLat === null && ! /^\d{5}$/.test($wire.location.trim())) locate()"
                        class="bg-white border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <option value="any">Any distance</option>
                    @foreach ($this::DISTANCES as $miles)
                        <option value="{{ $miles }}">Within {{ $miles }} miles</option>
                    @endforeach
                </select>
                @if ($this->hasLocation())
                    <button type="button" wire:click="clearLocation" class="text-xs font-semibold text-slate-500 hover:text-slate-800" title="Stop using my location" data-clear-location>
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @else
                    <button type="button" @click="locate()" class="px-3 py-2 rounded-xl text-xs font-semibold border border-slate-200 text-slate-700 hover:bg-amber-50 transition" data-use-location>
                        <i class="fa-solid fa-location-dot mr-1 text-amber-600"></i><span x-text="busy ? 'Locating…' : 'Use my location'"></span>
                    </button>
                @endif
                <span x-show="error" x-text="error" x-cloak class="text-[11px] text-red-600"></span>
            </div>
            <label for="sort-select" class="text-xs font-semibold text-slate-500">Sort By:</label>
            <select id="sort-select" wire:model.live="sortVal" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <option value="featured">Featured First</option>
                @if ($this->hasLocation())
                    <option value="nearest">Nearest First</option>
                @endif
                <option value="rating">Highest Rated</option>
                <option value="name">Name (A-Z)</option>
            </select>
        </div>
    </div>

    {{-- Saved filter, nothing saved yet --}}
    @if ($savedOnly && $this->businesses->count() === 0)
        <div data-saved-empty class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-300">
            <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-heart"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">No saved businesses yet</h3>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mt-1 mb-6">Tap the heart icon on any listing to save it here for later.</p>
            <button type="button" wire:click="showAllBusinesses" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-medium hover:bg-slate-800">Browse All Businesses</button>
        </div>
    @endif

    {{-- Directory Grid --}}
    @if ($this->businesses->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" wire:loading.class="opacity-50">
            @foreach ($this->businesses as $business)
                <div
                    wire:key="business-{{ $business->id }}"
                    x-data
                    @click="if (! $event.target.closest('a, button')) $dispatch('open-business-details', { businessId: {{ $business->id }} })"
                    class="cursor-pointer bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group"
                >
                    <div class="relative h-40 overflow-hidden bg-slate-100">
                        <img src="{{ ($business->canAccessLogo() && $business->image) ? asset('storage/' . $business->image) : 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=800&q=80' }}"
                            alt="{{ $business->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-linear-to-t from-slate-900/60 via-transparent to-transparent"></div>

                        <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 {{ auth()->check() && auth()->id() === $business->user_id ? 'max-w-[calc(100%-9.5rem)]' : 'max-w-[calc(100%-4.5rem)]' }}">
                            <span class="max-w-full truncate bg-slate-900/80 backdrop-blur-md text-amber-400 text-[11px] font-semibold px-2.5 py-1 rounded-lg" title="{{ $business->category }}">{{ $business->category }}</span>
                            @if ($business->canAccessSpotlight())
                                <span class="bg-amber-500 text-slate-950 text-[11px] font-extrabold px-2 py-1 rounded-lg flex items-center gap-1 shadow">
                                    <i class="fa-solid fa-crown text-[9px]"></i> Spotlight
                                </span>
                            @elseif ($business->isFeatured())
                                <span class="bg-amber-400 text-slate-950 text-[11px] font-bold px-2 py-1 rounded-lg flex items-center gap-1" data-featured-tag>
                                    <i class="fa-solid fa-star text-[9px]"></i> Featured
                                </span>
                            @elseif ($business->subscription_tier === 'standard')
                                <span class="bg-slate-700 text-white text-[11px] font-semibold px-2 py-1 rounded-lg flex items-center gap-1">
                                    <i class="fa-solid fa-check text-[9px]"></i> Standard
                                </span>
                            @endif
                        </div>

                        <button
                            @click.stop="$wire.toggleSaved({{ $business->id }})"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/80 hover:bg-white backdrop-blur-md flex items-center justify-center text-slate-700 hover:text-red-500 transition shadow-md"
                            title="{{ auth()->check() ? 'Save business' : 'Log in to save businesses' }}"
                        >
                            @if (in_array($business->id, $this->savedIds, true))
                                <i class="fa-solid fa-heart text-sm text-red-500"></i>
                            @else
                                <i class="fa-regular fa-heart text-sm"></i>
                            @endif
                        </button>

                        @unless ($business->is_verified)
                            <span class="absolute bottom-3 left-3 rounded-full bg-amber-500 px-2.5 py-1 text-xs font-semibold text-slate-950 shadow-md">Pending verification</span>
                        @endunless

                        @auth
                            @if (auth()->id() === $business->user_id)
                                <button
                                    wire:click="editBusiness({{ $business->id }})"
                                    class="absolute top-3 right-12 w-9 h-9 rounded-full bg-white/80 hover:bg-amber-500 hover:text-slate-950 backdrop-blur-md flex items-center justify-center text-slate-700 transition shadow-md"
                                    title="Edit listing"
                                >
                                    <i class="fa-solid fa-pen text-sm"></i>
                                </button>
                                <button
                                    wire:click="deleteBusiness({{ $business->id }})"
                                    wire:confirm="Remove {{ $business->name }} from the directory? This cannot be undone."
                                    class="absolute top-3 right-21 w-9 h-9 rounded-full bg-white/80 hover:bg-red-500 hover:text-white backdrop-blur-md flex items-center justify-center text-slate-700 transition shadow-md"
                                    title="Delete listing"
                                >
                                    <i class="fa-solid fa-trash text-sm"></i>
                                </button>
                                <button
                                    @click="$dispatch('open-subscription-modal', { businessId: {{ $business->id }} })"
                                    class="absolute top-3 right-30 w-9 h-9 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 backdrop-blur-md flex items-center justify-center font-bold transition shadow-md"
                                    title="Upgrade subscription"
                                >
                                    <i class="fa-solid fa-bolt text-sm"></i>
                                </button>
                            @endif
                        @endauth

                        <div class="absolute bottom-3 left-3 text-white text-xs font-medium flex items-center space-x-1">
                            <i class="fa-solid fa-location-dot text-amber-400"></i>
                            <span>{{ $business->location }}</span>
                        </div>
                    </div>

                    <div class="p-4 grow flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-slate-900 text-base group-hover:text-amber-600 transition-colors line-clamp-1">{{ $business->name }}</h3>
                                <div class="flex items-center space-x-1 bg-amber-50 text-amber-800 px-2 py-0.5 rounded-md text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-star text-amber-500 text-[10px]"></i>
                                    <span>{{ $business->rating }}</span>
                                </div>
                            </div>

                            @if ($business->distance_miles !== null)
                                <span class="inline-flex items-center gap-1.5 mb-3 mr-1 text-[11px] font-semibold rounded-md px-2 py-0.5 bg-amber-50 text-amber-700" data-distance-badge>
                                    <i class="fa-solid fa-location-dot"></i>
                                    {{ $business->distance_miles < 0.1 ? 'Less than 0.1' : number_format($business->distance_miles, 1) }} mi away
                                </span>
                            @endif

                            @if ($business->free_parking !== null && $business->category === 'Dining & Food')
                                <span class="inline-flex items-center gap-1.5 mb-3 text-[11px] font-semibold rounded-md px-2 py-0.5 {{ $business->free_parking ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}" data-parking-badge>
                                    <i class="fa-solid fa-square-parking"></i>
                                    {{ $business->free_parking ? 'Free parking' : 'No free parking' }}
                                </span>
                            @endif

                            @if ($business->description)
                                <p class="text-xs text-slate-500 line-clamp-2 mb-4 leading-relaxed">{{ app(\App\Services\BusinessDescription::class)->plainText($business) }}</p>
                            @endif

                            @if ($business->websiteLink())
                                <a
                                    href="{{ $business->websiteLink() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 text-xs text-amber-600 font-semibold hover:underline mb-3"
                                >
                                    <i class="fa-solid fa-globe"></i>
                                    <span>Visit Website</span>
                                </a>
                            @endif

                            @if ($business->address)
                                <a
                                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode(trim($business->address . ', ' . $business->location . ' ' . $business->zip)) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    @click.stop
                                    class="flex items-start gap-1.5 text-xs text-slate-500 hover:text-amber-600 mb-4 transition-colors"
                                    title="Open in Google Maps"
                                >
                                    <i class="fa-solid fa-location-dot text-amber-600 mt-0.5"></i>
                                    <span class="underline decoration-dotted underline-offset-2">{{ $business->address }}</span>
                                </a>
                            @endif

                            @if (! empty($business->tags))
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach ($business->tags as $tag)
                                        <span class="bg-slate-100 text-slate-600 text-[10px] px-2 py-0.5 rounded-md font-medium">#{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-400">Verified Black-Owned</span>
                            <div class="flex items-center space-x-2">
                                @if ($business->canAccessLeadForms())
                                    <button
                                        @click="$dispatch('open-lead-modal', { businessId: {{ $business->id }} })"
                                        class="px-2.5 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg text-xs transition flex items-center gap-1 shadow-sm"
                                    >
                                        <i class="fa-solid fa-paper-plane text-[10px]"></i>
                                        <span>Contact</span>
                                    </button>
                                @endif
                                @if ($business->phone)
                                    <a href="tel:{{ $business->phone }}" class="text-xs font-bold text-amber-600 hover:text-amber-700 flex items-center space-x-1">
                                        <span>Call</span>
                                        <i class="fa-solid fa-phone text-[10px]"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($this->businesses->hasPages())
            <nav class="mt-6 flex justify-center" aria-label="Business directory pagination">
                <div class="inline-flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($this->businesses->onFirstPage())
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Previous page"
                    >
                        <i class="fa-solid fa-chevron-left mr-2 text-xs" aria-hidden="true"></i>
                        Previous
                    </button>

                    <span class="text-sm text-slate-600">
                        Page <span class="font-semibold text-slate-900">{{ $this->businesses->currentPage() }}</span>
                        of <span class="font-semibold text-slate-900">{{ $this->businesses->lastPage() }}</span>
                    </span>

                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled($this->businesses->currentPage() >= $this->businesses->lastPage())
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Next page"
                    >
                        Next
                        <i class="fa-solid fa-chevron-right ml-2 text-xs" aria-hidden="true"></i>
                    </button>
                </div>
            </nav>
        @endif
    @else
        <div class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-300">
            <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-store"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">No businesses found</h3>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mt-1 mb-6">We couldn't find any listings matching your search criteria. Try adjusting your filters or location.</p>
            <button wire:click="resetFilters" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-medium hover:bg-slate-800">Clear All Filters</button>
        </div>
    @endif
</div>
