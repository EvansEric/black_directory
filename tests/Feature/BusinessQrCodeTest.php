<?php

use App\Models\Business;
use App\Models\User;
use App\Services\QrCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function paidListing(): array
{
    $owner = User::factory()->create();
    $business = Business::create([
        'is_verified' => true,
        'user_id' => $owner->id, 'name' => 'Qr Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
        'subscription_tier' => 'standard', 'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth(),
    ]);

    return [$owner, $business];
}

it('generates an svg only for http(s) urls', function () {
    $qr = new QrCodeGenerator;

    expect($qr->svg('https://example.com'))->toContain('<svg')
        ->and($qr->svg('javascript:alert(1)'))->toBeNull()
        ->and($qr->svg('not a url'))->toBeNull()
        ->and($qr->svg(null))->toBeNull();
});

it('shows a QR code once a website is entered while editing a listing', function () {
    [$owner, $business] = paidListing();

    Livewire::actingAs($owner)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertDontSeeHtml('data-qr-code')
        ->set('website', 'https://example.com')
        ->assertSeeHtml('data-qr-code')
        ->assertSee('Download SVG')
        ->set('website', '')
        ->assertDontSeeHtml('data-qr-code');
});

it('encodes the custom link when one is set', function () {
    [$owner, $business] = paidListing();

    $component = Livewire::actingAs($owner)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->set('website', 'https://example.com')
        ->set('customSlug', 'qr-shop');

    $withSlug = $component->instance()->qrCodeSvg();
    $component->set('customSlug', '');

    expect($withSlug)->not->toBe($component->instance()->qrCodeSvg());
});

it('shows no QR code to free listings', function () {
    $owner = User::factory()->create();
    $business = Business::create([
        'is_verified' => true,
        'user_id' => $owner->id, 'name' => 'Free', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
    ]);

    Livewire::actingAs($owner)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertDontSeeHtml('data-qr-code');
});
