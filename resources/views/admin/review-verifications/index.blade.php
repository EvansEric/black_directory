@component('layouts.app', ['title' => 'Review Verification | Noir Directory'])
    <livewire:navigation />
    <main class="mx-auto w-full max-w-4xl grow px-4 py-10 sm:px-6">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-amber-700 hover:underline">&larr; Admin dashboard</a>
        <h1 class="mt-4 font-serif text-3xl font-bold text-slate-900">Review Verification</h1>
        <p class="mt-2 text-sm text-slate-600">Check that the receipt or booking confirmation identifies the business and matches the reference. Your own requests and requests for businesses you own must be handled by another admin. Evidence is deleted after a decision.</p>
        @if (session('status'))
            <x-toastr-message :message="session('status')" />
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <div class="mt-6 space-y-4">
            @forelse ($verifications as $verification)
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-bold text-slate-900">{{ $verification->business->name }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Submitted {{ $verification->created_at->format('M j, Y g:i A') }}</p>
                    @if ($verification->canBeReviewedBy(auth()->user()))
                        <p class="mt-1 text-sm text-slate-600">Customer: {{ $verification->user->name }} ({{ $verification->user->email }})</p>
                        <p class="mt-1 break-all text-sm text-slate-600">Reference: {{ $verification->transaction_reference }}</p>
                        <a href="{{ route('admin.review-verifications.evidence', $verification) }}" class="mt-3 inline-block text-sm font-bold text-amber-700 hover:underline">Download evidence</a>
                        <form method="POST" action="{{ route('admin.review-verifications.decide', $verification) }}" class="mt-4 flex gap-2">
                            @csrf
                            <button name="decision" value="approved" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve</button>
                            <button name="decision" value="rejected" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white">Reject</button>
                        </form>
                    @else
                        <p class="mt-3 text-sm font-semibold text-amber-700">Another admin must review this request. You submitted it or own the business, so you cannot view its evidence or approve or reject it.</p>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">No pending verification requests.</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $verifications->links() }}</div>
    </main>
    <x-footer />
    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
@endcomponent
