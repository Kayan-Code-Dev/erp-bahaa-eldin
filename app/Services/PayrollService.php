<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Attendance;
use App\Models\Deduction;
use App\Models\Order;
use App\Models\Cashbox;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * PayrollService
 * 
 * Handles payroll generation, calculation, and payment processing
 * with full integration to the accounting system.
 */
class PayrollService
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Generate payroll for an employee for a specific period
     */
    public function generatePayroll(Employee $employee, string $period, User $generatedBy): Payroll
    {
        // Check if payroll already exists
        $existing = Payroll::where('employee_id', $employee->id)
                           ->where('period', $period)
                           ->whereNotIn('status', [Payroll::STATUS_CANCELLED])
                           ->first();

        if ($existing) {
            throw new \Exception("Payroll for period {$period} already exists for this employee.");
        }

        // Parse period
        [$year, $month] = explode('-', $period);
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

        return DB::transaction(function () use ($employee, $period, $periodStart, $periodEnd, $generatedBy) {
            // Get attendance summary
            $attendanceSummary = Attendance::getMonthlySummary(
                $employee->id, 
                $periodStart->year, 
                $periodStart->month
            );

            // Get orders for commission calculation
            $orders = $this->getEmployeeOrders($employee, $periodStart, $periodEnd);
            $commissionData = $this->calculateCommission($employee, $orders);

            // Get unapplied deductions for this period
            $deductions = Deduction::forEmployee($employee->id)
                                   ->forPeriod($period)
                                   ->unapplied()
                                   ->get();

            // Calculate deduction totals by type
            $deductionsByType = $this->calculateDeductionsByType($deductions);

            // Create payroll record
            $payroll = Payroll::create([
                'employee_id' => $employee->id,
                'period' => $period,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                
                // Salary components from employee
                'base_salary' => $employee->base_salary,
                'transport_allowance' => $employee->transport_allowance,
                'housing_allowance' => $employee->housing_allowance,
                'other_allowances' => $employee->other_allowances,
                'total_allowances' => $employee->transport_allowance + 
                                       $employee->housing_allowance + 
                                       $employee->other_allowances,
                
                // Overtime from attendance
                'overtime_hours' => $attendanceSummary['total_overtime_hours'],
                'overtime_rate' => $employee->overtime_rate,
                'overtime_amount' => $attendanceSummary['total_overtime_hours'] * 
                                      $employee->hourly_salary_rate * 
                                      $employee->overtime_rate,
                
                // Commission from orders
                'orders_count' => $commissionData['count'],
                'orders_total' => $commissionData['total'],
                'commission_rate' => $employee->commission_rate,
                'commission_amount' => $commissionData['commission'],
                
                // Attendance summary
                'working_days' => cal_days_in_month(CAL_GREGORIAN, $periodStart->month, $periodStart->year),
                'days_present' => $attendanceSummary['present_days'],
                'days_absent' => $attendanceSummary['absent_days'],
                'days_late' => $attendanceSummary['late_days'],
                'leave_days' => $attendanceSummary['leave_days'],
                
                // Deductions
                'absence_deductions' => $deductionsByType['absence'],
                'late_deductions' => $deductionsByType['late'],
                'penalty_deductions' => $deductionsByType['penalty'],
                'other_deductions' => $deductionsByType['other'],
                'total_deductions' => array_sum($deductionsByType),
                
                'status' => Payroll::STATUS_DRAFT,
                'generated_by' => $generatedBy->id,
            ]);

            // Calculate totals
            $payroll->calculateTotals();
            $payroll->save();

            // Create payroll items for detailed breakdown
            $this->createPayrollItems($payroll, $employee, $attendanceSummary, $commissionData, $deductions);

            // Link deductions to this payroll
            foreach ($deductions as $deduction) {
                $deduction->update(['payroll_id' => $payroll->id]);
            }

            return $payroll->fresh(['employee.user', 'items']);
        });
    }

    /**
     * Generate payrolls for all active employees
     */
    public function generateBulkPayrolls(string $period, User $generatedBy, ?int $departmentId = null): array
    {
        $query = Employee::active();
        
        if ($departmentId) {
            $query->inDepartment($departmentId);
        }

        $employees = $query->get();
        $results = [
            'generated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($employees as $employee) {
            try {
                $this->generatePayroll($employee, $period, $generatedBy);
                $results['generated']++;
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $results['skipped']++;
                } else {
                    $results['errors'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Process payroll payment
     */
    public function processPayment(
        Payroll $payroll,
        Cashbox $cashbox,
        User $paidBy,
        string $paymentMethod = 'bank_transfer',
        ?string $paymentReference = null
    ): Payroll {
        if ($payroll->status !== Payroll::STATUS_APPROVED) {
            throw new \Exception('Only approved payrolls can be paid.');
        }

        return DB::transaction(function () use ($payroll, $cashbox, $paidBy, $paymentMethod, $paymentReference) {
            // Create expense transaction
            $transaction = $this->transactionService->recordExpense(
                $cashbox,
                $payroll->net_salary,
                Transaction::CATEGORY_SALARY_EXPENSE,
                "Salary payment for {$payroll->employee->name} - {$payroll->period}",
                $paidBy,
                Payroll::class,
                $payroll->id,
                [
                    'employee_id' => $payroll->employee_id,
                    'employee_code' => $payroll->employee->employee_code,
                    'period' => $payroll->period,
                    'payment_method' => $paymentMethod,
                ]
            );

            // Mark payroll as paid
            $payroll->markAsPaid(
                $paidBy->id,
                $cashbox->id,
                $transaction->id,
                $paymentMethod,
                $paymentReference
            );

            return $payroll->fresh(['employee.user', 'transaction', 'cashbox']);
        });
    }

    /**
     * Recalculate payroll (only for draft status)
     */
    public function recalculatePayroll(Payroll $payroll, User $updatedBy): Payroll
    {
        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            throw new \Exception('Only draft payrolls can be recalculated.');
        }

        $employee = $payroll->employee;

        return DB::transaction(function () use ($payroll, $employee) {
            // Get attendance summary
            $attendanceSummary = Attendance::getMonthlySummary(
                $employee->id,
                $payroll->period_start->year,
                $payroll->period_start->month
            );

            // Get orders for commission
            $orders = $this->getEmployeeOrders($employee, $payroll->period_start, $payroll->period_end);
            $commissionData = $this->calculateCommission($employee, $orders);

            // Get deductions
            $deductions = Deduction::forEmployee($employee->id)
                                   ->forPeriod($payroll->period)
                                   ->unapplied()
                                   ->get();
            $deductionsByType = $this->calculateDeductionsByType($deductions);

            // Update payroll
            $payroll->update([
                'base_salary' => $employee->base_salary,
                'transport_allowance' => $employee->transport_allowance,
                'housing_allowance' => $employee->housing_allowance,
                'other_allowances' => $employee->other_allowances,
                'total_allowances' => $employee->transport_allowance +
                                       $employee->housing_allowance +
                                       $employee->other_allowances,

                'overtime_hours' => $attendanceSummary['total_overtime_hours'],
                'overtime_rate' => $employee->overtime_rate,
                'overtime_amount' => $attendanceSummary['total_overtime_hours'] *
                                      $employee->hourly_salary_rate *
                                      $employee->overtime_rate,

                'orders_count' => $commissionData['count'],
                'orders_total' => $commissionData['total'],
                'commission_rate' => $employee->commission_rate,
                'commission_amount' => $commissionData['commission'],

                'days_present' => $attendanceSummary['present_days'],
                'days_absent' => $attendanceSummary['absent_days'],
                'days_late' => $attendanceSummary['late_days'],
                'leave_days' => $attendanceSummary['leave_days'],

                'absence_deductions' => $deductionsByType['absence'],
                'late_deductions' => $deductionsByType['late'],
                'penalty_deductions' => $deductionsByType['penalty'],
                'other_deductions' => $deductionsByType['other'],
                'total_deductions' => array_sum($deductionsByType),
            ]);

            $payroll->calculateTotals();
            $payroll->save();

            // Recreate payroll items
            $payroll->items()->delete();
            $this->createPayrollItems($payroll, $employee, $attendanceSummary, $commissionData, $deductions);

            // Update deduction links
            Deduction::where('payroll_id', $payroll->id)->update(['payroll_id' => null]);
            foreach ($deductions as $deduction) {
                $deduction->update(['payroll_id' => $payroll->id]);
            }

            return $payroll->fresh(['employee.user', 'items']);
        });
    }

    /**
     * Get orders created by employee in period
     */
    protected function getEmployeeOrders(Employee $employee, Carbon $start, Carbon $end)
    {
        return Order::where('created_by', $employee->user_id)
                    ->whereBetween('created_at', [$start, $end])
                    ->where('status', 'completed')
                    ->get();
    }

    /**
     * Calculate commission from orders
     */
    protected function calculateCommission(Employee $employee, $orders): array
    {
        $total = $orders->sum('total_amount');
        $commission = $total * ($employee->commission_rate / 100);

        return [
            'count' => $orders->count(),
            'total' => $total,
            'commission' => round($commission, 2),
        ];
    }

    /**
     * Calculate deductions by type
     */
    protected function calculateDeductionsByType($deductions): array
    {
        return [
            'absence' => $deductions->where('type', Deduction::TYPE_ABSENCE)->sum('amount'),
            'late' => $deductions->where('type', Deduction::TYPE_LATE)->sum('amount'),
            'penalty' => $deductions->where('type', Deduction::TYPE_PENALTY)->sum('amount'),
            'other' => $deductions->whereNotIn('type', [
                Deduction::TYPE_ABSENCE,
                Deduction::TYPE_LATE,
                Deduction::TYPE_PENALTY,
            ])->sum('amount'),
        ];
    }

    /**
     * Create payroll items for detailed breakdown
     */
    protected function createPayrollItems(
        Payroll $payroll,
        Employee $employee,
        array $attendanceSummary,
        array $commissionData,
        $deductions
    ): void {
        // Earnings
        PayrollItem::createEarning(
            $payroll->id,
            PayrollItem::CATEGORY_BASE_SALARY,
            'Base Salary',
            $employee->base_salary
        );

        if ($employee->transport_allowance > 0) {
            PayrollItem::createEarning(
                $payroll->id,
                PayrollItem::CATEGORY_TRANSPORT,
                'Transport Allowance',
                $employee->transport_allowance
            );
        }

        if ($employee->housing_allowance > 0) {
            PayrollItem::createEarning(
                $payroll->id,
                PayrollItem::CATEGORY_HOUSING,
                'Housing Allowance',
                $employee->housing_allowance
            );
        }

        if ($employee->other_allowances > 0) {
            PayrollItem::createEarning(
                $payroll->id,
                PayrollItem::CATEGORY_OTHER_ALLOWANCE,
                'Other Allowances',
                $employee->other_allowances
            );
        }

        if ($attendanceSummary['total_overtime_hours'] > 0) {
            $overtimeRate = $employee->hourly_salary_rate * $employee->overtime_rate;
            PayrollItem::createEarning(
                $payroll->id,
                PayrollItem::CATEGORY_OVERTIME,
                'Overtime Pay',
                $attendanceSummary['total_overtime_hours'] * $overtimeRate,
                $attendanceSummary['total_overtime_hours'],
                $overtimeRate
            );
        }

        if ($commissionData['commission'] > 0) {
            PayrollItem::createEarning(
                $payroll->id,
                PayrollItem::CATEGORY_COMMISSION,
                "Commission ({$commissionData['count']} orders)",
                $commissionData['commission'],
                $commissionData['count'],
                null,
                ['orders_total' => $commissionData['total'], 'rate' => $employee->commission_rate]
            );
        }

        // Deductions
        foreach ($deductions as $deduction) {
            $category = match ($deduction->type) {
                Deduction::TYPE_ABSENCE => PayrollItem::CATEGORY_ABSENCE,
                Deduction::TYPE_LATE => PayrollItem::CATEGORY_LATE,
                Deduction::TYPE_PENALTY => PayrollItem::CATEGORY_PENALTY,
                default => PayrollItem::CATEGORY_OTHER_DEDUCTION,
            };

            PayrollItem::createDeduction(
                $payroll->id,
                $category,
                $deduction->reason,
                $deduction->amount,
                1,
                null,
                ['deduction_id' => $deduction->id, 'date' => $deduction->date->format('Y-m-d')]
            );
        }
    }

    /**
     * Get payroll summary for period
     */
    public function getPeriodSummary(string $period): array
    {
        $payrolls = Payroll::where('period', $period)->with('employee')->get();

        return [
            'period' => $period,
            'total_payrolls' => $payrolls->count(),
            'by_status' => [
                'draft' => $payrolls->where('status', Payroll::STATUS_DRAFT)->count(),
                'pending' => $payrolls->where('status', Payroll::STATUS_PENDING)->count(),
                'approved' => $payrolls->where('status', Payroll::STATUS_APPROVED)->count(),
                'paid' => $payrolls->where('status', Payroll::STATUS_PAID)->count(),
                'cancelled' => $payrolls->where('status', Payroll::STATUS_CANCELLED)->count(),
            ],
            'totals' => [
                'gross_salary' => $payrolls->whereNotIn('status', [Payroll::STATUS_CANCELLED])->sum('gross_salary'),
                'total_deductions' => $payrolls->whereNotIn('status', [Payroll::STATUS_CANCELLED])->sum('total_deductions'),
                'net_salary' => $payrolls->whereNotIn('status', [Payroll::STATUS_CANCELLED])->sum('net_salary'),
                'paid_amount' => $payrolls->where('status', Payroll::STATUS_PAID)->sum('net_salary'),
            ],
        ];
    }
}

