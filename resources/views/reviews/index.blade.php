@component('layouts.app', ['title' => 'Verified reviews | Noir Directory'])
    <livewire:navigation />
    <main class="mx-auto w-full max-w-3xl grow px-4 py-10 sm:px-6">
        <a href="{{ route('home') }}" class="text-sm font-semibold text-amber-700 hover:underline">&larr; Directory</a>
        <h1 class="mt-4 font-serif text-3xl font-bold text-slate-900">Verified reviews for {{ $business->name }}</h1>
        <a href="{{ route('businesses.show', $business) }}" class="mt-3 inline-block text-sm font-semibold text-amber-700 hover:underline">View business profile</a>
        <x-review-action :business="$business" />
        <div class="mt-6 space-y-4">
            @forelse ($reviews as $review)
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <p class="font-bold text-slate-900">{{ $review->user->name }} &middot; {{ $review->rating }}/5</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $review->created_at->format('M j, Y') }} &middot; Verified customer</p>
                    @if ($review->comment)
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $review->comment }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-600">No verified reviews yet.</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $reviews->links() }}</div>
    </main>
    <x-footer />
    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
@endcomponent
