<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employmentTypes = array_keys(Employee::EMPLOYMENT_TYPES);
        $employmentStatuses = array_keys(Employee::EMPLOYMENT_STATUSES);
        
        return [
            'user_id' => User::factory(),
            'employee_code' => fake()->unique()->bothify('EMP-###'),
            'department_id' => Department::factory(),
            'job_title_id' => null,
            'manager_id' => null,
            'employment_type' => fake()->randomElement($employmentTypes),
            'employment_status' => Employee::STATUS_ACTIVE,
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'termination_date' => null,
            'probation_end_date' => null,
            'base_salary' => fake()->randomFloat(2, 2000, 10000),
            'transport_allowance' => fake()->randomFloat(2, 0, 500),
            'housing_allowance' => fake()->randomFloat(2, 0, 2000),
            'other_allowances' => fake()->randomFloat(2, 0, 1000),
            'overtime_rate' => fake()->randomFloat(2, 10, 50),
            'commission_rate' => fake()->randomFloat(2, 0, 10),
            'vacation_days_balance' => fake()->numberBetween(0, 30),
            'vacation_days_used' => fake()->numberBetween(0, 20),
            'annual_vacation_days' => fake()->numberBetween(20, 30),
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00',
            'work_hours_per_day' => 8,
            'late_threshold_minutes' => 15,
            'bank_name' => fake()->optional()->company(),
            'bank_account_number' => fake()->optional()->numerify('##########'),
            'bank_iban' => null,
            'emergency_contact_name' => fake()->optional()->name(),
            'emergency_contact_phone' => fake()->optional()->phoneNumber(),
            'emergency_contact_relation' => fake()->optional()->randomElement(['spouse', 'parent', 'sibling', 'friend']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the employee is terminated.
     */
    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => Employee::STATUS_TERMINATED,
            'termination_date' => fake()->dateTimeBetween($attributes['hire_date'] ?? '-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the employee is on leave.
     */
    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => Employee::STATUS_ON_LEAVE,
        ]);
    }
}

