<?php

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessSubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function descriptionBusiness(string $tier, string $description = 'Plain description.'): Business
{
    return Business::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Description Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => $description,
        'is_verified' => true,
        'subscription_tier' => $tier,
        'subscription_status' => 'active',
    ]);
}

it('offers rich formatting and saves it for Standard and Pro listings', function (string $tier) {
    $business = descriptionBusiness($tier);
    $description = "Our **quality** and *service*.\n\n- First item\n- Second item\n\n1. One\n2. Two";

    Livewire::actingAs($business->user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSee('Description formatting')
        ->set('description', $description)
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->has_rich_description)->toBeTrue();

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSeeHtml('<strong>quality</strong>')
        ->assertSeeHtml('<em>service</em>')
        ->assertSeeHtml('<ul>')
        ->assertSeeHtml('<ol>')
        ->assertSeeHtml('<li>First item</li>');
})->with(['standard', 'pro']);

it('keeps Free descriptions plain and visible', function () {
    $business = descriptionBusiness('free');

    Livewire::actingAs($business->user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertDontSee('Description formatting')
        ->set('description', 'A **plain** description')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->has_rich_description)->toBeFalse();

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('A **plain** description')
        ->assertDontSeeHtml('<strong>plain</strong>');
});

it('escapes HTML and rejects unsafe links in rich descriptions', function () {
    $business = descriptionBusiness('standard');
    $business->update([
        'has_rich_description' => true,
        'description' => '<script>alert(1)</script> [bad](javascript:alert(1))',
    ]);

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('href="javascript:');
});

it('shows plain summaries without markdown and retains formatting after a downgrade', function () {
    $business = descriptionBusiness('pro', '**Great** coffee');
    $business->update(['has_rich_description' => true, 'subscription_tier' => 'free']);

    Livewire::test('business-list')
        ->assertSee('Great coffee')
        ->assertDontSee('**Great**');

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('Great coffee')
        ->assertDontSeeHtml('<strong>Great</strong>');

    Livewire::actingAs($business->user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSet('description', 'Great coffee')
        ->set('city', 'Decatur')
        ->set('state', 'GA')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->description)->toBe('**Great** coffee')
        ->and($business->fresh()->has_rich_description)->toBeTrue();

    $business->update(['subscription_tier' => 'standard']);
    Livewire::test('business-details-modal')->call('open', $business->id)->assertSeeHtml('<strong>Great</strong>');
});

it('retains existing plain descriptions without interpreting markdown', function () {
    $business = descriptionBusiness('standard', 'Prices start at **$5**.');

    Livewire::actingAs($business->user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->set('city', 'Decatur')
        ->set('state', 'GA')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->has_rich_description)->toBeFalse();
    Livewire::test('business-details-modal')->call('open', $business->id)
        ->assertSee('Prices start at **$5**.')
        ->assertDontSeeHtml('<strong>$5</strong>');
});

it('allows Standard and Pro account plans to upload a logo on their first listing', function (string $tier) {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->forceFill(['pending_stripe_session_id' => 'pending', 'pending_plan_tier' => $tier])->save();
    $this->mock(BusinessSubscriptionManager::class)
        ->shouldReceive('claimPendingPlan')->once()->andReturnTrue();

    Livewire::actingAs($user)->test('add-business-modal')
        ->set('name', 'First Shop')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Our **shop**')
        ->set('website', 'https://example.com')
        ->set('image', UploadedFile::fake()->image('logo.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $business = $user->businesses()->firstOrFail();
    expect($business->has_rich_description)->toBeTrue()
        ->and($business->website)->toBe('https://example.com')
        ->and($business->image)->not->toBeNull();
    Storage::disk('public')->assertExists($business->image);
})->with(['standard', 'pro']);

it('gives additional Pro listings Standard features only while the owner plan is active', function () {
    Storage::fake('public');
    $primary = descriptionBusiness('pro');
    $this->mock(BusinessSubscriptionManager::class)
        ->shouldReceive('claimPendingPlan')->once()->andReturnFalse();

    Livewire::actingAs($primary->user)->test('add-business-modal')
        ->set('name', 'Second Shop')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Our **second** shop')
        ->set('website', 'https://example.com')
        ->set('image', UploadedFile::fake()->image('logo.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $second = $primary->user->businesses()->where('name', 'Second Shop')->firstOrFail();
    expect($second->subscription_tier)->toBe('free')
        ->and($second->has_rich_description)->toBeTrue()
        ->and($second->canAccessLogo())->toBeTrue()
        ->and($second->canAccessCustomUrl())->toBeTrue()
        ->and($second->canAccessDescription())->toBeTrue();

    Storage::disk('public')->assertExists($second->image);

    Livewire::actingAs($primary->user)->test('add-business-modal')
        ->call('editBusiness', $second->id)
        ->assertSee('Description formatting')
        ->assertSeeHtml('wire:model="image"')
        ->assertSeeHtml('wire:model.blur="website"');

    $second->update(['is_verified' => true]);
    Livewire::test('business-details-modal')->call('open', $second->id)
        ->assertSeeHtml('<strong>second</strong>');

    $primary->update(['subscription_status' => 'canceled', 'subscription_ends_at' => now()->subDay()]);
    $second = $second->fresh();
    expect($second->canAccessLogo())->toBeFalse()
        ->and($second->canAccessCustomUrl())->toBeFalse()
        ->and($second->canAccessDescription())->toBeFalse();
});
