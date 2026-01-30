<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\User;
use App\Models\Cashbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 2000, 10000);
        $transportAllowance = fake()->randomFloat(2, 100, 500);
        $housingAllowance = fake()->randomFloat(2, 500, 2000);
        $otherAllowances = fake()->randomFloat(2, 0, 1000);
        $totalAllowances = $transportAllowance + $housingAllowance + $otherAllowances;
        
        $overtimeHours = fake()->randomFloat(2, 0, 20);
        $overtimeRate = fake()->randomFloat(2, 10, 50);
        $overtimeAmount = $overtimeHours * $overtimeRate;
        
        $ordersCount = fake()->numberBetween(0, 50);
        $ordersTotal = fake()->randomFloat(2, 0, 50000);
        $commissionRate = fake()->randomFloat(2, 0, 10);
        $commissionAmount = ($ordersTotal * $commissionRate) / 100;
        
        $workingDays = fake()->numberBetween(20, 30);
        $daysPresent = fake()->numberBetween(18, $workingDays);
        $daysAbsent = $workingDays - $daysPresent;
        $daysLate = fake()->numberBetween(0, 5);
        $leaveDays = fake()->numberBetween(0, 5);
        
        $absenceDeductions = $daysAbsent * ($baseSalary / $workingDays);
        $lateDeductions = fake()->randomFloat(2, 0, 200);
        $penaltyDeductions = fake()->randomFloat(2, 0, 500);
        $otherDeductions = fake()->randomFloat(2, 0, 300);
        $totalDeductions = $absenceDeductions + $lateDeductions + $penaltyDeductions + $otherDeductions;
        
        $grossSalary = $baseSalary + $totalAllowances + $overtimeAmount + $commissionAmount;
        $netSalary = $grossSalary - $totalDeductions;
        
        $periodStart = fake()->dateTimeBetween('-3 months', 'now');
        $periodEnd = (clone $periodStart)->modify('+1 month -1 day');
        
        return [
            'employee_id' => Employee::factory(),
            'period' => $periodStart->format('Y-m'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'base_salary' => $baseSalary,
            'transport_allowance' => $transportAllowance,
            'housing_allowance' => $housingAllowance,
            'other_allowances' => $otherAllowances,
            'total_allowances' => $totalAllowances,
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate,
            'overtime_amount' => $overtimeAmount,
            'orders_count' => $ordersCount,
            'orders_total' => $ordersTotal,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'working_days' => $workingDays,
            'days_present' => $daysPresent,
            'days_absent' => $daysAbsent,
            'days_late' => $daysLate,
            'leave_days' => $leaveDays,
            'absence_deductions' => $absenceDeductions,
            'late_deductions' => $lateDeductions,
            'penalty_deductions' => $penaltyDeductions,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => User::factory(),
            'submitted_by' => null,
            'approved_by' => null,
            'paid_by' => null,
            'cancelled_by' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'cashbox_id' => null,
            'transaction_id' => null,
            'payment_method' => null,
            'payment_reference' => null,
            'notes' => fake()->optional()->sentence(),
            'rejection_reason' => null,
        ];
    }

    /**
     * Indicate that the payroll is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payroll::STATUS_APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the payroll is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payroll::STATUS_PAID,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(1),
            'paid_by' => User::factory(),
            'paid_at' => now(),
            'cashbox_id' => Cashbox::factory(),
        ]);
    }

    /**
     * Indicate that the payroll is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payroll::STATUS_PENDING,
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ]);
    }
}



