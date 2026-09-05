<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'otp_token',
        'otp_code',
        'attempts',
        'sent_to',
        'expires_at',
        'verified_at',
        'consumed_at',
    ];

    /**
     * The hashed code is never serialised — it must not reach an API response.
     */
    protected $hidden = [
        'otp_code',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
