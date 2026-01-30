<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = array_keys(Attendance::STATUSES);
        $checkIn = fake()->dateTimeBetween('today 08:00', 'today 10:00');
        $checkOut = fake()->dateTimeBetween('today 16:00', 'today 18:00');
        $hoursWorked = ($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 3600;
        
        return [
            'employee_id' => Employee::factory(),
            'branch_id' => Branch::factory(),
            'date' => fake()->date(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'hours_worked' => round($hoursWorked, 2),
            'overtime_hours' => fake()->optional()->randomFloat(2, 0, 4),
            'is_late' => fake()->boolean(20),
            'late_minutes' => null,
            'is_early_departure' => fake()->boolean(10),
            'early_departure_minutes' => null,
            'status' => Attendance::STATUS_PRESENT,
            'notes' => fake()->optional()->sentence(),
            'check_in_ip' => fake()->optional()->ipv4(),
            'check_out_ip' => fake()->optional()->ipv4(),
        ];
    }

    /**
     * Indicate that the attendance is absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Attendance::STATUS_ABSENT,
            'check_in' => null,
            'check_out' => null,
            'hours_worked' => 0,
        ]);
    }

    /**
     * Indicate that the attendance is late.
     */
    public function late(): static
    {
        return $this->state(function (array $attributes) {
            $checkIn = fake()->dateTimeBetween('today 10:00', 'today 11:00');
            $checkOut = fake()->dateTimeBetween('today 17:00', 'today 18:00');
            $hoursWorked = ($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 3600;
            
            return [
                'is_late' => true,
                'late_minutes' => fake()->numberBetween(5, 60),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'hours_worked' => round($hoursWorked, 2),
            ];
        });
    }
}



