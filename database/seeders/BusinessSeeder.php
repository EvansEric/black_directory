<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Seed the businesses table.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();
        $categories = [
            'Dining & Food',
            'Beauty & Barber',
            'Professional Services',
            'Retail & Fashion',
            'Health & Wellness',
            'Arts & Creative',
            'Automotive & Transport',
            'Home & Trades',
            'Real Estate & Property',
            'Pet Care & Services',
            'Cleaning & Maintenance',
            'IT & Tech Solutions',
            'Legal & Financial',
            'Insurance',
            'Security & Protection Services',
            'Marketing & Media',
            'Education & Instruction',
            'Travel & Lodging',
            'Sports & Recreation',
            'Entertainment & Events',
            'Non-Profit & Community',
        ];

        foreach (range(1, 10) as $index) {
            $city = fake()->city();
            $state = fake()->stateAbbr();

            Business::create([
                'user_id' => $user->id,
                'name' => fake()->unique()->company(),
                'category' => fake()->randomElement($categories),
                'location' => "{$city}, {$state}",
                'city' => $city,
                'state' => $state,
                'address' => fake()->streetAddress(),
                'zip' => fake()->postcode(),
                'phone' => fake()->phoneNumber(),
                'website' => fake()->url(),
                'facebook_url' => fake()->url(),
                'instagram_url' => fake()->url(),
                'x_url' => fake()->url(),
                'tiktok_url' => fake()->url(),
                'description' => fake()->paragraph(),
                'featured' => $index <= 2,
                'is_verified' => fake()->boolean(),
                'tags' => fake()->words(3),
                'free_parking' => fake()->boolean(),
            ]);
        }
    }
}
