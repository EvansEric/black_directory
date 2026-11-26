@component('layouts.app', ['title' => $business->name.' | Noir Directory'])
    <livewire:navigation />
    <main class="mx-auto w-full max-w-3xl grow px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm text-amber-700">
            <a href="{{ route('home') }}" class="hover:underline">Business directory</a>
            <span aria-hidden="true"> / </span>
            <span class="text-slate-600">{{ $business->name }}</span>
        </nav>
        <article class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            <p class="text-sm font-semibold text-amber-700">{{ $business->category }}</p>
            <h1 class="mt-2 font-serif text-3xl font-bold text-slate-900">{{ $business->name }}</h1>
            @if ($business->isFeatured())
                <span class="mt-3 inline-flex rounded-lg bg-amber-400 px-2 py-1 text-xs font-bold text-slate-950" data-featured-tag>Featured</span>
            @endif
            <p class="mt-3 text-slate-600">{{ $business->location }}</p>

            @if ($business->canAccessLogo() && $business->image)
                <img src="{{ asset('storage/'.$business->image) }}" alt="{{ $business->name }}" class="mt-6 max-h-80 rounded-xl object-contain">
            @endif

            <h2 class="mt-8 text-xl font-bold text-slate-900">About {{ $business->name }}</h2>
            @if ($business->has_rich_description && $business->canAccessDescription())
                <div class="mt-3 text-slate-600 [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">{!! app(\App\Services\BusinessDescription::class)->formatted($business) !!}</div>
            @else
                <p class="mt-3 whitespace-pre-line text-slate-600">{{ app(\App\Services\BusinessDescription::class)->plainText($business) }}</p>
            @endif

            <h2 class="mt-8 text-xl font-bold text-slate-900">Location &amp; contact</h2>
            <address class="mt-3 not-italic text-slate-600">
                {{ collect([$business->streetAddress(), $business->location, $business->zip])->filter()->implode(', ') }}
            </address>
            @if ($business->phone)
                <p class="mt-3"><a href="tel:{{ $business->phone }}" class="font-semibold text-amber-700 hover:underline">{{ $business->phone }}</a></p>
            @endif
            @if ($business->websiteLink())
                <p class="mt-3"><a href="{{ $business->websiteLink() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-700 hover:underline">Visit website</a></p>
            @endif
            @if ($links = $business->availableSocialLinks())
                <ul class="mt-4 flex flex-wrap gap-4">
                    @foreach ($links as $label => $link)
                        <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-700 hover:underline">{{ $label }}</a></li>
                    @endforeach
                </ul>
            @endif

            <h2 class="mt-8 text-xl font-bold text-slate-900">Verified customer reviews</h2>
            <p class="mt-3 text-slate-600">
                {{ $business->reviews_count ? number_format($business->reviews_avg_rating, 1).' out of 5 ('.$business->reviews_count.' verified reviews)' : 'Not yet rated' }}
            </p>
            <a href="{{ route('reviews.index', $business) }}" class="mt-3 inline-block font-semibold text-amber-700 hover:underline">Read reviews for {{ $business->name }}</a>
            <x-review-action :business="$business" />
        </article>
    </main>
    <x-footer />
    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
@endcomponent
