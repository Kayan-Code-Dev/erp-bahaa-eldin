<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'user_id',
        'employee_code',
        'department_id',
        'job_title_id',
        'manager_id',
        'employment_type',
        'employment_status',
        'hire_date',
        'termination_date',
        'probation_end_date',
        'base_salary',
        'transport_allowance',
        'housing_allowance',
        'other_allowances',
        'overtime_rate',
        'commission_rate',
        'vacation_days_balance',
        'vacation_days_used',
        'annual_vacation_days',
        'work_start_time',
        'work_end_time',
        'work_hours_per_day',
        'late_threshold_minutes',
        'bank_name',
        'bank_account_number',
        'bank_iban',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'probation_end_date' => 'date',
        'base_salary' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'vacation_days_balance' => 'integer',
        'vacation_days_used' => 'integer',
        'annual_vacation_days' => 'integer',
        'work_hours_per_day' => 'integer',
        'late_threshold_minutes' => 'integer',
    ];

    // Employment types
    public const TYPE_FULL_TIME = 'full_time';
    public const TYPE_PART_TIME = 'part_time';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INTERN = 'intern';

    public const EMPLOYMENT_TYPES = [
        self::TYPE_FULL_TIME => 'Full Time',
        self::TYPE_PART_TIME => 'Part Time',
        self::TYPE_CONTRACT => 'Contract',
        self::TYPE_INTERN => 'Intern',
    ];

    // Employment statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    public const EMPLOYMENT_STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ON_LEAVE => 'On Leave',
        self::STATUS_SUSPENDED => 'Suspended',
        self::STATUS_TERMINATED => 'Terminated',
    ];

    /**
     * User account for this employee
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Job title
     */
    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * Direct manager
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Direct reports (subordinates)
     */
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Branches where employee works
     */
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'employee_branch')
                    ->withPivot('is_primary', 'assigned_at', 'unassigned_at')
                    ->withTimestamps();
    }

    /**
     * Primary branch
     */
    public function primaryBranch()
    {
        return $this->branches()->wherePivot('is_primary', true)->first();
    }

    // ==================== ENTITY ASSIGNMENTS (POLYMORPHIC) ====================

    /**
     * Entity types for polymorphic assignments
     */
    public const ENTITY_TYPE_BRANCH = 'branch';
    public const ENTITY_TYPE_WORKSHOP = 'workshop';
    public const ENTITY_TYPE_FACTORY = 'factory';

    public const ENTITY_TYPES = [
        self::ENTITY_TYPE_BRANCH => 'Branch',
        self::ENTITY_TYPE_WORKSHOP => 'Workshop',
        self::ENTITY_TYPE_FACTORY => 'Factory',
    ];

    /**
     * Get all entity assignments (polymorphic)
     */
    public function entityAssignments()
    {
        return $this->hasMany(EmployeeEntity::class);
    }

    /**
     * Get assigned branches via polymorphic table
     */
    public function assignedBranches()
    {
        return Branch::whereIn('id', function ($query) {
            $query->select('entity_id')
                ->from('employee_entity')
                ->where('employee_id', $this->id)
                ->where('entity_type', self::ENTITY_TYPE_BRANCH)
                ->whereNull('unassigned_at');
        });
    }

    /**
     * Get assigned workshops via polymorphic table
     */
    public function assignedWorkshops()
    {
        return Workshop::whereIn('id', function ($query) {
            $query->select('entity_id')
                ->from('employee_entity')
                ->where('employee_id', $this->id)
                ->where('entity_type', self::ENTITY_TYPE_WORKSHOP)
                ->whereNull('unassigned_at');
        });
    }

    /**
     * Get assigned factories via polymorphic table
     */
    public function assignedFactories()
    {
        return Factory::whereIn('id', function ($query) {
            $query->select('entity_id')
                ->from('employee_entity')
                ->where('employee_id', $this->id)
                ->where('entity_type', self::ENTITY_TYPE_FACTORY)
                ->whereNull('unassigned_at');
        });
    }

    /**
     * Check if employee is assigned to a specific entity
     */
    public function isAssignedTo(string $entityType, int $entityId): bool
    {
        return $this->entityAssignments()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('unassigned_at')
            ->exists();
    }

    /**
     * Get all assigned entity IDs grouped by type
     */
    public function getAssignedEntities(): array
    {
        $assignments = $this->entityAssignments()
            ->whereNull('unassigned_at')
            ->get();

        $result = [
            self::ENTITY_TYPE_BRANCH => [],
            self::ENTITY_TYPE_WORKSHOP => [],
            self::ENTITY_TYPE_FACTORY => [],
        ];

        foreach ($assignments as $assignment) {
            $result[$assignment->entity_type][] = $assignment->entity_id;
        }

        return $result;
    }

    /**
     * Assign employee to an entity
     */
    public function assignToEntity(string $entityType, int $entityId, bool $isPrimary = false): EmployeeEntity
    {
        // Check if already assigned
        $existing = $this->entityAssignments()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        if ($existing) {
            // Reactivate if previously unassigned
            if ($existing->unassigned_at) {
                $existing->update([
                    'unassigned_at' => null,
                    'assigned_at' => now(),
                    'is_primary' => $isPrimary,
                ]);
            } else {
                $existing->update(['is_primary' => $isPrimary]);
            }
            return $existing;
        }

        return $this->entityAssignments()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_primary' => $isPrimary,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Unassign employee from an entity
     */
    public function unassignFromEntity(string $entityType, int $entityId): bool
    {
        return $this->entityAssignments()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]) > 0;
    }

    /**
     * Attendance records
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Equipment/custody items
     */
    public function custodies()
    {
        return $this->hasMany(EmployeeCustody::class);
    }

    /**
     * Active custodies
     */
    public function activeCustodies()
    {
        return $this->custodies()->where('status', 'assigned');
    }

    /**
     * Documents
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Deductions
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Payrolls
     */
    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    /**
     * Scope for active employees
     */
    public function scopeActive($query)
    {
        return $query->where('employment_status', self::STATUS_ACTIVE);
    }

    /**
     * Scope by employment type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    /**
     * Scope by department
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope by branch
     */
    public function scopeInBranch($query, $branchId)
    {
        return $query->whereHas('branches', function ($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        });
    }

    /**
     * Get full name from user
     */
    public function getNameAttribute(): string
    {
        return $this->user->name ?? '';
    }

    /**
     * Get email from user
     */
    public function getEmailAttribute(): string
    {
        return $this->user->email ?? '';
    }

    /**
     * Get employment type label
     */
    public function getEmploymentTypeLabelAttribute(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? $this->employment_type;
    }

    /**
     * Get employment status label
     */
    public function getEmploymentStatusLabelAttribute(): string
    {
        return self::EMPLOYMENT_STATUSES[$this->employment_status] ?? $this->employment_status;
    }

    /**
     * Get total salary (base + allowances)
     */
    public function getTotalSalaryAttribute(): float
    {
        return $this->base_salary + $this->transport_allowance +
               $this->housing_allowance + $this->other_allowances;
    }

    /**
     * Get remaining vacation days
     */
    public function getRemainingVacationDaysAttribute(): int
    {
        return $this->vacation_days_balance - $this->vacation_days_used;
    }

    /**
     * Check if in probation period
     */
    public function getIsInProbationAttribute(): bool
    {
        if (!$this->probation_end_date) {
            return false;
        }
        return $this->probation_end_date->isFuture();
    }

    /**
     * Get years of service
     */
    public function getYearsOfServiceAttribute(): float
    {
        return $this->hire_date->diffInYears(now());
    }

    /**
     * Calculate daily salary rate
     */
    public function getDailySalaryRateAttribute(): float
    {
        return $this->base_salary / 30;
    }

    /**
     * Calculate hourly salary rate
     */
    public function getHourlySalaryRateAttribute(): float
    {
        return $this->daily_salary_rate / $this->work_hours_per_day;
    }

    /**
     * Calculate overtime hourly rate
     */
    public function getOvertimeHourlyRateAttribute(): float
    {
        return $this->hourly_salary_rate * $this->overtime_rate;
    }

    /**
     * Generate employee code
     */
    public static function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $lastEmployee = self::withTrashed()->orderBy('id', 'desc')->first();
        $nextNumber = $lastEmployee ? ($lastEmployee->id + 1) : 1;
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}


