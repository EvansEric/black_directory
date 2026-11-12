<?php

use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;

function adminReviewBusiness(User $owner, string $name, bool $verified = false): Business
{
    return Business::create([
        'user_id' => $owner->id,
        'name' => $name,
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Business details for review.',
        'is_verified' => $verified,
    ]);
}

it('denies the admin page and verification updates to guests and non-admins', function () {
    $owner = User::factory()->create();
    $business = adminReviewBusiness($owner, 'Pending Shop');
    $index = route('admin.businesses.index');
    $update = route('admin.businesses.verification', $business);

    $this->get($index)->assertForbidden();
    $this->patch($update, ['is_verified' => '1'])->assertForbidden();
    $this->actingAs($owner)->get($index)->assertForbidden();
    $this->actingAs($owner)->patch($update, ['is_verified' => '1'])->assertForbidden();

    expect($business->fresh()->is_verified)->toBeFalse();
});

it('shows the admin navigation link only to admins', function () {
    $this->get(route('home'))->assertDontSee('Admin Dashboard');
    $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee('Admin Dashboard');
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('home'))
        ->assertSee('Admin Dashboard')
        ->assertSee(route('admin.dashboard'), false);
});

it('lists pending businesses by default and supports status and owner search', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    adminReviewBusiness($owner, 'Pending Shop');
    adminReviewBusiness(User::factory()->create(), 'Verified Shop', true);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(route('admin.businesses.index'))
        ->assertOk()->assertSee('Pending Shop')->assertSee('owner@example.test')
        ->assertSee('Business details for review.')->assertDontSee('Verified Shop');
    $this->get(route('admin.businesses.index', ['status' => 'verified']))
        ->assertOk()->assertSee('Verified Shop')->assertDontSee('Pending Shop');
    $this->get(route('admin.businesses.index', ['status' => 'all', 'search' => 'owner@example.test']))
        ->assertOk()->assertSee('Pending Shop')->assertDontSee('Verified Shop');
});

it('verifies and revokes verification, changing public directory visibility', function () {
    $business = adminReviewBusiness(User::factory()->create(), 'Review Shop');
    $admin = User::factory()->create(['is_admin' => true]);
    $url = route('admin.businesses.verification', $business);

    Livewire::test('business-list')->assertDontSee('Review Shop');
    $this->actingAs($admin)->patch($url, ['is_verified' => '1'])
        ->assertRedirect(route('admin.businesses.index', ['status' => 'pending']))
        ->assertSessionHas('status');
    expect($business->fresh()->is_verified)->toBeTrue();

    auth()->logout();
    Livewire::test('business-list')->assertSee('Review Shop');
    $this->actingAs($admin)->patch($url, ['is_verified' => '0'])
        ->assertRedirect(route('admin.businesses.index', ['status' => 'verified']))
        ->assertSessionHas('status');
    expect($business->fresh()->is_verified)->toBeFalse();

    auth()->logout();
    Livewire::test('business-list')->assertDontSee('Review Shop');
});

it('rejects invalid updates and reports missing businesses', function () {
    $business = adminReviewBusiness(User::factory()->create(), 'Review Shop');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->patchJson(route('admin.businesses.verification', $business), ['is_verified' => 'yes'])
        ->assertUnprocessable();
    $this->patchJson(route('admin.businesses.verification', 999999), ['is_verified' => '1'])
        ->assertNotFound();
    expect($business->fresh()->is_verified)->toBeFalse();
});

it('paginates review results and escapes submitted content', function () {
    $owner = User::factory()->create();
    foreach (range(1, 16) as $number) {
        adminReviewBusiness($owner, "Shop {$number}");
    }
    adminReviewBusiness($owner, '<script>alert(1)</script>');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(route('admin.businesses.index'))
        ->assertOk()->assertSee('Shop 1')->assertDontSee('Shop 16');
    $this->get(route('admin.businesses.index', ['page' => 2]))
        ->assertOk()->assertSee('Shop 16')->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
