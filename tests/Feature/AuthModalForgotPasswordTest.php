<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('sends a reset link from the auth modal', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $component = Livewire::test('auth-modal')
        ->set('forgotEmail', $user->email)
        ->call('requestPasswordReset');

    Notification::assertSentTo($user, ResetPassword::class);
    $component->assertSet('forgotMessage', 'If an account exists for that email, we have sent a password reset link.');
});
