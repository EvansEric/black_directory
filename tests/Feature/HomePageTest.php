<?php

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
