<?php

namespace App\Console\Commands;

use App\Services\SubscriptionGating;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

class SyncStripePrices extends Command
{
    protected $signature = 'stripe:sync-prices {--dry-run : Show the prices that would be created without calling Stripe}';

    protected $description = 'Create Stripe prices matching the configured subscription tiers and print the env values to set';

    private const CYCLES = [
        'monthly' => ['key' => 'monthly_price', 'interval' => 'month'],
        'annual' => ['key' => 'annual_price', 'interval' => 'year'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $lines = [];

        foreach (SubscriptionGating::TIERS as $tier => $definition) {
            if (! isset($definition['stripe_monthly_price_id'])) {
                continue;
            }

            foreach (self::CYCLES as $cycle => $settings) {
                $cents = (int) round($definition[$settings['key']] * 100);
                $envKey = strtoupper("STRIPE_PRICE_{$tier}_{$cycle}");

                if ($dryRun) {
                    $this->line("{$envKey}: would create {$definition['name']} {$cycle} at {$cents} cents");

                    continue;
                }

                $price = Cashier::stripe()->prices->create([
                    'currency' => 'usd',
                    'unit_amount' => $cents,
                    'recurring' => ['interval' => $settings['interval']],
                    'product_data' => ['name' => "Noir Directory {$definition['name']}"],
                    'metadata' => ['tier' => $tier, 'cycle' => $cycle],
                ]);

                $lines[] = "{$envKey}={$price->id}";
            }
        }

        if ($lines !== []) {
            $this->info('Add these to your .env, then run php artisan config:clear:');
            $this->line(implode(PHP_EOL, $lines));
        }

        return self::SUCCESS;
    }
}
