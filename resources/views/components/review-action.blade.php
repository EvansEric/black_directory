@props(['business'])

@if ($business->is_verified)
    @guest
        <button type="button" x-data @click="$dispatch('open-auth-modal')" class="mt-3 block text-sm font-semibold text-amber-700 hover:underline">Log in to write a review</button>
    @else
        @if (auth()->id() !== $business->user_id)
            <a href="{{ route('reviews.create', $business) }}" class="mt-3 block text-sm font-semibold text-amber-700 hover:underline">Write a review / check verification status</a>
        @endif
    @endguest
@endif
