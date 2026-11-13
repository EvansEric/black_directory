<?php

use App\Models\User;
use App\Models\Business;
use App\Services\SubscriptionGating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin Dashboard | Noir Directory')]
class extends Component
{
    use WithPagination;

    public string $section = 'analytics';
    public string $timeFilter = 'day';
    public string $businessStatus = 'pending';
    public string $businessSearch = '';

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->is_admin, 403);
    }

    public function setSection(string $section): void
    {
        $this->section = $section;
        $this->resetPage();
    }

    public function setTimeFilter(string $filter): void
    {
        $this->timeFilter = $filter;
    }

    public function updated($name)
    {
        if (in_array($name, ['businessStatus', 'businessSearch'])) {
            $this->resetPage();
        }
    }

    public function getTotalUsers(): int
    {
        return User::count();
    }

    public function getUsersByPlan(): array
    {
        return [
            'free' => User::whereDoesntHave('businesses', function ($query) {
                $query->whereIn('subscription_tier', [SubscriptionGating::TIER_STANDARD, SubscriptionGating::TIER_PRO, SubscriptionGating::TIER_SPOTLIGHT]);
            })->count(),
            'standard' => User::whereHas('businesses', function ($query) {
                $query->where('subscription_tier', SubscriptionGating::TIER_STANDARD)->where(function ($q) {
                    $q->where('subscription_status', 'active')->orWhere('subscription_status', 'trialing');
                });
            })->distinct()->count(),
            'pro' => User::whereHas('businesses', function ($query) {
                $query->where('subscription_tier', SubscriptionGating::TIER_PRO)->where(function ($q) {
                    $q->where('subscription_status', 'active')->orWhere('subscription_status', 'trialing');
                });
            })->distinct()->count(),
            'spotlight' => User::whereHas('businesses', function ($query) {
                $query->where('subscription_tier', SubscriptionGating::TIER_SPOTLIGHT)->where(function ($q) {
                    $q->where('subscription_status', 'active')->orWhere('subscription_status', 'trialing');
                });
            })->distinct()->count(),
        ];
    }

    public function getNewUsersTrend(): array
    {
        $query = User::query();
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite
            $results = match ($this->timeFilter) {
                'day' => $query->where('created_at', '>=', now()->startOfDay())
                    ->selectRaw('strftime("%H:00", created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('strftime("%H:00", created_at)'))
                    ->orderBy('period')
                    ->get(),
                'week' => $query->where('created_at', '>=', now()->startOfWeek())
                    ->selectRaw('strftime("%A", created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('strftime("%A", created_at)'))
                    ->orderBy(DB::raw('CASE strftime("%w", created_at) WHEN "0" THEN 1 WHEN "1" THEN 2 WHEN "2" THEN 3 WHEN "3" THEN 4 WHEN "4" THEN 5 WHEN "5" THEN 6 ELSE 7 END'))
                    ->get(),
                'year' => $query->where('created_at', '>=', now()->startOfYear())
                    ->selectRaw('strftime("%B", created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('strftime("%B", created_at)'))
                    ->orderBy(DB::raw('CASE strftime("%m", created_at) WHEN "01" THEN 1 WHEN "02" THEN 2 WHEN "03" THEN 3 WHEN "04" THEN 4 WHEN "05" THEN 5 WHEN "06" THEN 6 WHEN "07" THEN 7 WHEN "08" THEN 8 WHEN "09" THEN 9 WHEN "10" THEN 10 WHEN "11" THEN 11 ELSE 12 END'))
                    ->get(),
                default => $query->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('strftime("%d", created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('strftime("%d", created_at)'))
                    ->orderBy('period')
                    ->get(),
            };
        } else {
            // MySQL: include sort expression in GROUP BY for strict mode compliance
            $results = match ($this->timeFilter) {
                'day' => $query->where('created_at', '>=', now()->startOfDay())
                    ->selectRaw('DATE_FORMAT(created_at, "%H:00") as period, COUNT(*) as count')
                    ->groupBy(DB::raw('DATE_FORMAT(created_at, "%H:00")'))
                    ->orderBy('period')
                    ->get(),
                'week' => $query->where('created_at', '>=', now()->startOfWeek())
                    ->selectRaw('DAYNAME(created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('DAYNAME(created_at)'), DB::raw('DAYOFWEEK(created_at)'))
                    ->orderBy(DB::raw('DAYOFWEEK(created_at)'))
                    ->get(),
                'year' => $query->where('created_at', '>=', now()->startOfYear())
                    ->selectRaw('MONTHNAME(created_at) as period, COUNT(*) as count')
                    ->groupBy(DB::raw('MONTHNAME(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('MONTH(created_at)'))
                    ->get(),
                default => $query->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('DATE_FORMAT(created_at, "%d") as period, COUNT(*) as count')
                    ->groupBy(DB::raw('DATE_FORMAT(created_at, "%d")'))
                    ->orderBy('period')
                    ->get(),
            };
        }

        // Convert to array with period => count mapping
        $data = [];
        foreach ($results as $row) {
            $data[$row->period] = $row->count;
        }

        return $data;
    }

    #[Computed]
    public function businesses()
    {
        $query = Business::query();

        if ($this->businessStatus !== 'all') {
            $query->where('is_verified', $this->businessStatus === 'verified');
        }

        if ($this->businessSearch) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->businessSearch}%")
                    ->orWhere('location', 'like', "%{$this->businessSearch}%")
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('email', 'like', "%{$this->businessSearch}%")
                            ->orWhere('name', 'like', "%{$this->businessSearch}%");
                    });
            });
        }

        return $query->latest()->paginate(10);
    }

    public function filterBusinesses(): void
    {
        // This method is called by wire:submit on the form
        // Livewire will automatically re-compute businesses() after property updates
    }

    public function verifyBusiness(int $businessId): void
    {
        $business = Business::findOrFail($businessId);
        $business->update(['is_verified' => true]);
        session()->flash('status', 'Business verified successfully.');
    }

    public function unverifyBusiness(int $businessId): void
    {
        $business = Business::findOrFail($businessId);
        $business->update(['is_verified' => false]);
        session()->flash('status', 'Business verification removed.');
    }
}; ?>

<div class="min-h-screen bg-slate-50">
    <livewire:navigation />

    <div class="flex gap-0">
        {{-- Sidebar --}}
        <aside class="hidden w-64 border-r border-slate-200 bg-white lg:block">
            <nav class="space-y-2 p-6">
                <p class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-400">Admin Menu</p>

                <button
                    wire:click="setSection('analytics')"
                    class="w-full rounded-xl px-4 py-2.5 text-left text-sm font-medium transition {{ $section === 'analytics' ? 'bg-amber-100 text-amber-900' : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <i class="fa-solid fa-chart-line mr-2"></i> Analytics
                </button>

                <button
                    wire:click="setSection('businesses')"
                    class="w-full rounded-xl px-4 py-2.5 text-left text-sm font-medium transition {{ $section === 'businesses' ? 'bg-amber-100 text-amber-900' : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <i class="fa-solid fa-shield-halved mr-2"></i> Business Verification
                </button>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                {{-- Header --}}
                <div class="mb-8">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Admin Panel</p>
                    <h1 class="mt-2 font-serif text-3xl font-bold text-slate-900">
                        @if ($section === 'analytics') Analytics Dashboard @else Business Verification @endif
                    </h1>
                </div>

                {{-- Analytics Section --}}
                @if ($section === 'analytics')
                    {{-- Key Metrics --}}
                    <div class="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Users</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $this->getTotalUsers() }}</p>
                                </div>
                                <div class="rounded-xl bg-blue-100 p-3 text-blue-600">
                                    <i class="fa-solid fa-users text-xl"></i>
                                </div>
                            </div>
                        </div>

                        @php $usersByPlan = $this->getUsersByPlan(); @endphp

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Free Plan</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $usersByPlan['free'] }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-100 p-3 text-slate-600">
                                    <i class="fa-solid fa-coins text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Paid Users</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $usersByPlan['standard'] + $usersByPlan['pro'] + $usersByPlan['spotlight'] }}</p>
                                </div>
                                <div class="rounded-xl bg-emerald-100 p-3 text-emerald-600">
                                    <i class="fa-solid fa-check-circle text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Businesses</p>
                                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ \App\Models\Business::count() }}</p>
                                </div>
                                <div class="rounded-xl bg-amber-100 p-3 text-amber-600">
                                    <i class="fa-solid fa-store text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Plan Breakdown --}}
                    <div class="mb-8 grid gap-6 lg:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="mb-4 text-lg font-bold text-slate-900">Users by Plan</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Free</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-slate-200">
                                            <div class="h-full w-full rounded-full bg-slate-400" style="width: {{ min(100, ($usersByPlan['free'] / $this->getTotalUsers()) * 100) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['free'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Standard</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-blue-200">
                                            <div class="h-full rounded-full bg-blue-500" style="width: {{ min(100, ($usersByPlan['standard'] / $this->getTotalUsers()) * 100) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['standard'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Pro</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-amber-200">
                                            <div class="h-full rounded-full bg-amber-500" style="width: {{ min(100, ($usersByPlan['pro'] / $this->getTotalUsers()) * 100) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['pro'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Spotlight</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-purple-200">
                                            <div class="h-full rounded-full bg-purple-500" style="width: {{ min(100, ($usersByPlan['spotlight'] / $this->getTotalUsers()) * 100) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['spotlight'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- New Users Trend --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-bold text-slate-900">New Users</h3>
                                <div class="flex gap-2">
                                    @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $filter => $label)
                                        <button
                                            wire:click="setTimeFilter('{{ $filter }}')"
                                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition {{ $timeFilter === $filter ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="space-y-2">
                                @php $trend = $this->getNewUsersTrend(); $maxValue = max($trend ?: [1]); @endphp
                                @forelse ($trend as $period => $count)
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-slate-600 w-16 truncate">{{ $period }}</span>
                                        <div class="flex-1 mx-2 h-2 rounded-full bg-slate-200">
                                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ ($count / max($maxValue, 1)) * 100 }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-slate-900 w-8 text-right">{{ $count }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No new users in this period</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Business Verification Section --}}
                @if ($section === 'businesses')
                    @if (session('status'))
                        <div role="status" class="mb-6 rounded-xl border border-emerald-500 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <form wire:submit="filterBusinesses" class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="status" class="mb-1 block text-xs font-semibold text-slate-700">Status</label>
                                <select id="status" wire:model.live="businessStatus" class="rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm">
                                    <option value="pending">Pending</option>
                                    <option value="verified">Verified</option>
                                    <option value="all">All businesses</option>
                                </select>
                            </div>
                            <div class="min-w-52 flex-1">
                                <label for="search" class="mb-1 block text-xs font-semibold text-slate-700">Search business, location, or owner</label>
                                <input id="search" wire:model.live="businessSearch" type="search" maxlength="255" placeholder="Search..." class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm">
                            </div>
                        </form>
                    </div>

                    <div class="space-y-4">
                        @forelse ($this->businesses as $business)
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
                                    <div><dt class="font-semibold text-slate-700">Owner</dt><dd class="wrap-break-word text-slate-600">{{ $business->user->name }} ({{ $business->user->email }})</dd></div>
                                    <div><dt class="font-semibold text-slate-700">Address</dt><dd class="wrap-break-word text-slate-600">{{ collect([$business->address, $business->location, $business->zip])->filter()->implode(', ') }}</dd></div>
                                    <div><dt class="font-semibold text-slate-700">Phone</dt><dd class="wrap-break-word text-slate-600">{{ $business->phone ?? 'N/A' }}</dd></div>
                                    <div><dt class="font-semibold text-slate-700">Website</dt><dd class="wrap-break-word text-slate-600">{{ $business->website ?? 'N/A' }}</dd></div>
                                </dl>

                                @if (filled($business->description))
                                    <div class="mt-4 border-t border-slate-200 pt-4">
                                        <dt class="font-semibold text-slate-700 text-sm">Description</dt>
                                        <dd class="mt-1 text-sm text-slate-600 line-clamp-3">{{ $business->description }}</dd>
                                    </div>
                                @endif

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if (! $business->is_verified)
                                        <button
                                            wire:click="verifyBusiness({{ $business->id }})"
                                            wire:confirm="Verify this business?"
                                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500 transition"
                                        >
                                            <i class="fa-solid fa-check mr-2"></i> Verify
                                        </button>
                                    @else
                                        <button
                                            wire:click="unverifyBusiness({{ $business->id }})"
                                            wire:confirm="Remove verification?"
                                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-500 transition"
                                        >
                                            <i class="fa-solid fa-times mr-2"></i> Unverify
                                        </button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
                                <i class="fa-solid fa-inbox text-4xl text-slate-300 mb-4"></i>
                                <p class="text-slate-500">No businesses found matching your filters.</p>
                            </div>
                        @endforelse
                    </div>

                    {{ $this->businesses->links() }}
                @endif
            </div>
        </main>

        <livewire:add-business-modal />
    </div>
</div>
