<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class BranchPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Permission::create(['name' => 'Create-Role', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Roles', 'guard_name' => 'branch-api']);


        // Permission::create(['name' => 'Create-Permission', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Permissions', 'guard_name' => 'branch-api']);


        // Permission::create(['name' => 'Create-Department', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Departments', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Update-Department', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Delete-Department', 'guard_name' => 'branch-api']);

      
        Permission::create(['name' => 'Create-BranchJob', 'guard_name' => 'branch-api']);
        Permission::create(['name' => 'Read-BranchJobs', 'guard_name' => 'branch-api']);
        Permission::create(['name' => 'Update-BranchJob', 'guard_name' => 'branch-api']);
        Permission::create(['name' => 'Delete-BranchJob', 'guard_name' => 'branch-api']);



        // Permission::create(['name' => 'Create-Employee', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Employees', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Update-Employee', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Delete-Employee', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Blocked-Employee', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-DeletedEmployees', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Restore-Employee', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Force-Employee', 'guard_name' => 'branch-api']);


        // Permission::create(['name' => 'Create-Category', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Categories', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Update-Category', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Delete-Category', 'guard_name' => 'branch-api']);

        // Permission::create(['name' => 'Create-SubCategory', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-SubCategories', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Update-SubCategory', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Delete-SubCategory', 'guard_name' => 'branch-api']);


        // Permission::create(['name' => 'Create-Inventory', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Read-Inventories', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Update-Inventory', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Delete-Inventory', 'guard_name' => 'branch-api']);



        // Permission::create(['name' => 'Read-InventoryTransfers', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Create-InventoryTransfer', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Approve-InventoryTransfer', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Reject-InventoryTransfer', 'guard_name' => 'branch-api']);


        // Permission::create(['name' => 'Read-Orders', 'guard_name' => 'branch-api']);
        // Permission::create(['name' => 'Create-Order', 'guard_name' => 'branch-api']);
    }
}
