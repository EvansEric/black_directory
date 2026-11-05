<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Geocoder;
use Illuminate\Console\Command;

class GeocodeBusinesses extends Command
{
    protected $signature = 'businesses:geocode {--all : Re-geocode businesses that already have coordinates}';

    protected $description = 'Look up latitude/longitude for listings so they can be sorted and filtered by distance';

    public function handle(Geocoder $geocoder): int
    {
        $query = Business::query()->when(! $this->option('all'), fn ($q) => $q->whereNull('latitude'));
        $found = 0;

        foreach ($query->cursor() as $business) {
            $coordinates = $geocoder->locate($business);

            if ($coordinates) {
                $business->forceFill($coordinates)->save();
                $found++;
            } else {
                $this->warn("No match for #{$business->id} {$business->name}");
            }

            sleep(1); // Nominatim allows one request per second.
        }

        $this->info("Geocoded {$found} business(es).");

        return self::SUCCESS;
    }
}
