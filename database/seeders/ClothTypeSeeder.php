<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClothType;

class ClothTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clothTypes = [
            [
                'code' => 'THB-001',
                'name' => 'Thobe',
                'description' => 'Traditional men\'s robe',
            ],
            [
                'code' => 'DSH-001',
                'name' => 'Dishdasha',
                'description' => 'Traditional Gulf men\'s garment',
            ],
            [
                'code' => 'JLB-001',
                'name' => 'Jalabiya',
                'description' => 'Traditional Egyptian garment',
            ],
            [
                'code' => 'ABY-001',
                'name' => 'Abaya',
                'description' => 'Women\'s outer garment',
            ],
            [
                'code' => 'KFT-001',
                'name' => 'Kaftan',
                'description' => 'Traditional dress',
            ],
            [
                'code' => 'BSH-001',
                'name' => 'Bisht',
                'description' => 'Traditional cloak',
            ],
            [
                'code' => 'SUT-001',
                'name' => 'Suit',
                'description' => 'Formal business suit',
            ],
            [
                'code' => 'TUX-001',
                'name' => 'Tuxedo',
                'description' => 'Formal evening wear',
            ],
            [
                'code' => 'WDG-001',
                'name' => 'Wedding Dress',
                'description' => 'Bridal wedding attire',
            ],
            [
                'code' => 'UNF-001',
                'name' => 'Uniform',
                'description' => 'Work or school uniform',
            ],
        ];

        foreach ($clothTypes as $clothType) {
            ClothType::firstOrCreate(
                ['code' => $clothType['code']],
                [
                    'name' => $clothType['name'],
                    'description' => $clothType['description'],
                ]
            );
        }

        $this->command->info('Created ' . count($clothTypes) . ' cloth types.');
    }
}

