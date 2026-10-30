<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('renders the password reset form from its named route', function () {
    $this->get(route('password.reset', ['token' => 'reset-token', 'email' => 'user@example.com']))
        ->assertOk()
        ->assertSee('reset-token')
        ->assertSee('user@example.com');
});

it('resets the password with a valid reset token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertRedirect(route('home'));

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});
