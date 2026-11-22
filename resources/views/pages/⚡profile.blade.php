<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Business Profile | Noir Directory')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(! Auth::user()->is_admin, 403);
    }

    #[Computed]
    public function businesses()
    {
        return Business::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->get();
    }

    #[On('business-updated')]
    public function refreshBusinesses(): void
    {
        unset($this->businesses);
    }
};
?>

<div class="flex min-h-screen flex-col bg-slate-50">
    <livewire:navigation />

    @if (session('status'))
        <x-toastr-message :message="session('status')" />
    @endif

    <main class="mx-auto w-full max-w-5xl grow px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Your profile</p>
                <h1 class="mt-2 font-serif text-3xl font-bold text-slate-900">Manage your businesses</h1>
                <p class="mt-2 text-sm text-slate-600">Add a listing or update the details customers see in the directory.</p>
            </div>
            <button
                type="button"
                x-data
                @click="$dispatch('open-add-modal')"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-amber-900/20 transition hover:bg-amber-500"
            >
                <i class="fa-solid fa-plus"></i>
                Add Business
            </button>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="font-bold text-slate-900">Your business listings</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $this->businesses->count() }} {{ Str::plural('listing', $this->businesses->count()) }}</p>
                </div>
            </div>

            @forelse ($this->businesses as $business)
                <article
                    wire:key="profile-business-{{ $business->id }}"
                    class="flex flex-col gap-4 border-b border-slate-100 py-5 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-amber-50 font-bold text-amber-700">
                            @if ($business->canAccessLogo() && $business->image)
                                <img src="{{ asset('storage/'.$business->image) }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(substr($business->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-bold text-slate-900">{{ $business->name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $business->is_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                    {{ $business->is_verified ? 'Verified' : 'Pending verification' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $business->category }} &middot; {{ $business->location }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{ \App\Services\SubscriptionGating::TIERS[\App\Services\SubscriptionGating::getEffectiveTier($business)]['name'] }} plan
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-data
                        @click="$dispatch('edit-business', { businessId: {{ $business->id }} })"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-amber-400 hover:bg-amber-50 hover:text-amber-800"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit Business
                    </button>
                </article>
            @empty
                <div class="py-12 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-store text-xl"></i>
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900">No businesses yet</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Add your first business listing. It will start on the Free plan and remain pending until verification.</p>
                    <button
                        type="button"
                        x-data
                        @click="$dispatch('open-add-modal')"
                        class="mt-5 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-amber-500"
                    >
                        Add Your Business
                    </button>
                </div>
            @endforelse
        </section>
    </main>

    <x-footer />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
    <livewire:auth-modal />
</div>
