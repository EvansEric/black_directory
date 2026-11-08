<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;

class VerifyBusiness extends Command
{
    protected $signature = 'business:verify {id : ID of the business} {--revoke : Remove verification instead}';

    protected $description = 'Verify a business listing so it becomes publicly visible';

    public function handle(): int
    {
        $business = Business::query()->find($this->argument('id'));

        if (! $business) {
            $this->error('Business not found.');

            return self::FAILURE;
        }

        $business->is_verified = ! $this->option('revoke');
        $business->save();

        $this->info("{$business->name} is now ".($business->is_verified ? 'verified.' : 'unverified.'));

        return self::SUCCESS;
    }
}
