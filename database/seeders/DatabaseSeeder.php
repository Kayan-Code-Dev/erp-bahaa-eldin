<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions and roles first (required for other seeders)
        $this->call(PermissionSeeder::class);

        // Create test user (use firstOrCreate to avoid duplicates)
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );

        // Seed admin user
        $this->call(AdminUserSeeder::class);

        // Seed location data
        $this->call(CountrySeeder::class);
        $this->call(CitySeeder::class);

        // Seed categories and subcategories
        $this->call(CategorySeeder::class);
        $this->call(SubcategorySeeder::class);

        // Seed cloth types
        $this->call(ClothTypeSeeder::class);

        // Seed branches (depends on cities)
        $this->call(BranchSeeder::class);

        // Seed inventories for branches, workshops, factories
        $this->call(InventorySeeder::class);
    }
}
