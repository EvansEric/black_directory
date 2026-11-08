@component('layouts.app', ['title' => 'Business verification | Noir Directory'])
    <div class="flex min-h-screen flex-col">
        <livewire:navigation />

        <main class="mx-auto w-full max-w-7xl grow px-4 py-10 sm:px-6 lg:px-8">
            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Admin panel</p>
                <h1 class="mt-2 font-serif text-3xl font-bold text-slate-900">Business verification</h1>
                <p class="mt-2 text-sm text-slate-600">Review submissions before making them visible in the directory.</p>
            </div>

            @if (session('status'))
                <div role="status" class="mb-6 rounded-xl border border-emerald-500 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" action="{{ route('admin.businesses.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label for="status" class="mb-1 block text-xs font-semibold text-slate-700">Verification status</label>
                    <select id="status" name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="verified" @selected($status === 'verified')>Verified</option>
                        <option value="all" @selected($status === 'all')>All businesses</option>
                    </select>
                </div>
                <div class="min-w-52 grow">
                    <label for="search" class="mb-1 block text-xs font-semibold text-slate-700">Search business, location, or owner</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" maxlength="255" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
            </form>

            <div class="space-y-4">
                @forelse ($businesses as $business)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">{{ $business->name }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $business->category }} &middot; {{ $business->location }}</p>
                                <p class="mt-1 text-xs text-slate-500">Submitted {{ $business->created_at?->format('M j, Y') }} &middot; ID {{ $business->id }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $business->is_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $business->is_verified ? 'Verified' : 'Pending' }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="font-semibold text-slate-700">Owner</dt><dd class="break-words text-slate-600">{{ $business->user->name }} ({{ $business->user->email }})</dd></div>
                            <div><dt class="font-semibold text-slate-700">Address</dt><dd class="break-words text-slate-600">{{ collect([$business->address, $business->location, $business->zip])->filter()->implode(', ') }}</dd></div>
                            @if ($business->phone)
                                <div><dt class="font-semibold text-slate-700">Phone</dt><dd class="text-slate-600">{{ $business->phone }}</dd></div>
                            @endif
                            @if ($business->website)
                                <div><dt class="font-semibold text-slate-700">Website</dt><dd class="break-all text-slate-600">{{ $business->website }}</dd></div>
                            @endif
                        </dl>
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-slate-700">Description</h3>
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">{{ app(\App\Services\BusinessDescription::class)->plainText($business) }}</p>
                        </div>

                        <form method="POST" action="{{ route('admin.businesses.verification', $business) }}" class="mt-5">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_verified" value="{{ $business->is_verified ? '0' : '1' }}">
                            <button type="submit" onclick="return confirm('{{ $business->is_verified ? 'Remove verification and hide this business from the public?' : 'Verify this business and make it publicly visible?' }}')" class="rounded-xl px-4 py-2 text-sm font-bold text-white {{ $business->is_verified ? 'bg-red-700 hover:bg-red-600' : 'bg-emerald-700 hover:bg-emerald-600' }}">
                                {{ $business->is_verified ? 'Remove verification' : 'Verify business' }}
                            </button>
                        </form>
                    </article>
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">No businesses match these filters.</p>
                @endforelse
            </div>

            <div class="mt-6">{{ $businesses->links() }}</div>
        </main>

        <x-footer />
        <livewire:auth-modal />
        <livewire:add-business-modal />
        <livewire:subscription-modal />
    </div>
@endcomponent
