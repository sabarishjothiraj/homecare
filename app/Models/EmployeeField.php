<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'type',
        'options',
        'is_required',
        'is_mandatory',
        'is_document',
        'order',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_mandatory' => 'boolean',
        'is_document' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active fields
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for mandatory fields
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope for document fields
     */
    public function scopeDocuments($query)
    {
        return $query->where('is_document', true);
    }

    /**
     * Scope for non-document fields
     */
    public function scopeNonDocuments($query)
    {
        return $query->where('is_document', false);
    }

    /**
     * Get ordered fields
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}

