<?php

use Livewire\Livewire;

it('explains listing verification, renewal, cancellation, and verification removal', function () {
    $this->get(route('listing-disclosure'))
        ->assertOk()
        ->assertSee('Listing and subscription disclosure')
        ->assertSee('A listing must be verified before its owner can start a paid plan.')
        ->assertSee('renew on the selected monthly or annual billing cycle until canceled')
        ->assertSee('selecting the Free plan')
        ->assertSee('does not automatically cancel an existing subscription');
});

it('links to the disclosure from the footer, pricing page, and plan dialog', function () {
    $url = route('listing-disclosure');

    $this->get(route('home'))->assertSee('href="'.$url.'"', false);
    $this->get(route('pricing'))->assertSee('href="'.$url.'"', false);
    Livewire::test('subscription-modal')
        ->assertSee('href="'.$url.'"', false)
        ->call('selectTier', 'standard')
        ->assertSee('renew on the selected billing cycle until canceled');
});
