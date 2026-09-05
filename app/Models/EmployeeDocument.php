<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employee_field_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    /**
     * Get the employee that owns the document
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the field definition
     */
    public function field()
    {
        return $this->belongsTo(EmployeeField::class, 'employee_field_id');
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

