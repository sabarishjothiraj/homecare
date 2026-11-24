<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    // Folders employee uploads are stored in. New files go to the private
    // 'local' disk; files uploaded earlier may still sit on the 'public' disk.
    public const PHOTO_DIRECTORY = 'employee_images';

    public const DOCUMENT_DIRECTORY = 'employee_documents_dynamic';

    public const FILE_DISK = 'local';

    public const LEGACY_FILE_DISK = 'public';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'field_data',
        'caste_id',
        'subcaste',
        'created_by',
        'status',
        'source',
        'activated_at',
        'payment_valid_until',
    ];

    protected $casts = [
        'field_data' => 'array',
        'activated_at' => 'datetime',
        'payment_valid_until' => 'datetime',
    ];

    /**
     * Get the caste that the employee belongs to
     */
    public function caste()
    {
        return $this->belongsTo(Caste::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get employee documents
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Get employee assignments
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get employee payouts
     */
    public function payouts()
    {
        return $this->hasMany(EmployeePayout::class);
    }

    /**
     * Salary history, latest effective first
     */
    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class)->orderByDesc('effective_from');
    }

    /**
     * Salary in force on a date (defaults to today)
     */
    public function salaryOn($date = null): ?EmployeeSalary
    {
        $date = \Illuminate\Support\Carbon::parse($date ?? now())->toDateString();

        return $this->salaries->first(fn (EmployeeSalary $salary) => $salary->effective_from->toDateString() <= $date);
    }

    /**
     * Daily attendance marks
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get employee payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get employee categories/services
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'employee_service')->withTimestamps();
    }

    /**
     * Scope for active employees
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive employees
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Active employees not placed on any active assignment (including covers)
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->whereDoesntHave('assignments', fn ($q) => $q->where('is_active', true));
    }

    /**
     * Employees currently placed on an active assignment or cover
     */
    public function scopePlaced($query)
    {
        return $query->whereHas('assignments', fn ($q) => $q->where('is_active', true));
    }

    /**
     * Scope for pending employees
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if employee payment is valid
     */
    public function hasValidPayment(): bool
    {
        if (! $this->payment_valid_until) {
            return true; // No payment required
        }

        return $this->payment_valid_until->isFuture();
    }

    /**
     * Get field value
     */
    public function getField(string $key, $default = null)
    {
        if ($key === 'name') {
            $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }
        }

        if (in_array($key, ['email', 'phone', 'alternate_phone'], true) && ! empty($this->{$key})) {
            return $this->{$key};
        }

        return $this->field_data[$key] ?? $default;
    }

    /**
     * Set field value
     */
    public function setField(string $key, $value): void
    {
        $data = $this->field_data ?? [];
        $data[$key] = $value;
        $this->field_data = $data;
    }

    /**
     * Documents uploaded through the employee form, keyed by their position
     * in field_data (the index the document route uses).
     */
    public function uploadedDocuments(): array
    {
        $documents = $this->field_data['documents'] ?? [];

        return is_array($documents) ? array_filter($documents, 'is_array') : [];
    }

    /**
     * Whether a stored path points inside the given employee upload folder.
     * Only such paths are ever served, moved or deleted, so a path that found
     * its way into field_data can never reach other files on either disk.
     */
    public static function isUploadPath(mixed $path, string $directory): bool
    {
        return is_string($path) && str_starts_with($path, $directory.'/') && ! str_contains($path, '..');
    }

    /**
     * Disk holding an employee upload, or null when there is no such file.
     */
    public static function fileDisk(mixed $path, string $directory): ?string
    {
        if (! static::isUploadPath($path, $directory)) {
            return null;
        }

        // fileExists, not exists: a directory at that path is not an upload.
        foreach ([self::FILE_DISK, self::LEGACY_FILE_DISK] as $disk) {
            if (Storage::disk($disk)->fileExists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
