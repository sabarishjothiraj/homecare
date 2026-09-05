<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'note',
        'created_by',
    ];

    /**
     * Get assignment
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
