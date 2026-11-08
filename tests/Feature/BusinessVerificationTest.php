<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function pendingListing(User $owner): Business
{
    return Business::create([
        'user_id' => $owner->id, 'name' => 'Pending Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
    ]);
}

it('hides unverified listings from the public but shows them to the owner and admins', function () {
    $owner = User::factory()->create();
    pendingListing($owner);

    Livewire::test('business-list')->assertDontSee('Pending Shop');
    Livewire::actingAs(User::factory()->create())->test('business-list')->assertDontSee('Pending Shop');
    Livewire::actingAs($owner)->test('business-list')->assertSee('Pending Shop');
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))->test('business-list')->assertSee('Pending Shop');
});

it('promotes a user to admin from the command line', function () {
    $user = User::factory()->create();

    $this->artisan('user:make-admin', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('verifies a business from the command line', function () {
    $business = pendingListing(User::factory()->create());

    $this->artisan('business:verify', ['id' => $business->id])->assertSuccessful();

    expect($business->fresh()->is_verified)->toBeTrue();
});
