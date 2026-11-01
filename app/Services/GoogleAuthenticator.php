<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthenticator
{
    /**
     * Find the user for a Google account, linking by verified email or creating one.
     */
    public function resolveUser(SocialiteUser $google): User
    {
        $user = User::query()->where('google_id', $google->getId())->first();

        if ($user) {
            return $user;
        }

        $email = (string) $google->getEmail();
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            // Only link an existing password account when Google vouches for the email.
            if (! $this->emailIsVerified($google)) {
                throw new \DomainException('Your Google email address is not verified.');
            }

            $user->forceFill(['google_id' => $google->getId()])->save();

            return $user;
        }

        $user = new User([
            'name' => $google->getName() ?: Str::before($email, '@'),
            'email' => $email,
            // Social accounts have no password; reset flow can set one later.
            'password' => Str::random(40),
        ]);
        $user->forceFill([
            'google_id' => $google->getId(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function emailIsVerified(SocialiteUser $google): bool
    {
        $raw = method_exists($google, 'getRaw') ? $google->getRaw() : ($google->user ?? []);

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }
}
