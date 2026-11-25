@component('layouts.app', ['title' => 'Review '.$business->name.' | Noir Directory'])
    <livewire:navigation />
    <main class="mx-auto w-full max-w-3xl grow px-4 py-10 sm:px-6">
        <a href="{{ route('businesses.show', $business) }}" class="text-sm font-semibold text-amber-700 hover:underline">&larr; {{ $business->name }}</a>
        <h1 class="mt-4 font-serif text-3xl font-bold text-slate-900">Review {{ $business->name }}</h1>
        <p class="mt-2 text-sm text-slate-600">An admin must verify your purchase or booking before you can publish a review. Each verified transaction permits one review.</p>

        @if (session('status'))
            <x-toastr-message :message="session('status')" />
        @endif

        @if (! auth()->user()->hasVerifiedEmail())
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="font-bold text-slate-900">Verify your email first</h2>
                <p class="mt-2 text-sm text-slate-600">We will send a verification link to your account email address. After verifying, return to this page.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
                    @csrf
                    <button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white">Send verification link</button>
                </form>
            </section>
        @else
            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Submit purchase evidence</h2>
                <p class="mt-2 text-sm text-slate-600">Upload a receipt or booking confirmation showing the business and transaction reference. Remove payment card and other unnecessary personal details first. Evidence is private and deleted when an admin decides your request. PDF, JPEG or PNG; maximum 5 MB.</p>
                <form method="POST" action="{{ route('reviews.verify', $business) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="transaction_reference" class="block text-sm font-semibold text-slate-700">Receipt or booking reference</label>
                        <input id="transaction_reference" name="transaction_reference" value="{{ old('transaction_reference') }}" required maxlength="120" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="evidence" class="block text-sm font-semibold text-slate-700">Evidence file</label>
                        <input id="evidence" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-1 w-full text-sm text-slate-700">
                    </div>
                    <button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white">Submit for verification</button>
                </form>
            </section>

            <section class="mt-6 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Your verification requests</h2>
                @forelse ($verifications as $verification)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6">
                        <p class="text-sm font-semibold text-slate-900">Submitted {{ $verification->created_at->format('M j, Y') }} &middot; {{ ucfirst($verification->status) }}</p>
                        @if ($verification->status === 'pending')
                            <p class="mt-2 text-sm text-slate-600">Awaiting an admin decision. Return here to check the status.</p>
                        @elseif ($verification->status === 'rejected')
                            <p class="mt-2 text-sm text-slate-600">We could not verify this evidence. You may submit another request.</p>
                        @elseif ($verification->status === 'approved')
                            <form method="POST" action="{{ route('reviews.store', $business) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="verification_id" value="{{ $verification->id }}">
                                <div>
                                    <label for="rating-{{ $verification->id }}" class="block text-sm font-semibold text-slate-700">Rating</label>
                                    <select id="rating-{{ $verification->id }}" name="rating" required class="mt-1 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Choose a rating</option>
                                        @foreach (range(1, 5) as $rating)
                                            <option value="{{ $rating }}" @selected((int) old('verification_id') === $verification->id && (int) old('rating') === $rating)>{{ $rating }} / 5</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="comment-{{ $verification->id }}" class="block text-sm font-semibold text-slate-700">Your review (optional)</label>
                                    <textarea id="comment-{{ $verification->id }}" name="comment" maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ (int) old('verification_id') === $verification->id ? old('comment') : '' }}</textarea>
                                </div>
                                <button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white">Publish review</button>
                            </form>
                        @elseif ($verification->status === 'consumed')
                            <p class="mt-2 text-sm text-slate-600">Review published: {{ $verification->review?->rating }}/5</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-600">You have not submitted any requests for this business.</p>
                @endforelse
                {{ $verifications->links() }}
            </section>
        @endif
    </main>
    <x-footer />
    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
@endcomponent
