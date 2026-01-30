<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;
use Illuminate\Support\Facades\Storage;

class EmployeeDocument extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'employee_id',
        'type',
        'title',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'document_number',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    // Document type constants
    public const TYPE_NATIONAL_ID = 'national_id';
    public const TYPE_PASSPORT = 'passport';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_RESUME = 'resume';
    public const TYPE_PHOTO = 'photo';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_NATIONAL_ID => 'National ID',
        self::TYPE_PASSPORT => 'Passport',
        self::TYPE_CONTRACT => 'Contract',
        self::TYPE_CERTIFICATE => 'Certificate',
        self::TYPE_RESUME => 'Resume/CV',
        self::TYPE_PHOTO => 'Photo',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * Employee who owns this document
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * User who verified this document
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * User who uploaded this document
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for unverified documents
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope for expiring documents
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '>', today())
                     ->where('expiry_date', '<=', today()->addDays($days));
    }

    /**
     * Scope for expired documents
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '<', today());
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Check if document is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->isPast();
    }

    /**
     * Check if document is expiring soon
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->isBetween(today(), today()->addDays(30));
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return today()->diffInDays($this->expiry_date, false);
    }

    /**
     * Get file size formatted
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    /**
     * Get full file path in storage
     */
    public function getFullPathAttribute(): string
    {
        return $this->file_path;
    }

    /**
     * Get download URL (signed for security)
     */
    public function getDownloadUrlAttribute(): string
    {
        return Storage::temporaryUrl($this->file_path, now()->addHours(1));
    }

    /**
     * Mark document as verified
     */
    public function verify(int $verifiedByUserId): self
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => $verifiedByUserId,
            'verified_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark document as unverified
     */
    public function unverify(): self
    {
        $this->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return $this;
    }

    /**
     * Delete the file from storage
     */
    public function deleteFile(): bool
    {
        if (Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }
        return false;
    }

    /**
     * Get storage path for employee documents
     */
    public static function getStoragePath(int $employeeId): string
    {
        return "employee_documents/{$employeeId}";
    }
}


