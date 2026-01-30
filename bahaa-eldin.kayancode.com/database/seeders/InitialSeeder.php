<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Country;
use App\Models\City;
use App\Models\Admin;
use App\Models\Branch;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class InitialSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إضافة الدولة مصر
        $country = Country::create([
            'name'            => 'مصر',
            'code'            => 'EG',
            'currency_name'   => 'الجنيه المصري',
            'currency_symbol' => 'E£',
            'image'           => 'flags/egypt.png', // ضع مسار مناسب أو null
            'description'     => 'جمهورية مصر العربية',
            'active'          => true,
        ]);

        // 2. إضافة مدينة القاهرة
        $city = City::create([
            'name'       => 'القاهرة',
            'latitude'   => 30.0444,
            'longitude'  => 31.2357,
            'code'       => 'CAI',
            'country_id' => $country->id,
            'active'     => true,
        ]);

        // 3. إضافة مشرف أساسي
        $admin = Admin::create([
            'uuid'       => (string) Str::uuid(),
            'first_name' => 'Super',
            'last_name'  => 'Admin',
            'email'      => 'admin@system.com',
            'phone'      => '+201000000000',
            'password'   => Hash::make('password123'), // غيرها بعدين
            'id_number'  => 'EG123456789',
            'city_id'    => $city->id,
            'status'     => 'active',
            'ip_address' => '127.0.0.1',
        ]);
        $admin->assignRole('المشرف العام');
    }


    public function givPermission()
    {
        $admin = Admin::find(1);
        $Permissions = Permission::all();
        $allPermission = [];
        foreach ($Permissions as $Permission) {
            array_push($allPermission, $Permission->name);
        }
        $admin->givePermissionTo($allPermission);
    }
}
