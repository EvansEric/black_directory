<?php

use App\Models\User;
use App\Models\Business;
use App\Services\SubscriptionGating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    public ?int $editingUserId = null;
    public string $editingUserName = '';
    public string $editingUserEmail = '';
    public string $editingUserPassword = '';
    public string $editingUserPasswordConfirm = '';
    public bool $editingUserIsAdmin = false;
    public bool $showAddUserModal = false;
    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserPassword = '';
    public string $newUserPasswordConfirm = '';
    public bool $newUserIsAdmin = false;

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
        return User::where('is_admin', false)->count();
    }

    public function getUsersByPlan(): array
    {
        return [
            'free' => User::where('is_admin', false)->whereDoesntHave('businesses', function ($query) {
                $query->whereIn('subscription_tier', [SubscriptionGating::TIER_STANDARD, SubscriptionGating::TIER_PRO, SubscriptionGating::TIER_SPOTLIGHT]);
            })->count(),
            'standard' => User::where('is_admin', false)->whereHas('businesses', function ($query) {
                $query->where('subscription_tier', SubscriptionGating::TIER_STANDARD)->where(function ($q) {
                    $q->where('subscription_status', 'active')->orWhere('subscription_status', 'trialing');
                });
            })->distinct()->count(),
            'pro' => User::where('is_admin', false)->whereHas('businesses', function ($query) {
                $query->where('subscription_tier', SubscriptionGating::TIER_PRO)->where(function ($q) {
                    $q->where('subscription_status', 'active')->orWhere('subscription_status', 'trialing');
                });
            })->distinct()->count(),
            'spotlight' => User::where('is_admin', false)->whereHas('businesses', function ($query) {
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

    #[Computed]
    public function users()
    {
        return User::where('is_admin', false)->latest()->paginate(10);
    }

    public function filterBusinesses(): void
    {
        // This method is called by wire:submit on the form
        // Livewire will automatically re-compute businesses() after property updates
    }

    public function openAddUserModal(): void
    {
        $this->showAddUserModal = true;
    }

    public function closeAddUserModal(): void
    {
        $this->showAddUserModal = false;
        $this->newUserName = '';
        $this->newUserEmail = '';
        $this->newUserPassword = '';
        $this->newUserPasswordConfirm = '';
        $this->newUserIsAdmin = false;
    }

    public function saveAddUser(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|max:255|unique:users,email',
            'newUserPassword' => 'required|string|min:8',
            'newUserPasswordConfirm' => 'required|same:newUserPassword',
        ]);

        User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => Hash::make($this->newUserPassword),
            'is_admin' => $this->newUserIsAdmin,
            'is_active' => true,
        ]);

        session()->flash('status', 'User created successfully.');
        $this->closeAddUserModal();
    }

    public function openEditUserModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $userId;
        $this->editingUserName = $user->name;
        $this->editingUserEmail = $user->email;
        $this->editingUserIsAdmin = $user->is_admin;
    }

    public function saveEditUser(): void
    {
        if (!$this->editingUserId) {
            return;
        }

        $rules = [];

        if (filled($this->editingUserPassword)) {
            $rules['editingUserPassword'] = 'required|string|min:8';
            $rules['editingUserPasswordConfirm'] = 'required|same:editingUserPassword';
        }

        if (!empty($rules)) {
            $this->validate($rules);
        }

        $user = User::findOrFail($this->editingUserId);
        $updateData = [];

        if (filled($this->editingUserPassword)) {
            $updateData['password'] = Hash::make($this->editingUserPassword);
        }

        $updateData['is_admin'] = $this->editingUserIsAdmin;

        $user->update($updateData);

        $this->editingUserId = null;
        $this->editingUserPassword = '';
        $this->editingUserPasswordConfirm = '';
        $this->editingUserIsAdmin = false;
        session()->flash('status', "{$user->name} has been updated successfully.");
    }

    public function cancelEditUser(): void
    {
        $this->editingUserId = null;
        $this->editingUserName = '';
        $this->editingUserEmail = '';
        $this->editingUserPassword = '';
        $this->editingUserPasswordConfirm = '';
        $this->editingUserIsAdmin = false;
    }

    public function makeAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_unless(Auth::user()->is_admin, 403);
        $user->update(['is_admin' => true]);
        session()->flash('status', "{$user->name} is now an admin.");
    }

    public function toggleUserStatus(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_unless(Auth::user()->is_admin, 403);
        $user->update(['is_active' => !($user->is_active ?? true)]);
        $status = ($user->is_active ?? true) ? 'activated' : 'deactivated';
        session()->flash('status', "{$user->name} has been {$status}.");
    }

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_unless(Auth::user()->is_admin, 403);
        abort_if($user->id === Auth::id(), 403);
        $user->delete();
        session()->flash('status', 'User deleted successfully.');
    }

    public function makeSuperAdmin(int $userId): void
    {
        abort_unless(Auth::user()->is_super_admin, 403);
        $user = User::findOrFail($userId);
        $user->update(['is_super_admin' => true]);
        session()->flash('status', "{$user->name} is now a super admin.");
    }

    public function removeAdmin(int $userId): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        abort_if($userId === Auth::id(), 403);
        $user = User::findOrFail($userId);
        $user->update(['is_admin' => false, 'is_super_admin' => false]);
        session()->flash('status', 'Admin status removed from ' . $user->name);
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

                <button
                    wire:click="setSection('user-management')"
                    class="w-full rounded-xl px-4 py-2.5 text-left text-sm font-medium transition {{ $section === 'user-management' ? 'bg-amber-100 text-amber-900' : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <i class="fa-solid fa-users-gear mr-2"></i> User Management
                </button>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                {{-- Header --}}
                <div class="mb-8">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Admin Panel</p>
                    <div class="mt-2 flex items-center justify-between">
                        <h1 class="font-serif text-3xl font-bold text-slate-900">
                            @if ($section === 'analytics') Analytics Dashboard @elseif ($section === 'businesses') Business Verification @else User Management @endif
                        </h1>
                    </div>
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
                                            <div class="h-full w-full rounded-full bg-slate-400" style="width: {{ min(100, ($this->getTotalUsers() > 0 ? ($usersByPlan['free'] / $this->getTotalUsers()) * 100 : 0)) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['free'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Standard</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-blue-200">
                                            <div class="h-full rounded-full bg-blue-500" style="width: {{ min(100, ($this->getTotalUsers() > 0 ? ($usersByPlan['standard'] / $this->getTotalUsers()) * 100 : 0)) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['standard'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Pro</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-amber-200">
                                            <div class="h-full rounded-full bg-amber-500" style="width: {{ min(100, ($this->getTotalUsers() > 0 ? ($usersByPlan['pro'] / $this->getTotalUsers()) * 100 : 0)) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right text-sm font-semibold text-slate-900">{{ $usersByPlan['pro'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">Spotlight</span>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-purple-200">
                                            <div class="h-full rounded-full bg-purple-500" style="width: {{ min(100, ($this->getTotalUsers() > 0 ? ($usersByPlan['spotlight'] / $this->getTotalUsers()) * 100 : 0)) }}%"></div>
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
                        <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-700 mb-2">Business Management</p>
                                <p class="text-xs text-slate-500">Filter and manage business verification requests</p>
                            </div>
                            <button
                                wire:click="setSection('user-management')"
                                class="rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-600 transition whitespace-nowrap"
                            >
                                <i class="fa-solid fa-users-gear mr-2"></i> Manage Users
                            </button>
                        </div>
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

                {{-- User Management Section --}}
                @if ($section === 'user-management')
                    @if (session('status'))
                        <div role="status" class="mb-6 rounded-xl border border-emerald-500 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="mb-6">
                        <button
                            wire:click="openAddUserModal"
                            class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 transition"
                        >
                            <i class="fa-solid fa-plus mr-2"></i> Add New User
                        </button>
                    </div>

                    {{-- Add User Modal --}}
                    @if ($showAddUserModal)
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-lg overflow-y-auto max-h-[90vh]">
                                <h2 class="text-xl font-bold text-slate-900 mb-4">Add New User</h2>
                                
                                <form wire:submit="saveAddUser" class="space-y-4">
                                    <div>
                                        <label for="add-name" class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                                        <input 
                                            id="add-name"
                                            type="text" 
                                            wire:model="newUserName"
                                            class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                            required
                                        >
                                        @error('newUserName')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="add-email" class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                                        <input 
                                            id="add-email"
                                            type="email" 
                                            wire:model="newUserEmail"
                                            class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                            required
                                        >
                                        @error('newUserEmail')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="border-b border-slate-200 pb-4">
                                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-3">Password</p>
                                        
                                        <div>
                                            <label for="add-password" class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                                            <input 
                                                id="add-password"
                                                type="password" 
                                                wire:model="newUserPassword"
                                                placeholder="Minimum 8 characters"
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                                required
                                            >
                                            @error('newUserPassword')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="add-password-confirm" class="block text-sm font-semibold text-slate-700 mb-1 mt-3">Confirm Password</label>
                                            <input 
                                                id="add-password-confirm"
                                                type="password" 
                                                wire:model="newUserPasswordConfirm"
                                                placeholder="Confirm password"
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                                required
                                            >
                                            @error('newUserPasswordConfirm')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <input 
                                            id="add-admin"
                                            type="checkbox" 
                                            wire:model="newUserIsAdmin"
                                            class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                        >
                                        <label for="add-admin" class="text-sm font-semibold text-slate-700">Admin User</label>
                                    </div>

                                    <div class="flex gap-2 pt-4">
                                        <button
                                            type="button"
                                            wire:click="closeAddUserModal"
                                            class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 transition"
                                        >
                                            Create User
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Edit User Modal --}}
                    @if ($editingUserId)
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
                                <h2 class="text-xl font-bold text-slate-900 mb-4">Edit User</h2>
                                
                                <form wire:submit="saveEditUser" class="space-y-4">
                                    <div class="border-b border-slate-200 pb-4">
                                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-3">User Information</p>
                                        
                                        <div>
                                            <label for="edit-name" class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                                            <input 
                                                id="edit-name"
                                                type="text" 
                                                value="{{ $editingUserName }}"
                                                disabled
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm bg-slate-50 text-slate-700 cursor-not-allowed"
                                            >
                                        </div>

                                        <div class="mt-3">
                                            <label for="edit-email" class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                                            <input 
                                                id="edit-email"
                                                type="email" 
                                                value="{{ $editingUserEmail }}"
                                                disabled
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm bg-slate-50 text-slate-700 cursor-not-allowed"
                                            >
                                        </div>
                                    </div>

                                    <div class="border-b border-slate-200 pb-4">
                                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-3">Change Password (Optional)</p>
                                        
                                        <div>
                                            <label for="edit-password" class="block text-sm font-semibold text-slate-700 mb-1">New Password</label>
                                            <input 
                                                id="edit-password"
                                                type="password" 
                                                wire:model="editingUserPassword"
                                                placeholder="Leave blank to keep current password"
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                            >
                                            @error('editingUserPassword')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="edit-password-confirm" class="block text-sm font-semibold text-slate-700 mb-1 mt-3">Confirm Password</label>
                                            <input 
                                                id="edit-password-confirm"
                                                type="password" 
                                                wire:model="editingUserPasswordConfirm"
                                                placeholder="Confirm new password"
                                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                            >
                                            @error('editingUserPasswordConfirm')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <input 
                                            id="edit-admin"
                                            type="checkbox" 
                                            wire:model="editingUserIsAdmin"
                                            class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                        >
                                        <label for="edit-admin" class="text-sm font-semibold text-slate-700">Admin User</label>
                                    </div>

                                    <div class="flex gap-2 pt-4">
                                        <button
                                            type="button"
                                            wire:click="cancelEditUser"
                                            class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-500 transition"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50">
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Email</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Joined</th>
                                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-slate-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @forelse ($this->users as $user)
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                                                        <i class="fa-solid fa-user text-slate-400"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ $user->email }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                @if ($user->is_admin)
                                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">
                                                        <i class="fa-solid fa-crown mr-1"></i> Admin
                                                    </span>
                                                @elseif (!($user->is_active ?? true))
                                                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">
                                                        <i class="fa-solid fa-circle-xmark mr-1"></i> Inactive
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-800">
                                                        <i class="fa-solid fa-circle-check mr-1"></i> Active
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ $user->created_at->format('M j, Y') }}</td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex gap-2 justify-end flex-wrap">
                                                    <button
                                                        type="button"
                                                        wire:click="openEditUserModal({{ $user->id }})"
                                                        title="Edit User"
                                                        class="rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-purple-500 transition"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    @if ($user->is_active ?? true)
                                                        <button
                                                            type="button"
                                                            wire:click="toggleUserStatus({{ $user->id }})"
                                                            wire:confirm="Deactivate {{ $user->name }}?"
                                                            title="Deactivate"
                                                            class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-500 transition"
                                                        >
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="toggleUserStatus({{ $user->id }})"
                                                            wire:confirm="Activate {{ $user->name }}?"
                                                            title="Activate"
                                                            class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-500 transition"
                                                        >
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        wire:click="deleteUser({{ $user->id }})"
                                                        wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                                        title="Delete"
                                                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-500 transition"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center">
                                                <i class="fa-solid fa-inbox text-4xl text-slate-300 mb-4 block"></i>
                                                <p class="text-slate-500">No non-admin users found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{ $this->users->links() }}

                    {{-- Admin Users Section --}}
                    <div class="mt-12">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6">Current Admins</h2>
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50">
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Name</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Email</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Level</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-700">Joined</th>
                                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-slate-700">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @forelse (User::where('is_admin', true)->latest()->get() as $admin)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-10 w-10 rounded-full {{ $admin->is_super_admin ? 'bg-purple-200' : 'bg-amber-200' }} flex items-center justify-center">
                                                            <i class="fa-solid {{ $admin->is_super_admin ? 'fa-crown' : 'fa-star' }} {{ $admin->is_super_admin ? 'text-purple-600' : 'text-amber-600' }}"></i>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-slate-900">{{ $admin->name }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-slate-600">{{ $admin->email }}</td>
                                                <td class="px-6 py-4 text-sm">
                                                    @if ($admin->is_super_admin)
                                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-800">
                                                            <i class="fa-solid fa-crown mr-1"></i> Super Admin
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
                                                            <i class="fa-solid fa-star mr-1"></i> Admin
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-sm text-slate-600">{{ $admin->created_at->format('M j, Y') }}</td>
                                                <td class="px-6 py-4 text-right">
                                                    @if ($admin->id !== Auth::user()->id)
                                                        <div class="flex gap-2 justify-end">
                                                            @if (!$admin->is_super_admin && Auth::user()->is_super_admin)
                                                                <button
                                                                    wire:click="makeSuperAdmin({{ $admin->id }})"
                                                                    wire:confirm="Promote {{ $admin->name }} to Super Admin?"
                                                                    class="rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-purple-500 transition"
                                                                    title="Make Super Admin"
                                                                >
                                                                    <i class="fa-solid fa-crown"></i>
                                                                </button>
                                                            @endif
                                                            <button
                                                                wire:click="removeAdmin({{ $admin->id }})"
                                                                wire:confirm="Remove admin from {{ $admin->name }}?"
                                                                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-500 transition"
                                                                title="Remove Admin"
                                                            >
                                                                <i class="fa-solid fa-times mr-1"></i> Remove
                                                            </button>
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-slate-500 font-semibold">You</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-6 py-12 text-center">
                                                    <p class="text-slate-500">No admins found.</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </main>

        <livewire:add-business-modal />
    </div>
</div>
