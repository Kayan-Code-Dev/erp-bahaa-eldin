<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Custody;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustodyPhoto>
 */
class CustodyPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custody_id' => Custody::factory(),
            'photo_path' => 'custody-photos/' . $this->faker->uuid() . '.jpg',
            'photo_type' => 'custody_photo',
        ];
    }
}


