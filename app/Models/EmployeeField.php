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

    /**
     * Select options as a list of strings. Rows saved before options were
     * parsed hold the raw comma-separated string, so that is accepted too;
     * anything else (e.g. file-format metadata) yields no options.
     */
    public function optionList(): array
    {
        $options = $this->options;

        if (is_string($options)) {
            $options = explode(',', $options);
        }

        if (! is_array($options) || ! array_is_list($options)) {
            return [];
        }

        return static::cleanOptions($options);
    }

    /**
     * Turn the admin's comma-separated input into a list of options.
     */
    public static function parseOptions(?string $input): array
    {
        return static::cleanOptions(explode(',', (string) $input));
    }

    private static function cleanOptions(array $options): array
    {
        $options = array_map(fn ($option) => trim((string) $option), array_filter($options, 'is_scalar'));

        return array_values(array_unique(array_filter($options, fn ($option) => $option !== '')));
    }
}
