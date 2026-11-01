<?php

use App\Models\Business;
use App\Services\GoogleMapsEmbed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Business $business = null;

    #[On('open-business-details')]
    public function open(int $businessId): void
    {
        $this->business = Business::query()->findOrFail($businessId);

        $this->dispatch('show-business-details');
    }

    public function close(): void
    {
        $this->business = null;
    }

    public function mapUrl(GoogleMapsEmbed $maps): ?string
    {
        return $this->business ? $maps->embedUrl($this->business) : null;
    }

    public function directionsUrl(GoogleMapsEmbed $maps): ?string
    {
        return $this->business ? $maps->directionsUrl($this->business) : null;
    }
}; ?>

<div
    x-data="{ open: false }"
    x-on:show-business-details.window="open = true"
    x-show="open"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm"
    style="display: none;"
    @keydown.escape.window="open = false; $wire.close()"
>
    @if ($business)
        <div
            @click.away="open = false; $wire.close()"
            role="dialog"
            aria-modal="true"
            aria-label="{{ $business->name }} details"
            class="relative w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl"
        >
            <button
                type="button"
                @click="open = false; $wire.close()"
                class="absolute right-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-600 shadow hover:text-slate-900"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="grid grid-cols-1 md:grid-cols-2">
                <div class="flex flex-col gap-4 p-6 sm:p-8">
                    <div>
                        <span class="mb-2 inline-flex rounded-lg bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-amber-400">{{ $business->category }}</span>
                        <h3 class="font-serif text-2xl font-bold text-slate-900">{{ $business->name }}</h3>
                        <div class="mt-1 flex items-center gap-1 text-xs font-bold text-amber-800">
                            <i class="fa-solid fa-star text-[10px] text-amber-500"></i>
                            <span>{{ $business->rating }}</span>
                            @if ($business->reviews_count)
                                <span class="font-medium text-slate-500">({{ $business->reviews_count }} reviews)</span>
                            @endif
                        </div>
                    </div>

                    @if ($business->canAccessDescription() && $business->description)
                        <p class="text-sm leading-relaxed text-slate-600">{{ $business->description }}</p>
                    @endif

                    <ul class="space-y-2 text-sm text-slate-600">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot mt-1 text-amber-600"></i>
                            <span>{{ collect([$business->address, $business->location, $business->zip])->filter()->implode(', ') }}</span>
                        </li>
                        @if ($business->phone)
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-phone text-amber-600"></i>
                                <a href="tel:{{ $business->phone }}" class="hover:underline">{{ $business->phone }}</a>
                            </li>
                        @endif
                        @if ($business->canAccessCustomUrl() && $business->website)
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-globe text-amber-600"></i>
                                <a href="{{ $business->website }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Visit Website</a>
                            </li>
                        @endif
                    </ul>

                    @if (! empty($business->tags))
                        <div class="flex flex-wrap gap-1">
                            @foreach ($business->tags as $tag)
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">#{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-auto flex flex-wrap gap-2 pt-2">
                        <a
                            href="{{ $this->directionsUrl(app(GoogleMapsEmbed::class)) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800"
                        >
                            <i class="fa-solid fa-diamond-turn-right"></i> Open in Google Maps
                        </a>
                        @if ($business->canAccessLeadForms())
                            <button
                                type="button"
                                @click="open = false; $wire.close(); $dispatch('open-lead-modal', { businessId: {{ $business->id }} })"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-slate-950 hover:bg-amber-400"
                            >
                                <i class="fa-solid fa-paper-plane"></i> Contact
                            </button>
                        @endif
                    </div>
                </div>

                <div class="min-h-72 bg-slate-100 md:min-h-full">
                    <iframe
                        title="Map showing {{ $business->name }}"
                        src="{{ $this->mapUrl(app(GoogleMapsEmbed::class)) }}"
                        class="h-full min-h-72 w-full border-0"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </div>
        </div>
    @endif
</div>
