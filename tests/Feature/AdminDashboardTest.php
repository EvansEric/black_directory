<?php
use App\Models\User;
use Livewire\Livewire;

it('loads the admin dashboard for admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin Dashboard');
});

it('denies access to non-admins', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

it('denies access to guests', function () {
    $this->get(route('admin.dashboard'))->assertForbidden();
});


