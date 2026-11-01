<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function fakeGoogleUser(string $id, string $email, bool $verified = true): SocialiteUser
{
    $user = (new SocialiteUser)->map(['id' => $id, 'name' => 'Gina Google', 'email' => $email]);
    $user->user = ['email_verified' => $verified];

    return $user;
}

function mockGoogle(SocialiteUser $user): void
{
    Socialite::shouldReceive('driver->user')->andReturn($user);
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'id',
        'services.google.client_secret' => 'secret',
    ]);
});

it('shows the Google button in the auth modal', function () {
    $this->get(route('home'))->assertSee('data-google-auth', false);
});

it('explains that Google sign-in is not set up when credentials are missing', function () {
    config(['services.google.client_secret' => null]);

    $this->get(route('auth.google'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'Google sign-in is not set up yet.');
});

it('redirects to Google', function () {
    $this->get(route('auth.google'))
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

it('creates and logs in a new user from Google', function () {
    mockGoogle(fakeGoogleUser('g-1', 'new@example.com'));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->google_id)->toBe('g-1')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('logs in an existing Google user without creating a duplicate', function () {
    $user = User::factory()->create(['google_id' => 'g-2', 'email' => 'back@example.com']);
    mockGoogle(fakeGoogleUser('g-2', 'back@example.com'));

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('links Google to an existing account with a verified email', function () {
    $user = User::factory()->create(['email' => 'link@example.com']);
    mockGoogle(fakeGoogleUser('g-3', 'link@example.com'));

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-3');
});

it('refuses to link an existing account when Google has not verified the email', function () {
    $user = User::factory()->create(['email' => 'unsafe@example.com']);
    mockGoogle(fakeGoogleUser('g-4', 'unsafe@example.com', verified: false));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
});
