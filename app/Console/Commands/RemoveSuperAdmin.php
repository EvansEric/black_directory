<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:remove-super-admin')]
#[Description('Command description')]
class RemoveSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
