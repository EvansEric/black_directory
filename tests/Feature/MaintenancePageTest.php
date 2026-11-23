<?php

it('shows a branded maintenance page for service unavailable responses', function () {
    $response = view('errors.503')->render();

    expect($response)->toContain('Noir Directory')
        ->toContain('We’ll be right back.')
        ->toContain('Scheduled maintenance is in progress')
        ->toContain('Try again');
});
