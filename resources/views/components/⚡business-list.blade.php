<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $location = '';
    public string $category = 'All';
    public string $sortVal = 'featured';

    public array $categories = [
        'All',
        'Dining & Food',
        'Beauty & Barber',
        'Professional Services',
        'Retail & Fashion',
        'Health & Wellness',
        'Arts & Creative',
        'Automotive & Transport'

    ];

    #[Computed]
    public function businesses()
    {
        $query = Business::query();

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        if (trim($this->location) !== '') {
            $term = '%' . trim($this->location) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('location', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        }

        return match ($this->sortVal) {
            'rating' => $query->orderByDesc('rating')->get(),
            'name' => $query->orderBy('name')->get(),
            default => $query->orderByDesc('featured')->latest()->get(),
        };
    }

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'location', 'category', 'sortVal']);
    }

    #[On('business-updated')]
    public function refreshBusinesses(): void
    {
        unset($this->businesses);
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

<div id="directory">
    {{-- Search Box --}}
    <div class="bg-white p-3 sm:p-4 rounded-2xl shadow-2xl max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-3 text-slate-800 -mt-10 relative z-10 mb-10">
        <div class="md:col-span-5 relative flex items-center">
            <i class="fa-solid fa-magnifying-glass text-slate-400 absolute left-4 text-sm"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="What are you looking for? (e.g. Coffee, Lawyer, Bakery)"
                class="w-full pl-11 pr-4 py-3 bg-slate-50 md:bg-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
        </div>
        <div class="md:col-span-4 relative flex items-center border-t md:border-t-0 md:border-l border-slate-200 pt-2 md:pt-0">
            <i class="fa-solid fa-location-crosshairs text-slate-400 absolute left-4 text-sm"></i>
            <input type="text" wire:model.live.debounce.300ms="location" placeholder="City or Zip Code"
                class="w-full pl-11 pr-4 py-3 bg-slate-50 md:bg-transparent rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
        </div>
        <div class="md:col-span-3">
            <div class="w-full h-full min-h-[48px] bg-slate-900 text-white font-semibold rounded-xl text-sm flex items-center justify-center space-x-2">
                <i class="fa-solid fa-search"></i>
                <span>Search Directory</span>
            </div>
        </div>
    </div>

    {{-- Category Pills --}}
    <div class="flex flex-wrap items-center justify-center gap-2 mb-10 text-xs sm:text-sm">
        @foreach ($categories as $cat)
            <button
                wire:click="selectCategory('{{ $cat }}')"
                class="px-4 py-2 rounded-full font-medium transition duration-200 border {{ $category === $cat ? 'bg-amber-500 text-slate-900 border-amber-500 font-bold shadow-md' : 'bg-slate-800/80 text-slate-300 border-slate-700/60 hover:bg-slate-700 hover:text-white' }}"
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
            <p class="text-xs text-slate-500 mt-1">Showing {{ $this->businesses->count() }} business{{ $this->businesses->count() === 1 ? '' : 'es' }}</p>
        </div>

        <div class="flex items-center space-x-3">
            <label for="sort-select" class="text-xs font-semibold text-slate-500">Sort By:</label>
            <select id="sort-select" wire:model.live="sortVal" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <option value="featured">Featured First</option>
                <option value="rating">Highest Rated</option>
                <option value="name">Name (A-Z)</option>
            </select>
        </div>
    </div>

    {{-- Saved filter, nothing saved yet --}}
    <div
        x-data
        x-show="$store.favorites.showOnly && $store.favorites.ids.length === 0"
        style="display: none;"
        class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-300"
    >
        <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fa-solid fa-heart"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800">No saved businesses yet</h3>
        <p class="text-sm text-slate-500 max-w-sm mx-auto mt-1 mb-6">Tap the heart icon on any listing to save it here for later.</p>
        <button @click="$store.favorites.reset()" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-medium hover:bg-slate-800">Browse All Businesses</button>
    </div>

    {{-- Directory Grid --}}
    @if ($this->businesses->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" wire:loading.class="opacity-50">
            @foreach ($this->businesses as $business)
                <div
                    wire:key="business-{{ $business->id }}"
                    x-data
                    x-show="!$store.favorites.showOnly || $store.favorites.has({{ $business->id }})"
                    class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group"
                >
                    <div class="relative h-48 overflow-hidden bg-slate-100">
                        <img src="{{ $business->image ?: 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=800&q=80' }}"
                            alt="{{ $business->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

                        <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                            <span class="bg-slate-900/80 backdrop-blur-md text-amber-400 text-[11px] font-semibold px-2.5 py-1 rounded-lg">{{ $business->category }}</span>
                            @if ($business->featured)
                                <span class="bg-amber-500 text-slate-950 text-[11px] font-bold px-2 py-1 rounded-lg flex items-center gap-1">
                                    <i class="fa-solid fa-star text-[9px]"></i> Featured
                                </span>
                            @endif
                        </div>

                        <button
                            @click.stop="$store.favorites.toggle({{ $business->id }})"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/80 hover:bg-white backdrop-blur-md flex items-center justify-center text-slate-700 hover:text-red-500 transition shadow-md"
                            title="Save business"
                        >
                            <i :class="$store.favorites.has({{ $business->id }}) ? 'fa-solid text-red-500' : 'fa-regular'" class="fa-heart text-sm"></i>
                        </button>

                        @auth
                            @if (auth()->id() === $business->user_id)
                                <button
                                    wire:click="deleteBusiness({{ $business->id }})"
                                    wire:confirm="Remove {{ $business->name }} from the directory? This cannot be undone."
                                    class="absolute top-3 right-12 w-9 h-9 rounded-full bg-white/80 hover:bg-red-500 hover:text-white backdrop-blur-md flex items-center justify-center text-slate-700 transition shadow-md"
                                    title="Delete listing"
                                >
                                    <i class="fa-solid fa-trash text-sm"></i>
                                </button>
                            @endif
                        @endauth

                        <div class="absolute bottom-3 left-3 text-white text-xs font-medium flex items-center space-x-1">
                            <i class="fa-solid fa-location-dot text-amber-400"></i>
                            <span>{{ $business->location }}</span>
                        </div>
                    </div>

                    <div class="p-5 flex-grow flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-slate-900 text-lg group-hover:text-amber-600 transition-colors line-clamp-1">{{ $business->name }}</h3>
                                <div class="flex items-center space-x-1 bg-amber-50 text-amber-800 px-2 py-0.5 rounded-md text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-star text-amber-500 text-[10px]"></i>
                                    <span>{{ $business->rating }}</span>
                                </div>
                            </div>

                            <p class="text-xs text-slate-500 line-clamp-2 mb-4 leading-relaxed">{{ $business->description }}</p>

                            @if (! empty($business->tags))
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach ($business->tags as $tag)
                                        <span class="bg-slate-100 text-slate-600 text-[10px] px-2 py-0.5 rounded-md font-medium">#{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Verified Black-Owned</span>
                            @if ($business->phone)
                                <a href="tel:{{ $business->phone }}" class="text-xs font-bold text-amber-600 hover:text-amber-700 flex items-center space-x-1">
                                    <span>Call Business</span>
                                    <i class="fa-solid fa-phone text-[10px]"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
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
