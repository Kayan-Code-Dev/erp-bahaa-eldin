<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Subcategory;

class SubcategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subcategoriesByCategory = [
            'Men\'s Clothing' => [
                ['name' => 'Thobe/Dishdasha', 'description' => 'Traditional men\'s robe'],
                ['name' => 'Jalabiya', 'description' => 'Traditional Egyptian men\'s garment'],
                ['name' => 'Shirts', 'description' => 'Men\'s shirts'],
                ['name' => 'Pants', 'description' => 'Men\'s pants and trousers'],
                ['name' => 'Bisht', 'description' => 'Traditional cloak'],
            ],
            'Women\'s Clothing' => [
                ['name' => 'Abaya', 'description' => 'Women\'s outer garment'],
                ['name' => 'Kaftan', 'description' => 'Traditional women\'s dress'],
                ['name' => 'Jalabiya', 'description' => 'Women\'s traditional dress'],
                ['name' => 'Dresses', 'description' => 'Modern dresses'],
                ['name' => 'Skirts', 'description' => 'Women\'s skirts'],
            ],
            'Children\'s Clothing' => [
                ['name' => 'Boys\' Traditional', 'description' => 'Traditional clothing for boys'],
                ['name' => 'Girls\' Traditional', 'description' => 'Traditional clothing for girls'],
                ['name' => 'School Uniforms', 'description' => 'School uniforms for children'],
                ['name' => 'Casual Wear', 'description' => 'Casual clothing for children'],
            ],
            'Formal Wear' => [
                ['name' => 'Suits', 'description' => 'Business and formal suits'],
                ['name' => 'Tuxedos', 'description' => 'Formal tuxedos'],
                ['name' => 'Evening Gowns', 'description' => 'Formal evening wear'],
                ['name' => 'Wedding Attire', 'description' => 'Wedding clothing'],
            ],
            'Traditional Wear' => [
                ['name' => 'Eid Collection', 'description' => 'Special Eid clothing'],
                ['name' => 'Ramadan Collection', 'description' => 'Ramadan special clothing'],
                ['name' => 'National Dress', 'description' => 'National traditional clothing'],
            ],
            'Fabrics' => [
                ['name' => 'Cotton', 'description' => 'Cotton fabrics'],
                ['name' => 'Silk', 'description' => 'Silk fabrics'],
                ['name' => 'Linen', 'description' => 'Linen fabrics'],
                ['name' => 'Wool', 'description' => 'Wool fabrics'],
                ['name' => 'Synthetic', 'description' => 'Synthetic fabrics'],
            ],
            'Accessories' => [
                ['name' => 'Headwear', 'description' => 'Hats, caps, and traditional headwear'],
                ['name' => 'Belts', 'description' => 'Belts and sashes'],
                ['name' => 'Scarves', 'description' => 'Scarves and shawls'],
            ],
            'Uniforms' => [
                ['name' => 'Medical Uniforms', 'description' => 'Healthcare uniforms'],
                ['name' => 'Corporate Uniforms', 'description' => 'Business uniforms'],
                ['name' => 'Hospitality Uniforms', 'description' => 'Hotel and restaurant uniforms'],
                ['name' => 'Industrial Uniforms', 'description' => 'Factory and industrial wear'],
            ],
        ];

        $totalSubcategories = 0;

        foreach ($subcategoriesByCategory as $categoryName => $subcategories) {
            $category = Category::where('name', $categoryName)->first();

            if (!$category) {
                $this->command->warn("Category '{$categoryName}' not found. Skipping subcategories.");
                continue;
            }

            foreach ($subcategories as $subcategory) {
                Subcategory::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $subcategory['name'],
                    ],
                    ['description' => $subcategory['description']]
                );
                $totalSubcategories++;
            }
        }

        $this->command->info("Created {$totalSubcategories} subcategories.");
    }
}

