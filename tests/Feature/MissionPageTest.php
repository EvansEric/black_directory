<?php

it('renders the mission page from its named route', function () {
    $this->get(route('mission'))
        ->assertOk()
        ->assertSee('When Black-owned businesses thrive')
        ->assertSee('Our mission is bigger than a listing.');
});