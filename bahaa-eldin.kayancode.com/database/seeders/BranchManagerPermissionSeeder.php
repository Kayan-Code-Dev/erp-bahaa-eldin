<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class BranchManagerPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        Permission::create(['name' => 'Read-Roles', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Read-Permissions', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Create-Permission', 'guard_name' => 'branchManager-api']);


        Permission::create(['name' => 'Create-Branch', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Read-Branches', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Update-Branch', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Delete-Branch', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Blocked-Branch', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Restore-BranchDeleted', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Read-DeletedBranches', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Force-BranchDeleted', 'guard_name' => 'branchManager-api']);


        Permission::create(['name' => 'Create-Employee', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Read-Employees', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Update-Employee', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Delete-Employee', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Blocked-Employee', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Read-DeletedEmployees', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Restore-Employee', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Force-Employee', 'guard_name' => 'branchManager-api']);

        Permission::create(['name' => 'Read-Inventories', 'guard_name' => 'branchManager-api']);



        Permission::create(['name' => 'Read-InventoryTransfers', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Create-InventoryTransfer', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Approve-InventoryTransfer', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Reject-InventoryTransfer', 'guard_name' => 'branchManager-api']);


        Permission::create(['name' => 'Read-Orders', 'guard_name' => 'branchManager-api']);
        Permission::create(['name' => 'Create-Order', 'guard_name' => 'branchManager-api']);
    }
}
