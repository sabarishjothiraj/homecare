<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactQuery extends Model
{
    use HasFactory;

    /**
     * status => [label, bootstrap colour]. These are the only values the
     * contact_queries.status enum accepts; MySQL strict mode rejects others.
     */
    public const STATUSES = [
        'new' => ['label' => 'New', 'tone' => 'primary'],
        'in_progress' => ['label' => 'In Progress', 'tone' => 'info'],
        'resolved' => ['label' => 'Resolved', 'tone' => 'success'],
        'closed' => ['label' => 'Closed', 'tone' => 'secondary'],
    ];

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
     * Scope for queries an admin has opened but not yet resolved
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for resolved (responded to) queries
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status]['tone'] ?? 'secondary';
    }
}
