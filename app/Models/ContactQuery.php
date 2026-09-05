<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_response',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Get the user who responded
     */
    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Scope for new queries
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Scope for resolved queries
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for responded queries
     */
    public function scopeResponded($query)
    {
        return $query->where('status', 'responded');
    }
}

