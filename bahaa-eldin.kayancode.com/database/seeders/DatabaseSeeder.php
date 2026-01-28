<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();


        // $this->call(AdminPermissionSeeder::class);
        // $this->call(BranchManagerPermissionSeeder::class);
        // $this->call(BranchPermissionSeeder::class);
        $this->call(EmployeePermissionSeeder::class);
        // $this->call(RoleSeeder::class);
        // $this->call(InitialSeeder::class);
    }
}
