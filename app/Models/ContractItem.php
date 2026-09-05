<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'service_id',
        'amount',
        'max_assignments',
        'assignment_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'max_assignments' => 'integer',
        'assignment_count' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function canAssignMore(): bool
    {
        return $this->assignment_count < $this->max_assignments;
    }
}
