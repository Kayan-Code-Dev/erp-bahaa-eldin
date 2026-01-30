<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory, SerializesDates, LogsActivity;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'date',
        'check_in',
        'check_out',
        'hours_worked',
        'overtime_hours',
        'is_late',
        'late_minutes',
        'is_early_departure',
        'early_departure_minutes',
        'status',
        'notes',
        'check_in_ip',
        'check_out_ip',
    ];

    protected $casts = [
        'date' => 'date',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'is_late' => 'boolean',
        'late_minutes' => 'integer',
        'is_early_departure' => 'boolean',
        'early_departure_minutes' => 'integer',
    ];

    // Status constants
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_WEEKEND = 'weekend';
    public const STATUS_LEAVE = 'leave';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_HALF_DAY => 'Half Day',
        self::STATUS_HOLIDAY => 'Holiday',
        self::STATUS_WEEKEND => 'Weekend',
        self::STATUS_LEAVE => 'Leave',
    ];

    /**
     * Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Branch where attendance was recorded
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Scope by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope by date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope for month
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)
                     ->whereMonth('date', $month);
    }

    /**
     * Scope for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for present
     */
    public function scopePresent($query)
    {
        return $query->where('status', self::STATUS_PRESENT);
    }

    /**
     * Scope for absent
     */
    public function scopeAbsent($query)
    {
        return $query->where('status', self::STATUS_ABSENT);
    }

    /**
     * Scope for late arrivals
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Check if checked in
     */
    public function getIsCheckedInAttribute(): bool
    {
        return !is_null($this->check_in);
    }

    /**
     * Check if checked out
     */
    public function getIsCheckedOutAttribute(): bool
    {
        return !is_null($this->check_out);
    }

    /**
     * Get formatted check in time
     */
    public function getCheckInTimeAttribute(): ?string
    {
        return $this->check_in;
    }

    /**
     * Get formatted check out time
     */
    public function getCheckOutTimeAttribute(): ?string
    {
        return $this->check_out;
    }

    /**
     * Calculate hours worked from check in/out times
     */
    public function calculateHoursWorked(): float
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }

        $checkIn = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->check_in);
        $checkOut = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->check_out);

        return round($checkIn->diffInMinutes($checkOut) / 60, 2);
    }

    /**
     * Calculate overtime based on employee's work hours
     */
    public function calculateOvertime(): float
    {
        $standardHours = $this->employee->work_hours_per_day ?? 8;
        $overtime = $this->hours_worked - $standardHours;
        
        return max(0, round($overtime, 2));
    }

    /**
     * Calculate late minutes based on employee's work start time
     */
    public function calculateLateMinutes(): int
    {
        if (!$this->check_in) {
            return 0;
        }

        $workStartTime = $this->employee->work_start_time ?? '09:00:00';
        $threshold = $this->employee->late_threshold_minutes ?? 15;

        $expectedStart = Carbon::parse($this->date->format('Y-m-d') . ' ' . $workStartTime);
        $actualStart = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->check_in);

        $lateMinutes = $actualStart->diffInMinutes($expectedStart, false);

        // Only count as late if beyond threshold
        if ($lateMinutes > $threshold) {
            return $lateMinutes;
        }

        return 0;
    }

    /**
     * Record check-in for employee
     */
    public static function checkIn(Employee $employee, ?int $branchId = null, ?string $ip = null): self
    {
        $attendance = self::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => today(),
            ],
            [
                'branch_id' => $branchId,
                'status' => self::STATUS_PRESENT,
            ]
        );

        $attendance->check_in = now()->format('H:i:s');
        $attendance->check_in_ip = $ip;
        $attendance->late_minutes = $attendance->calculateLateMinutes();
        $attendance->is_late = $attendance->late_minutes > 0;
        $attendance->save();

        return $attendance;
    }

    /**
     * Record check-out for employee
     */
    public static function checkOut(Employee $employee, ?string $ip = null): ?self
    {
        $attendance = self::where('employee_id', $employee->id)
                          ->whereDate('date', today())
                          ->first();

        if (!$attendance) {
            return null;
        }

        $attendance->check_out = now()->format('H:i:s');
        $attendance->check_out_ip = $ip;
        $attendance->hours_worked = $attendance->calculateHoursWorked();
        $attendance->overtime_hours = $attendance->calculateOvertime();

        // Check for early departure
        $workEndTime = $employee->work_end_time ?? '17:00:00';
        $expectedEnd = Carbon::parse(today()->format('Y-m-d') . ' ' . $workEndTime);
        $actualEnd = Carbon::parse(today()->format('Y-m-d') . ' ' . $attendance->check_out);
        
        if ($actualEnd->lt($expectedEnd)) {
            $attendance->is_early_departure = true;
            $attendance->early_departure_minutes = $expectedEnd->diffInMinutes($actualEnd);
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * Get monthly summary for employee
     */
    public static function getMonthlySummary(int $employeeId, int $year, int $month): array
    {
        $attendances = self::forEmployee($employeeId)
                           ->forMonth($year, $month)
                           ->get();

        return [
            'total_days' => $attendances->count(),
            'present_days' => $attendances->where('status', self::STATUS_PRESENT)->count(),
            'absent_days' => $attendances->where('status', self::STATUS_ABSENT)->count(),
            'half_days' => $attendances->where('status', self::STATUS_HALF_DAY)->count(),
            'leave_days' => $attendances->where('status', self::STATUS_LEAVE)->count(),
            'late_days' => $attendances->where('is_late', true)->count(),
            'total_hours_worked' => $attendances->sum('hours_worked'),
            'total_overtime_hours' => $attendances->sum('overtime_hours'),
            'total_late_minutes' => $attendances->sum('late_minutes'),
        ];
    }
}


