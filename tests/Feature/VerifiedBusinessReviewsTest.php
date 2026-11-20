<?php

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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

function purchaseEvidence(string $reference = 'RECEIPT-123'): array
{
    return [
        'transaction_reference' => $reference,
        'evidence' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
    ];
}

it('verifies evidence privately and permits exactly one published review per purchase', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $url = route('reviews.verify', $business);

    $this->actingAs($customer)->post($url, purchaseEvidence())->assertRedirect();
    $verification = ReviewVerification::firstOrFail();
    $stored = DB::table('review_verifications')->where('id', $verification->id)->first();
    expect($stored->evidence)->not->toBe($verification->evidence)
        ->and($stored->transaction_reference)->not->toContain('RECEIPT-123')
        ->and($verification->status)->toBe('pending');

    $this->actingAs($customer)->get(route('admin.review-verifications.evidence', $verification))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.review-verifications.evidence', $verification))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($customer)->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 1,
    ])->assertSessionHasErrors('verification_id');

    $this->actingAs($admin)->post(route('admin.review-verifications.decide', $verification), ['decision' => 'approved'])
        ->assertRedirect();
    $verification->refresh();
    expect($verification->status)->toBe('approved')
        ->and($verification->evidence)->toBeNull()
        ->and($verification->transaction_reference)->toBeNull();
    $this->get(route('admin.review-verifications.evidence', $verification))->assertNotFound();

    $this->actingAs($customer)->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 1, 'comment' => 'Not a good experience.',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(BusinessReview::count())->toBe(1)
        ->and($verification->fresh()->status)->toBe('consumed');
    $this->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 5,
    ])->assertSessionHasErrors('verification_id');
    $this->post($url, purchaseEvidence('receipt-123'))->assertSessionHasErrors('transaction_reference');

    Livewire::test('business-details-modal')->call('open', $business->id)
        ->assertSee('1.0 (1 verified reviews)')->assertSee('Not a good experience.');
});

it('prevents guests, unverified accounts, owners and other users from bypassing verification', function () {
    $owner = User::factory()->create();
    $business = reviewBusiness($owner);
    $unverified = User::factory()->unverified()->create();
    $customer = User::factory()->create();
    $other = User::factory()->create();

    $this->post(route('reviews.verify', $business), purchaseEvidence())->assertRedirect(route('login'));
    $this->actingAs($owner)->post(route('reviews.verify', $business), purchaseEvidence())->assertForbidden();
    $this->actingAs($unverified)->get(route('reviews.create', $business))->assertSee('Verify your email first');
    $this->post(route('reviews.verify', $business), purchaseEvidence())->assertForbidden();
    $this->actingAs($customer)->post(route('reviews.verify', $business), purchaseEvidence())->assertRedirect();
    $verification = ReviewVerification::firstOrFail();
    $verification->update(['status' => 'approved']);
    $this->actingAs($other)->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 5,
    ])->assertNotFound();
});

it('rejects invalid evidence and deletes rejected evidence while allowing resubmission', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($customer)->post(route('reviews.verify', $business), [
        'transaction_reference' => 'x',
        'evidence' => UploadedFile::fake()->create('file.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors('evidence');
    $this->post(route('reviews.verify', $business), purchaseEvidence())->assertRedirect();
    $verification = ReviewVerification::firstOrFail();
    $this->actingAs($admin)->post(route('admin.review-verifications.decide', $verification), ['decision' => 'rejected'])
        ->assertRedirect();
    expect($verification->fresh()->evidence)->toBeNull()
        ->and($verification->fresh()->transaction_hash)->toBeNull();
    $this->actingAs($customer)->post(route('reviews.verify', $business), purchaseEvidence())->assertSessionHasNoErrors();
    expect(ReviewVerification::count())->toBe(2);
});

it('prevents conflicts of interest in admin evidence review', function () {
    $owner = User::factory()->create(['is_admin' => true]);
    $business = reviewBusiness($owner);
    $customer = User::factory()->create();
    $this->actingAs($customer)->post(route('reviews.verify', $business), purchaseEvidence());
    $verification = ReviewVerification::firstOrFail();

    $this->actingAs($owner)->get(route('admin.review-verifications.index'))->assertDontSee($customer->email);
    $this->get(route('admin.review-verifications.evidence', $verification))->assertForbidden();
    $this->post(route('admin.review-verifications.decide', $verification), ['decision' => 'approved'])->assertForbidden();
    expect($verification->fresh()->status)->toBe('pending');
});

it('sends and accepts signed email verification links', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->from(route('home'))->post(route('verification.send'))->assertRedirect(route('home'));
    Notification::assertSentTo($user, VerifyEmail::class);
    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
    ]);
    $this->get($link)->assertRedirect(route('home'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not present legacy ratings as verified customer ratings', function () {
    $business = reviewBusiness();
    Livewire::test('business-list')->assertSee('Not yet rated')->assertDontSee('5.0 (99)');
    Livewire::test('business-details-modal')->call('open', $business->id)
        ->assertSee('Not yet rated')->assertDontSee('(99 reviews)');
});

it('orders ratings using review count and makes all reviews available publicly', function () {
    $single = reviewBusiness();
    $multiple = reviewBusiness();
    $unrated = reviewBusiness();
    $customer = User::factory()->create();

    foreach ([[$single, 5], ...array_fill(0, 6, [$multiple, 4])] as [$business, $rating]) {
        $verification = ReviewVerification::create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'status' => 'consumed',
        ]);
        BusinessReview::create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'review_verification_id' => $verification->id,
            'rating' => $rating,
            'comment' => '<script>unsafe</script>',
        ]);
    }

    expect(Business::query()->orderedByTier('rating')->pluck('id')->all())
        ->toBe([$multiple->id, $single->id, $unrated->id]);
    $this->get(route('reviews.index', $multiple))->assertOk()
        ->assertSee('4/5')->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
        ->assertDontSee('<script>unsafe</script>', false);
    Livewire::test('business-details-modal')->call('open', $multiple->id)->assertSee('See all verified reviews');
});
