<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AdminPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'Create-Role', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Roles', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-Role', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-Role', 'guard_name' => 'admin-api']);

        Permission::create(['name' => 'Create-Permission', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Permissions', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-Permission', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-Permission', 'guard_name' => 'admin-api']);

        Permission::create(['name' => 'Create-Admin', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Admins', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-Admin', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-Admin', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Blocked-Admin', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-DeletedAdmins', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Restore-AdminDeleted', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Force-AdminDeleted', 'guard_name' => 'admin-api']);



        Permission::create(['name' => 'Create-Country', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Countries', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-Country', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-Country', 'guard_name' => 'admin-api']);


        Permission::create(['name' => 'Create-City', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Cities', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-City', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-City', 'guard_name' => 'admin-api']);


        Permission::create(['name' => 'Create-BranchManager', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-BranchManagers', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-BranchManager', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-BranchManager', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Blocked-BranchManager', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-DeletedBranchManagers', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Restore-BranchManagerDeleted', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Force-BranchManagerDeleted', 'guard_name' => 'admin-api']);


        Permission::create(['name' => 'Create-Branch', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-Branches', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Update-Branch', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Delete-Branch', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Blocked-Branch', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Restore-BranchDeleted', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Read-DeletedBranches', 'guard_name' => 'admin-api']);
        Permission::create(['name' => 'Force-BranchDeleted', 'guard_name' => 'admin-api']);

        
    }
}
