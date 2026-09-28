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
            'Marketing & Media',
            'Education & Instruction',
            'Travel & Lodging',
            'Sports & Recreation',
            'Entertainment & Events',
            'Non-Profit & Community',
        ];

        foreach (range(1, 10) as $index) {
            Business::create([
                'user_id' => $user->id,
                'name' => fake()->unique()->company(),
                'category' => fake()->randomElement($categories),
                'location' => fake()->city(),
                'address' => fake()->streetAddress(),
                'zip' => fake()->postcode(),
                'phone' => fake()->phoneNumber(),
                'website' => fake()->url(),
                'description' => fake()->paragraph(),
                'rating' => fake()->randomFloat(1, 3.5, 5),
                'reviews_count' => fake()->numberBetween(0, 250),
                'featured' => $index <= 2,
                'tags' => fake()->words(3),
            ]);
        }
    }
}
