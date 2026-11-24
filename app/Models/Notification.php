<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'link',
        'contract_id',
        'user_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the contract
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Create contract expiring notification
     */
    public static function createContractExpiringNotification(Contract $contract): self
    {
        $patientName = $contract->patient?->name ?? 'N/A';

        // The date rather than "N days left", which is wrong from the next day on
        return self::create([
            'type' => 'contract_expiring',
            'title' => 'Contract Expiring Soon',
            'message' => "Contract {$contract->contract_number} for patient {$patientName} will expire on {$contract->end_date->format('M d, Y')}.",
            // Relative: the scheduler builds this, and its APP_URL may not be the host staff use
            'link' => route('admin.contracts.show', $contract, false),
            'contract_id' => $contract->id,
            'is_read' => false,
        ]);
    }
}
