<?php

use App\Models\Business;
use App\Models\User;

it('renders a sidebar advertisement area on the home page', function () {
    config()->set('app.advertisements_enabled', true);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Advertisement')
        ->assertSee('directory-sidebar')
        ->assertSee('Your ad could be here');
});

it('hides the advertisement and sidebar layout when advertisements are disabled', function () {
    config()->set('app.advertisements_enabled', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Advertisement')
        ->assertDontSee('directory-sidebar')
        ->assertDontSee('Your ad could be here')
        ->assertDontSee('xl:grid-cols-[minmax(0,1fr)_18rem]', false);
});

it('shows the promote listing button to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Promote Your Listing');
});

it('hides the promote listing button from super admins', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)->get(route('home'))
        ->assertOk()
        ->assertDontSee('Promote Your Listing')
        ->assertDontSee('View Listing Plans');
});

it('hides promotion and plan listings from users with only unverified businesses', function () {
    $owner = User::factory()->create();
    Business::create([
        'user_id' => $owner->id,
        'name' => 'Pending Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Pending verification.',
    ]);

    $this->actingAs($owner)->get(route('home'))
        ->assertOk()
        ->assertDontSee('Promote Your Listing')
        ->assertDontSee('Pricing')
        ->assertDontSee('View Listing Plans')
        ->assertDontSee('Choose Standard');

    $this->actingAs($owner)->get(route('pricing'))->assertRedirect('/');
    $this->actingAs($owner)->get(route('subscriptions.pricing'))->assertForbidden();
});

it('shows the promote button when the owner has a verified business', function () {
    $owner = User::factory()->create();
    Business::create([
        'user_id' => $owner->id,
        'name' => 'Verified Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Verified.',
        'is_verified' => true,
    ]);

    $this->actingAs($owner)->get(route('home'))
        ->assertOk()
        ->assertSee('Promote Your Listing')
        ->assertDontSee('View Listing Plans');
});
