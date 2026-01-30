<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $allAdminPer = Permission::where('guard_name', 'admin-api')->get();
        Role::create(['guard_name' => 'admin-api', 'name' => 'المشرف العام'])->givePermissionTo($allAdminPer);
    }
}
