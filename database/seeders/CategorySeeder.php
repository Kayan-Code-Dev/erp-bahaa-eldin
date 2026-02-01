<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Men\'s Clothing',
                'description' => 'Traditional and modern men\'s clothing',
            ],
            [
                'name' => 'Women\'s Clothing',
                'description' => 'Traditional and modern women\'s clothing',
            ],
            [
                'name' => 'Children\'s Clothing',
                'description' => 'Clothing for children of all ages',
            ],
            [
                'name' => 'Formal Wear',
                'description' => 'Suits, tuxedos, and formal attire',
            ],
            [
                'name' => 'Traditional Wear',
                'description' => 'Traditional and cultural garments',
            ],
            [
                'name' => 'Fabrics',
                'description' => 'Raw fabrics and materials',
            ],
            [
                'name' => 'Accessories',
                'description' => 'Clothing accessories and add-ons',
            ],
            [
                'name' => 'Uniforms',
                'description' => 'Work and school uniforms',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }

        $this->command->info('Created ' . count($categories) . ' categories.');
    }
}

