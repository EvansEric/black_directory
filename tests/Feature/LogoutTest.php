<?php

use App\Models\User;
use Livewire\Livewire;

it('renders a logout button wired to the logout action for logged in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertSee('wire:click="logout"', false);
});

it('logs the user out and ends the session from the navigation', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('navigation')
        ->call('logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
