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
    Livewire::actingAs($owner)->test('business-list')
        ->assertSee('Pending Shop')
        ->assertDontSee('title="Upgrade subscription"', false)
        ->assertDontSee('title="View listing plans"', false);
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))->test('business-list')->assertSee('Pending Shop');
});

it('promotes a user to admin from the command line', function () {
    $user = User::factory()->create();

    $this->artisan('user:make-admin', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('hides subscription upgrades from admins viewing listings they own', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    foreach ([$admin, $superAdmin] as $owner) {
        Business::create([
            'is_verified' => true,
            'user_id' => $owner->id,
            'name' => $owner->is_admin ? 'Admin Shop' : 'Super Admin Shop',
            'category' => 'Retail & Fashion',
            'location' => 'Atlanta, GA',
            'description' => 'Verified listing.',
        ]);

        Livewire::actingAs($owner)->test('business-list')
            ->assertDontSee('title="Upgrade subscription"', false);
    }
});

it('verifies a business from the command line', function () {
    $business = pendingListing(User::factory()->create());

    $this->artisan('business:verify', ['id' => $business->id])->assertSuccessful();

    expect($business->fresh()->is_verified)->toBeTrue();
});
