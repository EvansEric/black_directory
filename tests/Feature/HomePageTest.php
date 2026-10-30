<?php

it('renders a sidebar advertisement area on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Advertisement')
        ->assertSee('directory-sidebar')
        ->assertSee('Your ad could be here');
});
