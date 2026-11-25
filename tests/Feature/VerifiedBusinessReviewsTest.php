<?php

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

it('links admins to review verification', function () {
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test('pages::admin-dashboard')
        ->assertSee('Review Verification')
        ->assertSee(route('admin.review-verifications.index'), false);
});

it('offers guests login and customers a review link but hides it from the owner', function () {
    $business = reviewBusiness();

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('Log in to write a review');

    $customer = User::factory()->create();
    $this->actingAs($customer)->get(route('businesses.show', $business))
        ->assertSee('href="'.route('reviews.create', $business).'"', false);
    $this->get(route('reviews.index', $business))
        ->assertSee('href="'.route('reviews.create', $business).'"', false);

    Livewire::actingAs($business->user)->test('business-details-modal')
        ->call('open', $business->id)
        ->assertDontSee('Write a review / check verification status');
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
        ->assertSee('See all verified reviews');
});

it('requires authentication for customer routes and admin access for moderation', function () {
    $business = reviewBusiness();
    $verification = ReviewVerification::create([
        'business_id' => $business->id, 'user_id' => User::factory()->create()->id,
    ]);

    $this->get(route('reviews.create', $business))->assertRedirect(route('login'));
    $this->post(route('reviews.verify', $business))->assertRedirect(route('login'));
    $this->post(route('reviews.store', $business))->assertRedirect(route('login'));

    foreach ([null, User::factory()->create()] as $user) {
        if ($user) {
            $this->actingAs($user);
        }
        $this->get(route('admin.review-verifications.index'))->assertForbidden();
        $this->get(route('admin.review-verifications.evidence', $verification))->assertForbidden();
        $this->post(route('admin.review-verifications.decide', $verification), ['decision' => 'approved'])->assertForbidden();
    }
});

it('verifies evidence privately then publishes exactly one review per approved transaction', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $file = UploadedFile::fake()->image('receipt.png', 10, 10);
    $contents = $file->getContent();

    $this->actingAs($customer)->post(route('reviews.verify', $business), [
        'transaction_reference' => 'ORDER-123',
        'evidence' => $file,
    ])->assertRedirect(route('reviews.create', $business));

    $verification = ReviewVerification::query()->sole();
    expect($verification->status)->toBe('pending')
        ->and($verification->transaction_reference)->toBe('ORDER-123')
        ->and(base64_decode($verification->evidence, true))->toBe($contents);
    $raw = DB::table('review_verifications')->where('id', $verification->id)->first();
    expect($raw->transaction_reference)->not->toBe('ORDER-123')
        ->and($raw->evidence)->not->toBe(base64_encode($contents));

    $this->get(route('reviews.create', $business))->assertOk()
        ->assertSee('Pending')->assertDontSee('ORDER-123')
        ->assertDontSee('Publish review')
        ->assertSee('content="noindex, follow"', false);

    $this->actingAs($admin)->get(route('admin.review-verifications.index'))
        ->assertOk()->assertSee('ORDER-123')->assertSee($customer->email)
        ->assertSee('content="noindex, follow"', false);
    $response = $this->get(route('admin.review-verifications.evidence', $verification))
        ->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename="purchase-evidence"');
    expect($response->getContent())->toBe($contents)
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');

    $this->post(route('admin.review-verifications.decide', $verification), ['decision' => 'approved'])
        ->assertRedirect(route('admin.review-verifications.index'));

    $verification->refresh();
    expect($verification->status)->toBe('approved')
        ->and($verification->evidence)->toBeNull()
        ->and($verification->evidence_type)->toBeNull()
        ->and($verification->transaction_reference)->toBeNull()
        ->and($verification->decided_by)->toBe($admin->id)
        ->and($verification->decided_at)->not->toBeNull();
    $this->get(route('admin.review-verifications.evidence', $verification))->assertNotFound();
    $this->post(route('admin.review-verifications.decide', $verification), ['decision' => 'rejected'])
        ->assertStatus(409);

    $this->actingAs($customer)->get(route('reviews.create', $business))
        ->assertOk()->assertSee('Approved')->assertSee('Publish review');
    $payload = ['verification_id' => $verification->id, 'rating' => 4, 'comment' => 'Great service.'];
    $this->post(route('reviews.store', $business), $payload)
        ->assertRedirect(route('reviews.create', $business));
    expect($verification->fresh()->status)->toBe('consumed');
    $this->assertDatabaseHas('business_reviews', [
        'business_id' => $business->id, 'user_id' => $customer->id,
        'review_verification_id' => $verification->id, 'rating' => 4, 'comment' => 'Great service.',
    ]);
    $this->get(route('reviews.create', $business))->assertSee('Review published: 4/5');
    $this->get(route('reviews.index', $business))->assertSee('Great service.');
    $this->get(route('businesses.show', $business))->assertSee('4.0 out of 5 (1 verified reviews)');
    Livewire::test('business-list')->assertSee('4.0 (1)');

    $this->post(route('reviews.store', $business), $payload)->assertSessionHasErrors('verification_id');
    $this->assertDatabaseCount('business_reviews', 1);
    $this->post(route('reviews.verify', $business), [
        'transaction_reference' => ' order-123 ', 'evidence' => UploadedFile::fake()->image('receipt.png'),
    ])->assertSessionHasErrors('transaction_reference');
});

it('allows rejected evidence to be resubmitted and deletes its sensitive fields', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($customer)->post(route('reviews.verify', $business), [
        'transaction_reference' => 'RETRY-1', 'evidence' => UploadedFile::fake()->image('receipt.png'),
    ])->assertSessionHasNoErrors();
    $verification = ReviewVerification::query()->sole();

    $this->actingAs($admin)->post(route('admin.review-verifications.decide', $verification), ['decision' => 'rejected'])
        ->assertRedirect();
    $verification->refresh();
    expect($verification->status)->toBe('rejected')
        ->and($verification->transaction_hash)->toBeNull()
        ->and($verification->transaction_reference)->toBeNull()
        ->and($verification->evidence)->toBeNull()
        ->and($verification->evidence_type)->toBeNull();

    $this->actingAs($customer)->get(route('reviews.create', $business))
        ->assertSee('Rejected')->assertDontSee('Publish review');
    $this->post(route('reviews.verify', $business), [
        'transaction_reference' => 'RETRY-1', 'evidence' => UploadedFile::fake()->image('receipt.png'),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('review_verifications', 2);
});

it('blocks owner reviews, unverified businesses, and customers without verified email', function () {
    $business = reviewBusiness();
    $owner = $business->user;
    $customer = User::factory()->unverified()->create();

    $this->actingAs($owner)->get(route('reviews.create', $business))->assertForbidden();
    $this->post(route('reviews.verify', $business))->assertForbidden();
    $this->post(route('reviews.store', $business))->assertForbidden();

    $this->actingAs($customer)->get(route('reviews.create', $business))
        ->assertOk()->assertSee('Verify your email first')->assertDontSee('Submit purchase evidence');
    $this->post(route('reviews.verify', $business))->assertForbidden();
    $this->post(route('reviews.store', $business))->assertForbidden();

    $business->update(['is_verified' => false]);
    $this->get(route('reviews.create', $business))->assertNotFound();
    $this->post(route('reviews.verify', $business))->assertNotFound();
    $this->post(route('reviews.store', $business))->assertNotFound();
});

it('prevents admins from seeing or deciding their own or their business evidence', function (bool $ownsBusiness) {
    $admin = User::factory()->create(['is_admin' => true]);
    $business = reviewBusiness($ownsBusiness ? $admin : null);
    $verification = ReviewVerification::create([
        'business_id' => $business->id,
        'user_id' => $ownsBusiness ? User::factory()->create()->id : $admin->id,
        'transaction_reference' => 'CONFLICT-1',
        'evidence' => base64_encode('private receipt'),
        'evidence_type' => 'application/pdf',
    ]);

    $this->actingAs($admin)->get(route('admin.review-verifications.index'))
        ->assertOk()->assertDontSee('CONFLICT-1');
    $this->get(route('admin.review-verifications.evidence', $verification))->assertForbidden();
    $this->post(route('admin.review-verifications.decide', $verification), ['decision' => 'approved'])->assertForbidden();
    expect($verification->fresh()->status)->toBe('pending');
})->with([true, false]);

it('only accepts an approved purchase owned by the reviewer for the same business', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $other = User::factory()->create();
    $verification = ReviewVerification::create([
        'business_id' => $business->id, 'user_id' => $other->id, 'status' => 'approved',
    ]);

    $this->actingAs($customer)->get(route('reviews.create', $business))->assertDontSee('Publish review');
    $this->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 5,
    ])->assertNotFound();

    $verification->update(['user_id' => $customer->id]);
    $otherBusiness = reviewBusiness();
    $this->post(route('reviews.store', $otherBusiness), [
        'verification_id' => $verification->id, 'rating' => 5,
    ])->assertNotFound();
    $this->assertDatabaseCount('business_reviews', 0);
});

it('rejects review publication before approval or after consumption', function (string $status) {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $verification = ReviewVerification::create([
        'business_id' => $business->id, 'user_id' => $customer->id, 'status' => $status,
    ]);
    $this->actingAs($customer)->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 5,
    ])->assertSessionHasErrors('verification_id');
    $this->assertDatabaseCount('business_reviews', 0);
})->with(['pending', 'rejected', 'consumed']);

it('validates evidence type, size, and reference before saving', function () {
    $business = reviewBusiness();
    $this->actingAs(User::factory()->create());

    foreach ([
        ['transaction_reference' => ' ', 'evidence' => UploadedFile::fake()->image('receipt.png'), 'error' => 'transaction_reference'],
        ['transaction_reference' => 'INVALID-1', 'evidence' => UploadedFile::fake()->createWithContent('script.html', '<script>alert(1)</script>'), 'error' => 'evidence'],
        ['transaction_reference' => 'LARGE-1', 'evidence' => UploadedFile::fake()->create('receipt.pdf', 5121, 'application/pdf'), 'error' => 'evidence'],
    ] as $case) {
        $this->post(route('reviews.verify', $business), [
            'transaction_reference' => $case['transaction_reference'], 'evidence' => $case['evidence'],
        ])->assertSessionHasErrors($case['error']);
    }
    $this->assertDatabaseCount('review_verifications', 0);
});

it('validates ratings and comments before consuming approval', function () {
    $business = reviewBusiness();
    $customer = User::factory()->create();
    $verification = ReviewVerification::create([
        'business_id' => $business->id, 'user_id' => $customer->id, 'status' => 'approved',
    ]);
    $this->actingAs($customer);

    foreach ([0, 6, 2.5] as $rating) {
        $this->post(route('reviews.store', $business), [
            'verification_id' => $verification->id, 'rating' => $rating,
        ])->assertSessionHasErrors('rating');
    }
    $this->post(route('reviews.store', $business), [
        'verification_id' => $verification->id, 'rating' => 5, 'comment' => str_repeat('a', 2001),
    ])->assertSessionHasErrors('comment');

    expect($verification->fresh()->status)->toBe('approved');
    $this->assertDatabaseCount('business_reviews', 0);
});
