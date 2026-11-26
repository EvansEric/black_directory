<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-super-admin {email : Email address of the user}')]
#[Description('Grant super admin privileges to a user')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true, 'is_super_admin' => true])->save();

        $this->info("{$user->email} is now a super admin.");

        return self::SUCCESS;
    }
}
