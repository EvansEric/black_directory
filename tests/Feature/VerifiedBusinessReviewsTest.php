<?php

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

function reviewBusiness(?User $owner = null): Business
{
    return Business::create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'name' => 'Verified Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A local shop.',
        'is_verified' => true,
        'rating' => 5,
        'reviews_count' => 99,
    ]);
}

it('does not expose review verification routes or admin links', function () {
    expect(Route::has('reviews.create'))->toBeFalse()
        ->and(Route::has('reviews.verify'))->toBeFalse()
        ->and(Route::has('reviews.store'))->toBeFalse()
        ->and(Route::has('admin.review-verifications.index'))->toBeFalse()
        ->and(Route::has('admin.review-verifications.evidence'))->toBeFalse()
        ->and(Route::has('admin.review-verifications.decide'))->toBeFalse();

    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test('pages::admin-dashboard')
        ->assertDontSee('Review Verification');
});

it('does not offer a link to request or submit reviews', function () {
    $business = reviewBusiness();

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertDontSee('Request verification')
        ->assertDontSee('write a review')
        ->assertDontSee('Log in to request a review');
});

it('does not present legacy ratings as verified customer ratings', function () {
    $business = reviewBusiness();

    Livewire::test('business-list')
        ->assertSee('Not yet rated')
        ->assertDontSee('5.0 (99)');
    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('Not yet rated')
        ->assertDontSee('(99 reviews)');
});

it('keeps existing verified reviews publicly readable', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();

    foreach (range(1, 6) as $index) {
        $verification = ReviewVerification::create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'status' => 'consumed',
        ]);
        BusinessReview::create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'review_verification_id' => $verification->id,
            'rating' => 5,
            'comment' => $index === 1 ? '<script>unsafe</script>' : "Review {$index}",
        ]);
    }

    $this->get(route('reviews.index', $business))
        ->assertOk()
        ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
        ->assertDontSee('<script>unsafe</script>', false);
    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('See all verified reviews')
        ->assertDontSee('write a review');
});
